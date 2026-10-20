<?php
// Logowanie, sesja i kontrola dostępu.

const ROLA_OBYWATEL = 'citizen';
const ROLA_INSPEKTOR = 'inspector';
const ROLA_ADMIN = 'admin';

const SESJA_MAX_BEZCZYNNOSC = 30 * 60;  // wylogowanie po 30 min bez aktywności
const SESJA_ODNOWIENIE_ID = 15 * 60;     // nowy identyfikator sesji co 15 min

// Dane zalogowanego użytkownika wczytywane z bazy przy każdym żądaniu,
// więc zablokowanie konta albo zmiana roli działa od razu.
function aktualnyUzytkownik(): ?array
{
    static $uzytkownik = false;

    if ($uzytkownik !== false) {
        return $uzytkownik;
    }

    $uzytkownik = null;
    $id = $_SESSION['user_id'] ?? null;

    if (!is_int($id)) {
        return null;
    }

    $zapytanie = db()->prepare(
        'SELECT id, first_name, last_name, email, phone, is_active, created_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $zapytanie->execute(['id' => $id]);
    $wiersz = $zapytanie->fetch();

    if (!$wiersz || (int) $wiersz['is_active'] !== 1) {
        wylogujUzytkownika();
        return null;
    }

    $role = db()->prepare(
        'SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :id'
    );
    $role->execute(['id' => $id]);
    $wiersz['roles'] = $role->fetchAll(PDO::FETCH_COLUMN);

    $uzytkownik = $wiersz;
    return $uzytkownik;
}

function zalogowany(): bool
{
    return aktualnyUzytkownik() !== null;
}

function maRole(string ...$role): bool
{
    $uzytkownik = aktualnyUzytkownik();
    return $uzytkownik !== null && array_intersect($role, $uzytkownik['roles']) !== [];
}

function wymagajLogowania(): void
{
    if (!zalogowany()) {
        komunikat('blad', 'Zaloguj się, aby przejść dalej.');
        przekieruj('logowanie.php');
    }
}

// Kontrola po stronie serwera - samo ukrycie linku nic nie daje.
function wymagajRoli(string ...$role): void
{
    wymagajLogowania();

    if (!maRole(...$role)) {
        bladStrony(403, 'Nie masz uprawnień do tej strony.');
    }
}

function panelUzytkownika(): string
{
    if (maRole(ROLA_ADMIN)) {
        return 'admin/index.php';
    }
    if (maRole(ROLA_INSPEKTOR)) {
        return 'inspektor/index.php';
    }
    return 'konto/profil.php';
}

function zalogujUzytkownika(int $id): void
{
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['user_id'] = $id;
    $_SESSION['ostatnia_aktywnosc'] = time();
    $_SESSION['odnowienie_id'] = time();
    csrfToken();
}

function wylogujUzytkownika(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function sprawdzWaznoscSesji(): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $teraz = time();

    if ($teraz - ($_SESSION['ostatnia_aktywnosc'] ?? 0) > SESJA_MAX_BEZCZYNNOSC) {
        wylogujUzytkownika();
        komunikat('blad', 'Sesja wygasła z powodu braku aktywności. Zaloguj się ponownie.');
        return;
    }

    if ($teraz - ($_SESSION['odnowienie_id'] ?? 0) > SESJA_ODNOWIENIE_ID) {
        session_regenerate_id(true);
        $_SESSION['odnowienie_id'] = $teraz;
    }

    $_SESSION['ostatnia_aktywnosc'] = $teraz;
}

// --- Walidacja danych konta (rejestracja i profil) ---

const HASLO_MIN = 8;
const HASLO_MAX = 72; // bcrypt bierze pod uwagę tylko 72 bajty

// Zwraca [poprawione dane, błędy]. Klucze błędów = nazwy pól formularza.
function walidujDaneOsobowe(array $dane): array
{
    $bledy = [];
    $wzorNazwy = "/^\p{L}[\p{L} '\-]*$/u";

    $imie = $dane['imie'] ?? '';
    if ($imie === '') {
        $bledy['imie'] = 'Podaj imię.';
    } elseif (mb_strlen($imie) < 2 || mb_strlen($imie) > 80 || !preg_match($wzorNazwy, $imie)) {
        $bledy['imie'] = 'Imię może zawierać tylko litery (2-80 znaków).';
    }

    $nazwisko = $dane['nazwisko'] ?? '';
    if ($nazwisko === '') {
        $bledy['nazwisko'] = 'Podaj nazwisko.';
    } elseif (mb_strlen($nazwisko) < 2 || mb_strlen($nazwisko) > 100 || !preg_match($wzorNazwy, $nazwisko)) {
        $bledy['nazwisko'] = 'Nazwisko może zawierać litery, spację i myślnik (2-100 znaków).';
    }

    $email = mb_strtolower($dane['email'] ?? '');
    if ($email === '') {
        $bledy['email'] = 'Podaj adres e-mail.';
    } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $bledy['email'] = 'Adres e-mail ma nieprawidłowy format.';
    }

    // telefon zapisujemy bez spacji i myślników, np. +48123456789
    $telefon = preg_replace('/[\s\-()]/', '', $dane['telefon'] ?? '');
    if ($telefon === '') {
        $bledy['telefon'] = 'Podaj numer telefonu.';
    } elseif (!preg_match('/^\+?[0-9]{9,15}$/', $telefon)) {
        $bledy['telefon'] = 'Numer telefonu powinien mieć 9-15 cyfr, np. 600 700 800 lub +48600700800.';
    }

    return [
        ['imie' => $imie, 'nazwisko' => $nazwisko, 'email' => $email, 'telefon' => $telefon],
        $bledy,
    ];
}

function walidujHaslo(string $haslo, string $powtorzenie, string $pole = 'haslo'): array
{
    $bledy = [];

    if ($haslo === '') {
        $bledy[$pole] = 'Podaj hasło.';
    } elseif (strlen($haslo) < HASLO_MIN || strlen($haslo) > HASLO_MAX) {
        $bledy[$pole] = 'Hasło musi mieć od ' . HASLO_MIN . ' do ' . HASLO_MAX . ' znaków.';
    } elseif (!preg_match('/\p{L}/u', $haslo) || !preg_match('/\d/', $haslo)) {
        $bledy[$pole] = 'Hasło musi zawierać co najmniej jedną literę i jedną cyfrę.';
    }

    if ($powtorzenie === '') {
        $bledy[$pole . '2'] = 'Powtórz hasło.';
    } elseif (!isset($bledy[$pole]) && !hash_equals($haslo, $powtorzenie)) {
        $bledy[$pole . '2'] = 'Hasła nie są takie same.';
    }

    return $bledy;
}

function emailZajety(string $email, ?int $pominId = null): bool
{
    $zapytanie = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $zapytanie->execute(['email' => $email, 'id' => $pominId ?? 0]);
    return $zapytanie->fetchColumn() !== false;
}
