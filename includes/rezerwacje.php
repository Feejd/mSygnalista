<?php
// Dostępność inspektorów i zapisywanie wizyt.
// Godziny liczymy w minutach od północy, np. 09:30 = 570 - łatwiej porównywać przedziały.

const KROK_TERMINOW = 15;          // co ile minut może zaczynać się wizyta
const REZERWACJA_DNI_DO_PRZODU = 60;

function naMinuty(string $godzina): int
{
    [$h, $m] = explode(':', $godzina);
    return (int) $h * 60 + (int) $m;
}

function naGodzine(int $minuty): string
{
    return sprintf('%02d:%02d', intdiv($minuty, 60), $minuty % 60);
}

// Reguła z wytycznych: przedziały kolidują, gdy nowy zaczyna się przed końcem
// istniejącego i kończy po jego początku. Styk (koniec = początek) nie jest kolizją.
function nakladajaSie(int $poczatekA, int $koniecA, int $poczatekB, int $koniecB): bool
{
    return $poczatekA < $koniecB && $koniecA > $poczatekB;
}

// Odejmuje od przedziałów [od, do] wszystkie przedziały z $wyciecia.
function odejmijPrzedzialy(array $przedzialy, array $wyciecia): array
{
    foreach ($wyciecia as [$wOd, $wDo]) {
        $wynik = [];
        foreach ($przedzialy as [$od, $do]) {
            if (!nakladajaSie($od, $do, $wOd, $wDo)) {
                $wynik[] = [$od, $do];
                continue;
            }
            if ($od < $wOd) {
                $wynik[] = [$od, $wOd];
            }
            if ($do > $wDo) {
                $wynik[] = [$wDo, $do];
            }
        }
        $przedzialy = $wynik;
    }
    return $przedzialy;
}

function poprawnaDataRezerwacji(string $data): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $data);
    return $d !== false && $d->format('Y-m-d') === $data;
}

// Godziny pracy inspektora w danym dniu, już bez przerw i nieobecności.
function godzinyPracy(int $inspektorId, string $data): array
{
    // wyjątkowy grafik na konkretny dzień zastępuje zwykły (np. dyżur w sobotę)
    $wyjatki = db()->prepare(
        'SELECT TIME_FORMAT(start_time, \'%H:%i\') AS od, TIME_FORMAT(end_time, \'%H:%i\') AS do_
         FROM availability_overrides WHERE inspector_id = :id AND work_date = :data'
    );
    $wyjatki->execute(['id' => $inspektorId, 'data' => $data]);
    $przedzialy = array_map(fn ($w) => [naMinuty($w['od']), naMinuty($w['do_'])], $wyjatki->fetchAll());

    if (!$przedzialy) {
        $swieto = db()->prepare('SELECT 1 FROM holidays WHERE holiday_date = :data');
        $swieto->execute(['data' => $data]);
        if ($swieto->fetchColumn()) {
            return [];
        }

        $grafik = db()->prepare(
            'SELECT id, TIME_FORMAT(start_time, \'%H:%i\') AS od, TIME_FORMAT(end_time, \'%H:%i\') AS do_
             FROM work_schedules
             WHERE inspector_id = :id AND weekday = :dzien AND valid_from <= :data1 AND valid_until >= :data2'
        );
        $grafik->execute(['id' => $inspektorId, 'dzien' => (int) date('N', strtotime($data)), 'data1' => $data, 'data2' => $data]);

        foreach ($grafik->fetchAll() as $g) {
            $przerwy = db()->prepare(
                'SELECT TIME_FORMAT(start_time, \'%H:%i\') AS od, TIME_FORMAT(end_time, \'%H:%i\') AS do_
                 FROM schedule_breaks WHERE schedule_id = :id'
            );
            $przerwy->execute(['id' => $g['id']]);
            $wyciecia = array_map(fn ($p) => [naMinuty($p['od']), naMinuty($p['do_'])], $przerwy->fetchAll());

            array_push($przedzialy, ...odejmijPrzedzialy([[naMinuty($g['od']), naMinuty($g['do_'])]], $wyciecia));
        }
    }

    // urlopy, zwolnienia, szkolenia - mogą trwać kilka dni, więc przycinamy je do tego dnia
    $nieobecnosci = db()->prepare(
        'SELECT starts_at, ends_at FROM inspector_absences
         WHERE inspector_id = :id AND starts_at < :koniec AND ends_at > :poczatek'
    );
    $nieobecnosci->execute(['id' => $inspektorId, 'poczatek' => $data . ' 00:00:00', 'koniec' => $data . ' 23:59:59']);

    $wyciecia = [];
    foreach ($nieobecnosci->fetchAll() as $n) {
        $od = substr($n['starts_at'], 0, 10) < $data ? 0 : naMinuty(substr($n['starts_at'], 11, 5));
        $do = substr($n['ends_at'], 0, 10) > $data ? 24 * 60 : naMinuty(substr($n['ends_at'], 11, 5));
        $wyciecia[] = [$od, $do];
    }

    $przedzialy = odejmijPrzedzialy($przedzialy, $wyciecia);
    usort($przedzialy, fn ($a, $b) => $a[0] <=> $b[0]);
    return $przedzialy;
}

// Zajęte przedziały inspektora w danym dniu (oczekujące i potwierdzone wizyty).
function zajeteTerminy(int $inspektorId, string $data, ?int $klientId = null): array
{
    $sql = "SELECT TIME_FORMAT(starts_at, '%H:%i') AS od, TIME_FORMAT(ends_at, '%H:%i') AS do_
            FROM reservations
            WHERE status_code IN ('pending', 'confirmed') AND DATE(starts_at) = :data AND (inspector_id = :inspektor";
    $parametry = ['data' => $data, 'inspektor' => $inspektorId];

    // klient też nie może mieć dwóch wizyt naraz, nawet u różnych inspektorów
    if ($klientId !== null) {
        $sql .= ' OR client_id = :klient';
        $parametry['klient'] = $klientId;
    }

    $zapytanie = db()->prepare($sql . ')');
    $zapytanie->execute($parametry);
    return array_map(fn ($z) => [naMinuty($z['od']), naMinuty($z['do_'])], $zapytanie->fetchAll());
}

// Lista godzin rozpoczęcia ("09:00", "09:15", ...), w których wizyta o danej długości się zmieści.
function wolneGodziny(int $inspektorId, string $data, int $czasTrwania, ?int $klientId = null): array
{
    $dzisiaj = date('Y-m-d');
    $ostatniDzien = date('Y-m-d', strtotime('+' . REZERWACJA_DNI_DO_PRZODU . ' days'));

    if (!poprawnaDataRezerwacji($data) || $data < $dzisiaj || $data > $ostatniDzien) {
        return [];
    }

    // dzisiaj tylko godziny, które jeszcze nie minęły
    $najwczesniej = $data === $dzisiaj ? naMinuty(date('H:i')) + 1 : 0;
    $zajete = zajeteTerminy($inspektorId, $data, $klientId);
    $wolne = [];

    foreach (godzinyPracy($inspektorId, $data) as [$od, $do]) {
        // pierwszy termin równo w siatce co 15 min
        $start = (int) (ceil(max($od, $najwczesniej) / KROK_TERMINOW) * KROK_TERMINOW);

        for ($t = $start; $t + $czasTrwania <= $do; $t += KROK_TERMINOW) {
            $kolizja = false;
            foreach ($zajete as [$zOd, $zDo]) {
                if (nakladajaSie($t, $t + $czasTrwania, $zOd, $zDo)) {
                    $kolizja = true;
                    break;
                }
            }
            if (!$kolizja) {
                $wolne[] = naGodzine($t);
            }
        }
    }

    return $wolne;
}

// Aktywna usługa + aktywny inspektor, który ją wykonuje. Null, gdy para jest niedostępna.
function uslugaUInspektora(int $uslugaId, int $inspektorId): ?array
{
    $zapytanie = db()->prepare(
        'SELECT s.id, s.name, s.duration_minutes, s.price, u.first_name, u.last_name, i.institution
         FROM services s
         JOIN service_categories c ON c.id = s.category_id AND c.is_active = 1
         JOIN inspector_services isv ON isv.service_id = s.id AND isv.is_active = 1
         JOIN inspectors i ON i.id = isv.inspector_id AND i.is_active = 1
         JOIN users u ON u.id = i.user_id AND u.is_active = 1
         WHERE s.id = :usluga AND i.id = :inspektor AND s.is_active = 1'
    );
    $zapytanie->execute(['usluga' => $uslugaId, 'inspektor' => $inspektorId]);
    return $zapytanie->fetch() ?: null;
}

// Zapis wizyty. Wszystko jest sprawdzane jeszcze raz po stronie serwera,
// bo dane z formularza mogły zostać zmienione. Zwraca [id, null] albo [null, komunikat].
function zarezerwuj(int $klientId, int $uslugaId, int $inspektorId, string $data, string $godzina, string $komentarz): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        // blokada wiersza inspektora - dwie osoby klikające ten sam termin
        // czekają na siebie, więc druga zobaczy już zajęty termin
        $pdo->prepare('SELECT id FROM inspectors WHERE id = :id FOR UPDATE')->execute(['id' => $inspektorId]);
        $pdo->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE')->execute(['id' => $klientId]);

        $usluga = uslugaUInspektora($uslugaId, $inspektorId);
        if ($usluga === null) {
            $pdo->rollBack();
            return [null, 'Ta usługa nie jest już dostępna u wybranego inspektora.'];
        }

        if (!in_array($godzina, wolneGodziny($inspektorId, $data, (int) $usluga['duration_minutes'], $klientId), true)) {
            $pdo->rollBack();
            return [null, 'Wybrany termin jest już zajęty albo niedostępny. Wybierz inną godzinę.'];
        }

        $poczatek = $data . ' ' . $godzina . ':00';
        $koniec = $data . ' ' . naGodzine(naMinuty($godzina) + (int) $usluga['duration_minutes']) . ':00';

        $pdo->prepare(
            'INSERT INTO reservations (client_id, inspector_id, service_id, starts_at, ends_at, price_at_booking,
                                       comment, changed_by, change_reason)
             VALUES (:klient, :inspektor, :usluga, :poczatek, :koniec, :cena, :komentarz, :kto, :powod)'
        )->execute([
            'klient' => $klientId,
            'inspektor' => $inspektorId,
            'usluga' => $uslugaId,
            'poczatek' => $poczatek,
            'koniec' => $koniec,
            'cena' => $usluga['price'],
            'komentarz' => $komentarz !== '' ? $komentarz : null,
            'kto' => $klientId,
            'powod' => 'Rezerwacja klienta',
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        return [$id, null];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

// --- Panel inspektora ---

// Profil inspektora dla zalogowanego konta albo null (np. admin bez profilu).
function profilInspektora(int $userId): ?array
{
    $zapytanie = db()->prepare(
        'SELECT id, institution, description, is_active FROM inspectors WHERE user_id = :id'
    );
    $zapytanie->execute(['id' => $userId]);
    return $zapytanie->fetch() ?: null;
}

// Wizyty jednego inspektora: 'dzis', 'przyszle' (od jutra) albo 'historia' (minione i anulowane).
function wizytyInspektora(int $inspektorId, string $widok, int $limit = 100, int $offset = 0): array
{
    $warunek = match ($widok) {
        'dzis' => 'DATE(r.starts_at) = CURDATE()',
        'przyszle' => "r.starts_at >= CURDATE() + INTERVAL 1 DAY AND r.status_code IN ('pending', 'confirmed')",
        default => "(r.starts_at < CURDATE() OR r.status_code IN ('completed', 'cancelled'))",
    };
    $kolejnosc = $widok === 'historia' ? 'r.starts_at DESC' : 'r.starts_at';

    $zapytanie = db()->prepare(
        "SELECT r.id, r.starts_at, r.ends_at, r.status_code, r.comment, s.name AS usluga,
                k.first_name AS k_imie, k.last_name AS k_nazwisko, k.phone AS k_telefon
         FROM reservations r
         JOIN services s ON s.id = r.service_id
         JOIN users k ON k.id = r.client_id
         WHERE r.inspector_id = :inspektor AND $warunek
         ORDER BY $kolejnosc
         LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
    );
    $zapytanie->execute(['inspektor' => $inspektorId]);
    return $zapytanie->fetchAll();
}

// Inspektor może oznaczyć wizytę jako zrealizowaną dopiero, gdy się zaczęła.
function dozwoloneStatusyInspektora(array $wizyta): array
{
    $mozliwe = PRZEJSCIA_STATUSOW[$wizyta['status_code']] ?? [];
    if (strtotime($wizyta['starts_at']) > time()) {
        $mozliwe = array_values(array_diff($mozliwe, ['completed']));
    }
    return $mozliwe;
}
