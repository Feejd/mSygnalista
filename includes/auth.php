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
