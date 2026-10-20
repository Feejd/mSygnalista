<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

// Profil ma każdy zalogowany użytkownik, niezależnie od roli.
wymagajLogowania();

$uzytkownik = aktualnyUzytkownik();
$id = (int) $uzytkownik['id'];

$dane = [
    'imie' => $uzytkownik['first_name'],
    'nazwisko' => $uzytkownik['last_name'],
    'email' => $uzytkownik['email'],
    'telefon' => $uzytkownik['phone'],
];
$bledyDanych = [];
$bledyHasla = [];

function pobierzHash(int $id): string
{
    $zapytanie = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
    $zapytanie->execute(['id' => $id]);
    return (string) $zapytanie->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akcja = pole('akcja');

    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    try {
        if ($akcja === 'dane') {
            [$dane, $bledyDanych] = walidujDaneOsobowe([
                'imie' => pole('imie'),
                'nazwisko' => pole('nazwisko'),
                'email' => pole('email'),
                'telefon' => pole('telefon'),
            ]);

            $zmianaEmaila = $dane['email'] !== $uzytkownik['email'];

            if (!isset($bledyDanych['email']) && $zmianaEmaila && emailZajety($dane['email'], $id)) {
                $bledyDanych['email'] = 'Ten adres e-mail jest już używany przez inne konto.';
            }

            // zmiana e-maila = zmiana loginu, więc prosimy o hasło
            if ($zmianaEmaila && !$bledyDanych) {
                $haslo = is_string($_POST['haslo_potwierdzenie'] ?? null) ? $_POST['haslo_potwierdzenie'] : '';
                if (!password_verify($haslo, pobierzHash($id))) {
                    $bledyDanych['haslo_potwierdzenie'] = 'Aby zmienić adres e-mail, podaj poprawne obecne hasło.';
                }
            }

            if (!$bledyDanych) {
                db()->prepare(
                    'UPDATE users SET first_name = :imie, last_name = :nazwisko, email = :email, phone = :telefon
                     WHERE id = :id'
                )->execute($dane + ['id' => $id]);

                komunikat('sukces', 'Dane konta zostały zapisane.');
                przekieruj('konto/profil.php');
            }
        } elseif ($akcja === 'haslo') {
            $obecne = is_string($_POST['haslo_obecne'] ?? null) ? $_POST['haslo_obecne'] : '';
            $nowe = is_string($_POST['haslo_nowe'] ?? null) ? $_POST['haslo_nowe'] : '';
            $nowe2 = is_string($_POST['haslo_nowe2'] ?? null) ? $_POST['haslo_nowe2'] : '';

            $hash = pobierzHash($id);

            if ($obecne === '' || !password_verify($obecne, $hash)) {
                $bledyHasla['haslo_obecne'] = 'Obecne hasło jest nieprawidłowe.';
            }

            $bledyHasla += walidujHaslo($nowe, $nowe2, 'haslo_nowe');

            if (!$bledyHasla && hash_equals($obecne, $nowe)) {
                $bledyHasla['haslo_nowe'] = 'Nowe hasło musi być inne niż obecne.';
            }

            if (!$bledyHasla) {
                db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                    ->execute(['hash' => password_hash($nowe, PASSWORD_DEFAULT), 'id' => $id]);

                session_regenerate_id(true);
                komunikat('sukces', 'Hasło zostało zmienione.');
                przekieruj('konto/profil.php');
            }
        } else {
            bladStrony(400, 'Nieznana operacja.');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            $bledyDanych['email'] = 'Ten adres e-mail jest już używany przez inne konto.';
        } else {
            error_log('Profil: ' . $ex->getMessage());
            bladStrony(503, 'Nie udało się zapisać zmian. Spróbuj ponownie później.');
        }
    }
}

$nazwyRol = [ROLA_OBYWATEL => 'Obywatel', ROLA_INSPEKTOR => 'Inspektor', ROLA_ADMIN => 'Administrator'];

$tytul = 'Moje konto';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Moje konto</h1>
<p class="text-mobiTextMuted mb-6">Zarządzaj swoimi danymi i hasłem.</p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm md:col-span-1">
        <div class="w-12 h-12 rounded-2xl bg-red-50 text-mobiRed flex items-center justify-center mb-4">
            <i aria-hidden="true" class="fa-solid fa-user text-xl"></i>
        </div>
        <h2 class="text-lg font-bold text-gray-900"><?= e($uzytkownik['first_name'] . ' ' . $uzytkownik['last_name']) ?></h2>
        <p class="text-sm text-mobiTextMuted break-all mb-4"><?= e($uzytkownik['email']) ?></p>
        <dl class="text-sm space-y-2 border-t border-gray-100 pt-4">
            <div class="flex justify-between gap-2">
                <dt class="text-mobiTextMuted">Rola</dt>
                <dd class="font-semibold text-right">
                    <?= e(implode(', ', array_map(fn ($r) => $nazwyRol[$r] ?? $r, $uzytkownik['roles']))) ?>
                </dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-mobiTextMuted">Konto od</dt>
                <dd class="font-semibold"><?= e(date('d.m.Y', strtotime($uzytkownik['created_at']))) ?></dd>
            </div>
        </dl>
    </section>

    <section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm md:col-span-2" aria-labelledby="dane-tytul">
        <h2 id="dane-tytul" class="text-lg font-bold text-gray-900 mb-4">Dane konta</h2>

        <?php if ($bledyDanych): ?>
            <p role="alert" class="komunikat komunikat-blad mb-5">Popraw zaznaczone pola.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('konto/profil.php')) ?>" class="space-y-5">
            <?= csrfPole() ?>
            <input type="hidden" name="akcja" value="dane">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <?= poleFormularza('imie', 'Imię', $dane['imie'], $bledyDanych, 'text', 'required maxlength="80" autocomplete="given-name"') ?>
                <?= poleFormularza('nazwisko', 'Nazwisko', $dane['nazwisko'], $bledyDanych, 'text', 'required maxlength="100" autocomplete="family-name"') ?>
            </div>
            <?= poleFormularza('email', 'Adres e-mail', $dane['email'], $bledyDanych, 'email', 'required maxlength="254" autocomplete="email"') ?>
            <?= poleFormularza('telefon', 'Telefon', $dane['telefon'], $bledyDanych, 'tel', 'required maxlength="25" autocomplete="tel"') ?>
            <?= poleFormularza('haslo_potwierdzenie', 'Obecne hasło (tylko przy zmianie e-maila)', '', $bledyDanych, 'password', 'maxlength="4096" autocomplete="current-password"') ?>
            <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">
                Zapisz dane
            </button>
        </form>
    </section>
</div>

<section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm md:ml-auto md:w-2/3" aria-labelledby="haslo-tytul">
    <h2 id="haslo-tytul" class="text-lg font-bold text-gray-900 mb-4">Zmiana hasła</h2>

    <?php if ($bledyHasla): ?>
        <p role="alert" class="komunikat komunikat-blad mb-5">Nie udało się zmienić hasła. Sprawdź zaznaczone pola.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('konto/profil.php')) ?>" class="space-y-5">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="haslo">
        <?= poleFormularza('haslo_obecne', 'Obecne hasło', '', $bledyHasla, 'password', 'required maxlength="4096" autocomplete="current-password"') ?>
        <?= poleFormularza('haslo_nowe', 'Nowe hasło', '', $bledyHasla, 'password', 'required minlength="' . HASLO_MIN . '" maxlength="' . HASLO_MAX . '" autocomplete="new-password"') ?>
        <?= poleFormularza('haslo_nowe2', 'Powtórz nowe hasło', '', $bledyHasla, 'password', 'required maxlength="' . HASLO_MAX . '" autocomplete="new-password" data-powtorz="pole-haslo_nowe"') ?>
        <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white font-semibold px-5 py-3 rounded-xl transition">
            Zmień hasło
        </button>
    </form>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
