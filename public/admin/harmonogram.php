<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

const DNI_TYGODNIA = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];
const TYPY_NIEOBECNOSCI = ['vacation' => 'Urlop', 'sick_leave' => 'Zwolnienie lekarskie', 'training' => 'Szkolenie', 'other' => 'Inne'];

function poprawnaGodzina(string $g): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $g);
}

function poprawnaDataGrafiku(string $d): bool
{
    $data = DateTime::createFromFormat('!Y-m-d', $d);
    return $data !== false && $data->format('Y-m-d') === $d;
}

function poprawnaDataGodzina(string $d): ?string
{
    $data = DateTime::createFromFormat('!Y-m-d\TH:i', $d);
    return $data !== false && $data->format('Y-m-d\TH:i') === $d ? $data->format('Y-m-d H:i:s') : null;
}

$inspektorzy = db()->query(
    'SELECT i.id, i.is_active, u.first_name, u.last_name
     FROM inspectors i JOIN users u ON u.id = i.user_id
     ORDER BY u.last_name, u.first_name'
)->fetchAll();

if (!$inspektorzy) {
    bladStrony(404, 'Najpierw dodaj inspektora.');
}

$idInspektorow = array_map('intval', array_column($inspektorzy, 'id'));
$inspektorId = filter_var($_GET['inspektor'] ?? null, FILTER_VALIDATE_INT) ?: $idInspektorow[0];

if (!in_array($inspektorId, $idInspektorow, true)) {
    bladStrony(404, 'Nie znaleziono inspektora.');
}

$adres = 'admin/harmonogram.php?inspektor=' . $inspektorId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $akcja = pole('akcja');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

    if ($akcja === 'dodaj_grafik') {
        $dzien = filter_var(pole('dzien'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
        $od = pole('od');
        $do = pole('do');
        $waznyOd = pole('wazny_od');
        $waznyDo = pole('wazny_do');

        if ($dzien === false || !poprawnaGodzina($od) || !poprawnaGodzina($do) || $od >= $do) {
            komunikat('blad', 'Wybierz dzień i podaj godziny, np. 08:00-16:00. Początek musi być przed końcem.');
        } elseif (!poprawnaDataGrafiku($waznyOd) || !poprawnaDataGrafiku($waznyDo) || $waznyOd > $waznyDo) {
            komunikat('blad', 'Podaj poprawny okres obowiązywania grafiku.');
        } else {
            // dwa przedziały nachodzą na siebie, gdy każdy zaczyna się przed końcem drugiego
            $kolizja = db()->prepare(
                'SELECT COUNT(*) FROM work_schedules
                 WHERE inspector_id = :inspektor AND weekday = :dzien
                   AND valid_from <= :wazny_do AND valid_until >= :wazny_od
                   AND start_time < :do AND end_time > :od'
            );
            $kolizja->execute([
                'inspektor' => $inspektorId, 'dzien' => $dzien,
                'wazny_od' => $waznyOd, 'wazny_do' => $waznyDo, 'od' => $od, 'do' => $do,
            ]);

            if ((int) $kolizja->fetchColumn() > 0) {
                komunikat('blad', 'Te godziny nachodzą na istniejący grafik tego dnia.');
            } else {
                db()->prepare(
                    'INSERT INTO work_schedules (inspector_id, weekday, valid_from, valid_until, start_time, end_time)
                     VALUES (:inspektor, :dzien, :wazny_od, :wazny_do, :od, :do)'
                )->execute([
                    'inspektor' => $inspektorId, 'dzien' => $dzien,
                    'wazny_od' => $waznyOd, 'wazny_do' => $waznyDo, 'od' => $od, 'do' => $do,
                ]);
                zapiszAudyt('schedule.create', 'work_schedules', (int) db()->lastInsertId(),
                    'Grafik inspektora #' . $inspektorId . ': ' . DNI_TYGODNIA[$dzien] . ' ' . $od . '-' . $do);
                komunikat('sukces', 'Dodano godziny pracy.');
            }
        }
    } elseif ($akcja === 'usun_grafik') {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'DELETE b FROM schedule_breaks b JOIN work_schedules w ON w.id = b.schedule_id
             WHERE w.id = :id AND w.inspector_id = :inspektor'
        )->execute(['id' => $id, 'inspektor' => $inspektorId]);
        $usun = $pdo->prepare('DELETE FROM work_schedules WHERE id = :id AND inspector_id = :inspektor');
        $usun->execute(['id' => $id, 'inspektor' => $inspektorId]);

        if ($usun->rowCount() > 0) {
            zapiszAudyt('schedule.delete', 'work_schedules', $id, 'Usunięcie godzin pracy inspektora #' . $inspektorId);
            komunikat('sukces', 'Usunięto godziny pracy. Istniejące rezerwacje zostają bez zmian.');
        }
        $pdo->commit();
    } elseif ($akcja === 'dodaj_przerwe') {
        $od = pole('od');
        $do = pole('do');

        $grafik = db()->prepare(
            'SELECT TIME_FORMAT(start_time, \'%H:%i\') AS poczatek, TIME_FORMAT(end_time, \'%H:%i\') AS koniec
             FROM work_schedules WHERE id = :id AND inspector_id = :inspektor'
        );
        $grafik->execute(['id' => $id, 'inspektor' => $inspektorId]);
        $grafik = $grafik->fetch();

        if (!$grafik) {
            bladStrony(404, 'Nie znaleziono grafiku.');
        }

        if (!poprawnaGodzina($od) || !poprawnaGodzina($do) || $od >= $do) {
            komunikat('blad', 'Podaj poprawne godziny przerwy.');
        } elseif ($od < $grafik['poczatek'] || $do > $grafik['koniec']) {
            komunikat('blad', 'Przerwa musi mieścić się w godzinach pracy (' . $grafik['poczatek'] . '-' . $grafik['koniec'] . ').');
        } else {
            $kolizja = db()->prepare(
                'SELECT COUNT(*) FROM schedule_breaks WHERE schedule_id = :id AND start_time < :do AND end_time > :od'
            );
            $kolizja->execute(['id' => $id, 'od' => $od, 'do' => $do]);

            if ((int) $kolizja->fetchColumn() > 0) {
                komunikat('blad', 'Ta przerwa nachodzi na inną przerwę.');
            } else {
                db()->prepare('INSERT INTO schedule_breaks (schedule_id, start_time, end_time) VALUES (:id, :od, :do)')
                    ->execute(['id' => $id, 'od' => $od, 'do' => $do]);
                zapiszAudyt('schedule.break_create', 'work_schedules', $id, 'Przerwa ' . $od . '-' . $do);
                komunikat('sukces', 'Dodano przerwę.');
            }
        }
    } elseif ($akcja === 'usun_przerwe') {
        // przez JOIN - przerwa musi należeć do grafiku tego inspektora
        $usun = db()->prepare(
            'DELETE b FROM schedule_breaks b JOIN work_schedules w ON w.id = b.schedule_id
             WHERE b.id = :id AND w.inspector_id = :inspektor'
        );
        $usun->execute(['id' => $id, 'inspektor' => $inspektorId]);

        if ($usun->rowCount() > 0) {
            zapiszAudyt('schedule.break_delete', 'schedule_breaks', $id, 'Usunięcie przerwy inspektora #' . $inspektorId);
            komunikat('sukces', 'Usunięto przerwę.');
        }
    } elseif ($akcja === 'dodaj_nieobecnosc') {
        $od = poprawnaDataGodzina(pole('od'));
        $do = poprawnaDataGodzina(pole('do'));
        $typ = pole('typ');
        $notatka = mb_substr(pole('notatka'), 0, 255);

        if ($od === null || $do === null || $od >= $do) {
            komunikat('blad', 'Podaj poprawny początek i koniec nieobecności.');
        } elseif (!array_key_exists($typ, TYPY_NIEOBECNOSCI)) {
            komunikat('blad', 'Wybierz rodzaj nieobecności.');
        } else {
            db()->prepare(
                'INSERT INTO inspector_absences (inspector_id, starts_at, ends_at, absence_type, note, created_by)
                 VALUES (:inspektor, :od, :do, :typ, :notatka, :kto)'
            )->execute([
                'inspektor' => $inspektorId, 'od' => $od, 'do' => $do, 'typ' => $typ,
                'notatka' => $notatka !== '' ? $notatka : null, 'kto' => aktualnyUzytkownik()['id'],
            ]);
            zapiszAudyt('absence.create', 'inspector_absences', (int) db()->lastInsertId(),
                TYPY_NIEOBECNOSCI[$typ] . ' inspektora #' . $inspektorId . ': ' . $od . ' - ' . $do);

            $kolizje = db()->prepare(
                "SELECT COUNT(*) FROM reservations
                 WHERE inspector_id = :inspektor AND status_code IN ('pending', 'confirmed')
                   AND starts_at < :do AND ends_at > :od"
            );
            $kolizje->execute(['inspektor' => $inspektorId, 'od' => $od, 'do' => $do]);
            $ile = (int) $kolizje->fetchColumn();

            komunikat('sukces', 'Dodano nieobecność.');
            if ($ile > 0) {
                komunikat('blad', 'Uwaga: w tym czasie są aktywne rezerwacje (' . $ile . '). Sprawdź je w zakładce Rezerwacje.');
            }
        }
    } elseif ($akcja === 'usun_nieobecnosc') {
        $usun = db()->prepare('DELETE FROM inspector_absences WHERE id = :id AND inspector_id = :inspektor');
        $usun->execute(['id' => $id, 'inspektor' => $inspektorId]);

        if ($usun->rowCount() > 0) {
            zapiszAudyt('absence.delete', 'inspector_absences', $id, 'Usunięcie nieobecności inspektora #' . $inspektorId);
            komunikat('sukces', 'Usunięto nieobecność.');
        }
    } else {
        bladStrony(400, 'Nieznana operacja.');
    }

    przekieruj($adres);
}

$grafiki = db()->prepare(
    'SELECT id, weekday, valid_from, valid_until,
            TIME_FORMAT(start_time, \'%H:%i\') AS poczatek, TIME_FORMAT(end_time, \'%H:%i\') AS koniec
     FROM work_schedules WHERE inspector_id = :id
     ORDER BY weekday, start_time, valid_from'
);
$grafiki->execute(['id' => $inspektorId]);
$grafiki = $grafiki->fetchAll();

$przerwy = db()->prepare(
    'SELECT b.id, b.schedule_id, TIME_FORMAT(b.start_time, \'%H:%i\') AS poczatek, TIME_FORMAT(b.end_time, \'%H:%i\') AS koniec
     FROM schedule_breaks b JOIN work_schedules w ON w.id = b.schedule_id
     WHERE w.inspector_id = :id ORDER BY b.start_time'
);
$przerwy->execute(['id' => $inspektorId]);
$przerwyGrafiku = [];
foreach ($przerwy->fetchAll() as $p) {
    $przerwyGrafiku[$p['schedule_id']][] = $p;
}

$nieobecnosci = db()->prepare(
    'SELECT id, starts_at, ends_at, absence_type, note FROM inspector_absences
     WHERE inspector_id = :id AND ends_at >= NOW() - INTERVAL 30 DAY
     ORDER BY starts_at DESC'
);
$nieobecnosci->execute(['id' => $inspektorId]);
$nieobecnosci = $nieobecnosci->fetchAll();

$tytul = 'Harmonogram';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Harmonogram</h1>
<p class="text-mobiTextMuted mb-6">Godziny pracy, przerwy i nieobecności inspektorów.</p>

<?= menuAdmina('harmonogram.php') ?>

<form method="get" action="<?= e(url('admin/harmonogram.php')) ?>" class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[12rem]">
        <label for="inspektor" class="block text-xs font-semibold mb-1">Inspektor</label>
        <select id="inspektor" name="inspektor" class="pole">
            <?php foreach ($inspektorzy as $i): ?>
                <option value="<?= (int) $i['id'] ?>" <?= (int) $i['id'] === $inspektorId ? 'selected' : '' ?>>
                    <?= e($i['first_name'] . ' ' . $i['last_name']) ?><?= (int) $i['is_active'] === 1 ? '' : ' (nieaktywny)' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">Pokaż</button>
</form>

<section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm mb-4" aria-labelledby="grafik-tytul">
    <h2 id="grafik-tytul" class="text-lg font-bold text-gray-900 mb-4">Godziny pracy</h2>

    <?php if (!$grafiki): ?>
        <p class="text-sm text-mobiTextMuted mb-4">Ten inspektor nie ma jeszcze grafiku.</p>
    <?php endif; ?>

    <ul class="divide-y divide-gray-100 mb-5">
        <?php foreach ($grafiki as $g): ?>
            <li class="py-3">
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <p class="text-sm">
                        <strong><?= e(DNI_TYGODNIA[(int) $g['weekday']]) ?></strong> <?= e($g['poczatek']) ?>–<?= e($g['koniec']) ?>
                        <span class="block text-xs text-mobiTextMuted">od <?= e(date('d.m.Y', strtotime($g['valid_from']))) ?> do <?= e(date('d.m.Y', strtotime($g['valid_until']))) ?></span>
                    </p>
                    <form method="post" action="<?= e(url($adres)) ?>" data-potwierdz="Usunąć te godziny pracy razem z przerwami?">
                        <?= csrfPole() ?>
                        <input type="hidden" name="akcja" value="usun_grafik">
                        <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50">Usuń</button>
                    </form>
                </div>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <?php foreach ($przerwyGrafiku[$g['id']] ?? [] as $p): ?>
                        <form method="post" action="<?= e(url($adres)) ?>" class="inline-flex items-center gap-1 text-xs bg-gray-100 rounded-lg px-2 py-1">
                            <?= csrfPole() ?>
                            <input type="hidden" name="akcja" value="usun_przerwe">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            Przerwa <?= e($p['poczatek']) ?>–<?= e($p['koniec']) ?>
                            <button type="submit" aria-label="Usuń przerwę <?= e($p['poczatek']) ?>" class="text-gray-500 hover:text-red-700 px-1">×</button>
                        </form>
                    <?php endforeach; ?>
                    <form method="post" action="<?= e(url($adres)) ?>" class="inline-flex items-center gap-1 text-xs">
                        <?= csrfPole() ?>
                        <input type="hidden" name="akcja" value="dodaj_przerwe">
                        <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                        <input type="time" name="od" required aria-label="Początek przerwy" class="border border-gray-300 rounded-lg px-1 py-1">
                        <input type="time" name="do" required aria-label="Koniec przerwy" class="border border-gray-300 rounded-lg px-1 py-1">
                        <button type="submit" class="font-semibold px-2 py-1 rounded-lg border border-gray-200 hover:border-gray-300">+ przerwa</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <form method="post" action="<?= e(url($adres)) ?>" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end border-t border-gray-100 pt-4">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="dodaj_grafik">
        <div class="col-span-2 md:col-span-1">
            <label for="dzien" class="block text-xs font-semibold mb-1">Dzień</label>
            <select id="dzien" name="dzien" class="pole" required>
                <?php foreach (DNI_TYGODNIA as $nr => $nazwa): ?>
                    <option value="<?= $nr ?>"><?= e($nazwa) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="grafik-od" class="block text-xs font-semibold mb-1">Od godziny</label>
            <input type="time" id="grafik-od" name="od" value="08:00" required class="pole">
        </div>
        <div>
            <label for="grafik-do" class="block text-xs font-semibold mb-1">Do godziny</label>
            <input type="time" id="grafik-do" name="do" value="16:00" required class="pole">
        </div>
        <div>
            <label for="wazny-od" class="block text-xs font-semibold mb-1">Ważny od</label>
            <input type="date" id="wazny-od" name="wazny_od" value="<?= e(date('Y-m-d')) ?>" required class="pole">
        </div>
        <div>
            <label for="wazny-do" class="block text-xs font-semibold mb-1">Ważny do</label>
            <input type="date" id="wazny-do" name="wazny_do" value="<?= e(date('Y') + 1 . '-12-31') ?>" required class="pole">
        </div>
        <div class="col-span-2 md:col-span-5">
            <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">Dodaj godziny pracy</button>
        </div>
    </form>
</section>

<section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm" aria-labelledby="nieobecnosci-tytul">
    <h2 id="nieobecnosci-tytul" class="text-lg font-bold text-gray-900 mb-4">Urlopy i nieobecności</h2>

    <?php if (!$nieobecnosci): ?>
        <p class="text-sm text-mobiTextMuted mb-4">Brak zaplanowanych nieobecności.</p>
    <?php endif; ?>

    <ul class="divide-y divide-gray-100 mb-5">
        <?php foreach ($nieobecnosci as $n): ?>
            <li class="py-3 flex flex-wrap justify-between items-center gap-2">
                <p class="text-sm">
                    <strong><?= e(TYPY_NIEOBECNOSCI[$n['absence_type']] ?? $n['absence_type']) ?></strong>
                    <?= e(date('d.m.Y H:i', strtotime($n['starts_at']))) ?> – <?= e(date('d.m.Y H:i', strtotime($n['ends_at']))) ?>
                    <?php if ($n['note']): ?>
                        <span class="block text-xs text-mobiTextMuted"><?= e($n['note']) ?></span>
                    <?php endif; ?>
                </p>
                <form method="post" action="<?= e(url($adres)) ?>" data-potwierdz="Usunąć tę nieobecność?">
                    <?= csrfPole() ?>
                    <input type="hidden" name="akcja" value="usun_nieobecnosc">
                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50">Usuń</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

    <form method="post" action="<?= e(url($adres)) ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end border-t border-gray-100 pt-4">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="dodaj_nieobecnosc">
        <div>
            <label for="typ" class="block text-xs font-semibold mb-1">Rodzaj</label>
            <select id="typ" name="typ" class="pole" required>
                <?php foreach (TYPY_NIEOBECNOSCI as $kod => $nazwa): ?>
                    <option value="<?= e($kod) ?>"><?= e($nazwa) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="nieob-od" class="block text-xs font-semibold mb-1">Od</label>
            <input type="datetime-local" id="nieob-od" name="od" required class="pole">
        </div>
        <div>
            <label for="nieob-do" class="block text-xs font-semibold mb-1">Do</label>
            <input type="datetime-local" id="nieob-do" name="do" required class="pole">
        </div>
        <div>
            <label for="notatka" class="block text-xs font-semibold mb-1">Notatka</label>
            <input type="text" id="notatka" name="notatka" maxlength="255" class="pole">
        </div>
        <div class="md:col-span-4">
            <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">Dodaj nieobecność</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
