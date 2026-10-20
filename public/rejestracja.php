<?php
require_once __DIR__ . '/../includes/init.php';
header('Cache-Control: no-store');

if (zalogowany()) {
    przekieruj(panelUzytkownika());
}

$dane = ['imie' => '', 'nazwisko' => '', 'email' => '', 'telefon' => ''];
$bledy = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        http_response_code(403);
        $bledy['formularz'] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        [$dane, $bledy] = walidujDaneOsobowe([
            'imie' => pole('imie'),
            'nazwisko' => pole('nazwisko'),
            'email' => pole('email'),
            'telefon' => pole('telefon'),
        ]);

        // hasła nie przycinamy - spacja na końcu też jest częścią hasła
        $haslo = is_string($_POST['haslo'] ?? null) ? $_POST['haslo'] : '';
        $haslo2 = is_string($_POST['haslo2'] ?? null) ? $_POST['haslo2'] : '';
        $bledy += walidujHaslo($haslo, $haslo2);

        if (!isset($bledy['haslo']) && $dane['email'] !== '' && mb_strtolower($haslo) === $dane['email']) {
            $bledy['haslo'] = 'Hasło nie może być takie samo jak adres e-mail.';
        }

        if (!isset($_POST['regulamin'])) {
            $bledy['regulamin'] = 'Musisz zaakceptować zasady korzystania z serwisu.';
        }

        try {
            if (!isset($bledy['email']) && emailZajety($dane['email'])) {
                $bledy['email'] = 'Konto z tym adresem e-mail już istnieje.';
            }

            if (!$bledy) {
                $pdo = db();
                $pdo->beginTransaction();

                $dodaj = $pdo->prepare(
                    'INSERT INTO users (first_name, last_name, email, phone, password_hash)
                     VALUES (:imie, :nazwisko, :email, :telefon, :hash)'
                );
                $dodaj->execute([
                    'imie' => $dane['imie'],
                    'nazwisko' => $dane['nazwisko'],
                    'email' => $dane['email'],
                    'telefon' => $dane['telefon'],
                    'hash' => password_hash($haslo, PASSWORD_DEFAULT),
                ]);
                $noweId = (int) $pdo->lastInsertId();

                // nowe konto zawsze dostaje tylko rolę obywatela
                $rola = $pdo->prepare(
                    'INSERT INTO user_roles (user_id, role_id) SELECT :id, id FROM roles WHERE code = :rola'
                );
                $rola->execute(['id' => $noweId, 'rola' => ROLA_OBYWATEL]);

                $pdo->commit();

                zalogujUzytkownika($noweId);
                komunikat('sukces', 'Konto zostało założone. Witaj w mSygnaliście!');
                przekieruj('konto/profil.php');
            }
        } catch (PDOException $ex) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // 23000 = naruszenie UNIQUE, np. dwa zgłoszenia formularza naraz
            if ($ex->getCode() === '23000') {
                $bledy['email'] = 'Konto z tym adresem e-mail już istnieje.';
            } else {
                error_log('Rejestracja: ' . $ex->getMessage());
                http_response_code(503);
                $bledy['formularz'] = 'Rejestracja jest chwilowo niedostępna. Spróbuj ponownie później.';
            }
        }
    }

    if ($bledy && !isset($bledy['formularz'])) {
        $bledy['formularz'] = 'Popraw zaznaczone pola i spróbuj ponownie.';
    }
}

$tytul = 'Rejestracja';
require __DIR__ . '/../includes/header.php';
?>
<div class="w-full max-w-lg mx-auto py-4">
    <a href="<?= e(url('index.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-6">
        <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Strona główna
    </a>

    <section class="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm" aria-labelledby="tytul">
        <h1 id="tytul" class="text-2xl font-extrabold text-gray-900 mb-2">Załóż konto</h1>
        <p class="text-sm text-mobiTextMuted mb-6">Konto pozwala rezerwować konsultacje i śledzić swoje wizyty. Zgłoszenia anonimowe nie wymagają konta.</p>

        <?php if (isset($bledy['formularz'])): ?>
            <p role="alert" class="komunikat komunikat-blad mb-5"><?= e($bledy['formularz']) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('rejestracja.php')) ?>" class="space-y-5">
            <?= csrfPole() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <?= poleFormularza('imie', 'Imię', $dane['imie'], $bledy, 'text', 'required maxlength="80" autocomplete="given-name"') ?>
                <?= poleFormularza('nazwisko', 'Nazwisko', $dane['nazwisko'], $bledy, 'text', 'required maxlength="100" autocomplete="family-name"') ?>
            </div>
            <?= poleFormularza('email', 'Adres e-mail', $dane['email'], $bledy, 'email', 'required maxlength="254" autocomplete="email"') ?>
            <?= poleFormularza('telefon', 'Telefon', $dane['telefon'], $bledy, 'tel', 'required maxlength="25" autocomplete="tel" placeholder="np. 600 700 800"') ?>
            <?= poleFormularza('haslo', 'Hasło', '', $bledy, 'password', 'required minlength="' . HASLO_MIN . '" maxlength="' . HASLO_MAX . '" autocomplete="new-password"') ?>
            <p class="text-xs text-mobiTextMuted -mt-3">Co najmniej <?= HASLO_MIN ?> znaków, w tym litera i cyfra.</p>
            <?= poleFormularza('haslo2', 'Powtórz hasło', '', $bledy, 'password', 'required maxlength="' . HASLO_MAX . '" autocomplete="new-password" data-powtorz="pole-haslo"') ?>

            <div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="regulamin" value="1" class="mt-1 accent-mobiRed" <?= isset($_POST['regulamin']) ? 'checked' : '' ?> required>
                    <span>Akceptuję zasady korzystania z serwisu i przetwarzanie moich danych w celu obsługi konsultacji.</span>
                </label>
                <?php if (isset($bledy['regulamin'])): ?>
                    <p class="blad-pola"><?= e($bledy['regulamin']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">
                Załóż konto
            </button>
        </form>

        <p class="text-sm text-center text-mobiTextMuted mt-6">
            Masz już konto? <a href="<?= e(url('logowanie.php')) ?>" class="text-mobiRed font-semibold">Zaloguj się</a>
        </p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
