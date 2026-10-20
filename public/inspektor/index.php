<?php
require_once __DIR__ . '/../../includes/init.php';

// Panel inspektora - administrator też może tu wejść.
wymagajRoli(ROLA_INSPEKTOR, ROLA_ADMIN);

$uzytkownik = aktualnyUzytkownik();

$zapytanie = db()->prepare('SELECT institution, description FROM inspectors WHERE user_id = :id AND is_active = 1');
$zapytanie->execute(['id' => $uzytkownik['id']]);
$inspektor = $zapytanie->fetch();

$tytul = 'Panel inspektora';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Panel inspektora</h1>
<p class="text-mobiTextMuted mb-6">Dzień dobry, <?= e($uzytkownik['first_name']) ?>.</p>

<section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4">
    <?php if ($inspektor): ?>
        <h2 class="text-lg font-bold text-gray-900 mb-1"><?= e($inspektor['institution']) ?></h2>
        <p class="text-sm text-mobiTextMuted"><?= e($inspektor['description']) ?></p>
    <?php else: ?>
        <p class="text-sm text-mobiTextMuted">To konto nie ma przypisanego profilu inspektora.</p>
    <?php endif; ?>
</section>

<div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm text-sm text-mobiTextMuted">
    Lista wizyt i przypisanych zgłoszeń pojawi się w kolejnym etapie projektu.
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
