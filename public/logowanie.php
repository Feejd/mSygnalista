<?php
require_once __DIR__ . '/../includes/init.php';
header('Cache-Control: no-store');

// Obywatel, który kliknął "Umów wizytę" przed zalogowaniem, wraca do wybranej usługi.
function celPoLogowaniu(): string
{
    $id = $_SESSION['wybrana_usluga'] ?? null;
    unset($_SESSION['wybrana_usluga']);

    if (maRole(ROLA_OBYWATEL) && is_int($id) && in_array($id, [1, 2, 3, 4, 5], true)) {
        return 'uslugi.php?umow=' . $id;
    }
    return panelUzytkownika();
}

if (zalogowany()) {
    przekieruj(panelUzytkownika());
}

$blad = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(pole('email'));
    $haslo = is_string($_POST['haslo'] ?? null) ? $_POST['haslo'] : '';

    if (!csrfPoprawny()) {
        http_response_code(403);
        $blad = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $haslo === '') {
        $blad = 'Podaj poprawny adres e-mail i hasło.';
    } elseif (strlen($haslo) > 4096) {
        $blad = 'Nieprawidłowy e-mail lub hasło.';
    } else {
        try {
            if (zaDuzoProbLogowania($email)) {
                http_response_code(429);
                $blad = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . LOGOWANIE_BLOKADA_MIN . ' minut.';
            } else {
                $zapytanie = db()->prepare(
                    'SELECT id, password_hash, is_active FROM users WHERE email = :email LIMIT 1'
                );
                $zapytanie->execute(['email' => $email]);
                $uzytkownik = $zapytanie->fetch();

                // Ten sam komunikat dla błędnego hasła, braku konta i konta nieaktywnego.
                // Sztuczny hash sprawia, że odpowiedź trwa tyle samo, gdy konta nie ma.
                $hash = $uzytkownik['password_hash'] ?? '$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO';
                $poprawneHaslo = password_verify($haslo, $hash);

                if (!$uzytkownik || !$poprawneHaslo || (int) $uzytkownik['is_active'] !== 1) {
                    zapiszProbeLogowania($email, false);
                    $blad = 'Nieprawidłowy e-mail lub hasło.';
                } else {
                    zapiszProbeLogowania($email, true);

                    // przy okazji logowania podbijamy koszt hasha, jeśli PHP ma nowsze ustawienia
                    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                        db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                            ->execute(['hash' => password_hash($haslo, PASSWORD_DEFAULT), 'id' => $uzytkownik['id']]);
                    }

                    $wybranaUsluga = $_SESSION['wybrana_usluga'] ?? null;
                    zalogujUzytkownika((int) $uzytkownik['id']);
                    $_SESSION['wybrana_usluga'] = $wybranaUsluga;

                    przekieruj(celPoLogowaniu());
                }
            }
        } catch (PDOException $ex) {
            error_log('Logowanie: ' . $ex->getMessage());
            http_response_code(503);
            $blad = 'Logowanie jest chwilowo niedostępne. Spróbuj ponownie później.';
        }
    }
}

$tytul = 'Logowanie';
require __DIR__ . '/../includes/header.php';
?>
<div class="w-full max-w-md mx-auto py-4">
    <a href="<?= e(url('index.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-6">
        <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Strona główna
    </a>
    <section class="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm" aria-labelledby="tytul">
        <h1 id="tytul" class="text-2xl font-extrabold text-gray-900 mb-2">Zaloguj się</h1>
        <p class="text-sm text-mobiTextMuted mb-6">Wprowadź adres e-mail i hasło do swojego konta.</p>

        <?php if ($blad !== ''): ?>
            <p role="alert" class="komunikat komunikat-blad mb-5"><?= e($blad) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('logowanie.php')) ?>" class="space-y-5">
            <?= csrfPole() ?>
            <?= poleFormularza('email', 'Adres e-mail', $email, [], 'email', 'required maxlength="254" autocomplete="username"') ?>
            <?= poleFormularza('haslo', 'Hasło', '', [], 'password', 'required maxlength="4096" autocomplete="current-password"') ?>
            <button type="submit" class="w-full bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">
                Zaloguj się
            </button>
        </form>

        <p class="text-sm text-center text-mobiTextMuted mt-6">
            Nie masz konta? <a href="<?= e(url('rejestracja.php')) ?>" class="text-mobiRed font-semibold">Zarejestruj się</a>
        </p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
