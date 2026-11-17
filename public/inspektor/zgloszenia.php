<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/rezerwacje.php';
require_once __DIR__ . '/../../includes/zgloszenia.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_INSPEKTOR);

$profil = profilInspektora((int) aktualnyUzytkownik()['id']);
if ($profil === null || (int) $profil['is_active'] !== 1) {
    bladStrony(403, 'To konto nie ma aktywnego profilu inspektora.');
}

// otwarte = nowe i w trakcie analizy, zamknięte = rozpatrzone i odrzucone
$widok = ($_GET['widok'] ?? '') === 'zamkniete' ? 'zamkniete' : 'otwarte';
$statusy = $widok === 'otwarte' ? "'new', 'in_review'" : "'resolved', 'rejected'";

$zapytanie = db()->prepare(
    ZAPYTANIE_LISTY_ZGLOSZEN . " WHERE r.assigned_inspector_id = :id AND r.status_code IN ($statusy)
     ORDER BY r.status_code = 'new' DESC, r.created_at" . ($widok === 'zamkniete' ? ' DESC LIMIT 100' : '')
);
$zapytanie->execute(['id' => $profil['id']]);
$zgloszenia = $zapytanie->fetchAll();

$tytul = 'Moje zgłoszenia';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Zgłoszenia</h1>
<p class="text-mobiTextMuted mb-6">Sprawy przydzielone Tobie przez administratora.</p>

<nav class="flex flex-wrap gap-2 mb-4" aria-label="Widok zgłoszeń">
    <?php foreach (['otwarte' => 'Do obsługi', 'zamkniete' => 'Zamknięte'] as $kod => $nazwa): ?>
        <a href="<?= e(url('inspektor/zgloszenia.php?widok=' . $kod)) ?>" <?= $kod === $widok ? 'aria-current="page"' : '' ?>
           class="text-xs font-semibold px-4 py-2 rounded-xl border transition <?= $kod === $widok ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300' ?>"><?= e($nazwa) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$zgloszenia): ?>
    <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted">Brak zgłoszeń.</p>
<?php else: ?>
    <ul class="bg-white border border-gray-200 rounded-3xl shadow-sm divide-y divide-gray-100 overflow-hidden">
        <?php foreach ($zgloszenia as $z) { echo wierszZgloszenia($z, false); } ?>
    </ul>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
