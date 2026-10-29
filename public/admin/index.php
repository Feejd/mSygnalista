<?php
require_once __DIR__ . '/../../includes/init.php';

// Tylko administrator. Obywatel albo inspektor wpisujący ten adres ręcznie dostaje 403.
wymagajRoli(ROLA_ADMIN);

$uzytkownik = aktualnyUzytkownik();

$liczby = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
            WHERE r.code = 'citizen') AS obywatele,
        (SELECT COUNT(*) FROM inspectors WHERE is_active = 1) AS inspektorzy,
        (SELECT COUNT(*) FROM services WHERE is_active = 1) AS uslugi,
        (SELECT COUNT(*) FROM reservations) AS rezerwacje"
)->fetch();

$tytul = 'Panel administratora';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Panel administratora</h1>
<p class="text-mobiTextMuted mb-6">Zalogowano jako <?= e($uzytkownik['first_name'] . ' ' . $uzytkownik['last_name']) ?>.</p>

<?= menuAdmina('index.php') ?>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['Obywatele', $liczby['obywatele'], 'fa-users'],
        ['Inspektorzy', $liczby['inspektorzy'], 'fa-user-tie'],
        ['Usługi', $liczby['uslugi'], 'fa-list-check'],
        ['Rezerwacje', $liczby['rezerwacje'], 'fa-calendar-days'],
    ] as [$nazwa, $liczba, $ikona]): ?>
        <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
            <i aria-hidden="true" class="fa-solid <?= e($ikona) ?> text-mobiRed mb-3"></i>
            <p class="text-2xl font-extrabold"><?= (int) $liczba ?></p>
            <p class="text-xs text-mobiTextMuted"><?= e($nazwa) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm text-sm text-mobiTextMuted">
    Kategorie są już w zakładce powyżej. Zarządzanie usługami, inspektorami, użytkownikami i rezerwacjami jest w przygotowaniu.
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
