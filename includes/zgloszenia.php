<?php
// Anonimowe zgłoszenia: tokeny dostępu, zapis i załączniki.
// Nie zapisujemy IP ani konta zgłaszającego - jedynym kluczem do sprawy jest token.

const ZALACZNIKI_KATALOG = __DIR__ . '/../storage/zalaczniki';
const ZALACZNIK_MAX_BAJTOW = 10 * 1024 * 1024;   // 10 MB na plik
const ZALACZNIKI_MAX_PLIKOW = 3;
const ZALACZNIKI_TYPY = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'video/mp4' => 'mp4',
];
const STATUSY_ZGLOSZEN = [
    'new' => 'Nowe',
    'in_review' => 'W trakcie analizy',
    'resolved' => 'Rozpatrzone',
    'rejected' => 'Odrzucone',
];

// Token pokazywany zgłaszającemu, np. MSY-3F9A-07C2-B1D4-88E0-5A6C (80 bitów losowości).
function generujToken(): string
{
    return 'MSY-' . implode('-', str_split(strtoupper(bin2hex(random_bytes(10))), 4));
}

// W bazie jest tylko skrót SHA-256 - wyciek bazy nie zdradza tokenów.
function hashTokenu(string $token): string
{
    return hash('sha256', $token, true);
}

// Szuka zgłoszenia po tokenie. Wielkość liter i spacje wokół nie mają znaczenia
// dla tokenów MSY-..., stare tokeny demonstracyjne są sprawdzane dokładnie.
function zgloszeniePoTokenie(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) > 64) {
        return null;
    }

    $zapytanie = db()->prepare(
        'SELECT r.id, r.title, r.status_code, r.public_message, r.created_at, r.updated_at, c.name AS kategoria
         FROM reports r JOIN report_categories c ON c.id = r.category_id
         WHERE r.token_hash IN (:h1, :h2)'
    );
    $zapytanie->execute(['h1' => hashTokenu($token), 'h2' => hashTokenu(strtoupper($token))]);
    return $zapytanie->fetch() ?: null;
}

// Sprawdza pliki z $_FILES['zalaczniki'] i zwraca [listaPlikow, bledy].
function sprawdzZalaczniki(array $pliki): array
{
    $lista = [];
    $bledy = [];

    // brak pola albo pusty wybór plików
    if (!isset($pliki['name']) || !is_array($pliki['name'])) {
        return [[], []];
    }

    foreach ($pliki['name'] as $i => $nazwa) {
        $kod = $pliki['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($kod === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($kod === UPLOAD_ERR_INI_SIZE || $kod === UPLOAD_ERR_FORM_SIZE) {
            $bledy[] = 'Plik „' . $nazwa . '” jest za duży (maks. 10 MB).';
            continue;
        }
        if ($kod !== UPLOAD_ERR_OK || !is_uploaded_file($pliki['tmp_name'][$i])) {
            $bledy[] = 'Nie udało się przesłać pliku „' . $nazwa . '”.';
            continue;
        }

        $rozmiar = (int) $pliki['size'][$i];
        // typ sprawdzamy po zawartości pliku, nie po rozszerzeniu ani nagłówku z przeglądarki
        $typ = (new finfo(FILEINFO_MIME_TYPE))->file($pliki['tmp_name'][$i]);

        if ($rozmiar < 1 || $rozmiar > ZALACZNIK_MAX_BAJTOW) {
            $bledy[] = 'Plik „' . $nazwa . '” jest pusty albo za duży (maks. 10 MB).';
        } elseif (!isset(ZALACZNIKI_TYPY[$typ])) {
            $bledy[] = 'Plik „' . $nazwa . '” ma niedozwolony typ. Dozwolone: PDF, JPG, PNG, MP4.';
        } else {
            $lista[] = [
                'tmp' => $pliki['tmp_name'][$i],
                'nazwa' => mb_substr(basename((string) $nazwa), 0, 255),
                'typ' => $typ,
                'rozmiar' => $rozmiar,
            ];
        }
    }

    if (count($lista) > ZALACZNIKI_MAX_PLIKOW) {
        $bledy[] = 'Możesz dodać najwyżej ' . ZALACZNIKI_MAX_PLIKOW . ' pliki.';
    }

    return [$lista, $bledy];
}

// Zapis zgłoszenia z załącznikami. Zwraca token (pokazywany tylko raz).
function zapiszZgloszenie(array $dane, array $zalaczniki): string
{
    $token = generujToken();
    $przeniesione = [];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'INSERT INTO reports (category_id, token_hash, title, description, incident_at, incident_location)
             VALUES (:kategoria, :hash, :tytul, :opis, :kiedy, :gdzie)'
        )->execute([
            'kategoria' => $dane['kategoria'],
            'hash' => hashTokenu($token),
            'tytul' => $dane['tytul'],
            'opis' => $dane['opis'],
            'kiedy' => $dane['kiedy'],
            'gdzie' => $dane['gdzie'] !== '' ? $dane['gdzie'] : null,
        ]);
        $id = (int) $pdo->lastInsertId();

        $dodaj = $pdo->prepare(
            'INSERT INTO report_attachments (report_id, storage_key, original_name, mime_type, size_bytes, sha256)
             VALUES (:zgloszenie, :klucz, :nazwa, :typ, :rozmiar, :skrot)'
        );

        foreach ($zalaczniki as $plik) {
            // losowa nazwa na dysku - oryginalna nazwa jest tylko w bazie
            $klucz = date('Y/m/') . bin2hex(random_bytes(16)) . '.' . ZALACZNIKI_TYPY[$plik['typ']];
            $cel = ZALACZNIKI_KATALOG . '/' . $klucz;

            if (!is_dir(dirname($cel)) && !mkdir(dirname($cel), 0750, true)) {
                throw new RuntimeException('Nie można utworzyć katalogu na załączniki.');
            }
            if (!move_uploaded_file($plik['tmp'], $cel)) {
                throw new RuntimeException('Nie można zapisać załącznika.');
            }
            $przeniesione[] = $cel;

            $dodaj->execute([
                'zgloszenie' => $id,
                'klucz' => $klucz,
                'nazwa' => $plik['nazwa'],
                'typ' => $plik['typ'],
                'rozmiar' => $plik['rozmiar'],
                'skrot' => hash_file('sha256', $cel, true),
            ]);
        }

        $pdo->commit();
        return $token;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        // bez zgłoszenia w bazie pliki na dysku są niepotrzebne
        foreach ($przeniesione as $plik) {
            @unlink($plik);
        }
        throw $ex;
    }
}
