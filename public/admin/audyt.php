<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

const WPISOW_NA_STRONE = 20;

// grupy akcji = pierwszy człon action_code, np. service.create -> service
const GRUPY_AKCJI = [
    'category' => 'Kategorie',
    'service' => 'Usługi',
    'inspector' => 'Inspektorzy',
    'schedule' => 'Grafik',
    'absence' => 'Nieobecności',
    'reservation' => 'Rezerwacje',
    'report' => 'Zgłoszenia',
];

$grupa = is_string($_GET['grupa'] ?? null) && array_key_exists($_GET['grupa'], GRUPY_AKCJI) ? $_GET['grupa'] : '';
$gdzie = '';
$parametry = [];

if ($grupa !== '') {
    $gdzie = 'WHERE a.action_code LIKE :grupa';
    $parametry['grupa'] = $grupa . '.%';
}

$ile = db()->prepare("SELECT COUNT(*) FROM audit_logs a $gdzie");
$ile->execute($parametry);
$wszystkich = (int) $ile->fetchColumn();

$stron = max(1, (int) ceil($wszystkich / WPISOW_NA_STRONE));
$strona = filter_var($_GET['strona'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$strona = min($strona, $stron);

$wpisy = db()->prepare(
    "SELECT a.action_code, a.description, a.created_at, u.first_name, u.last_name
     FROM audit_logs a JOIN users u ON u.id = a.actor_id
     $gdzie
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT " . WPISOW_NA_STRONE . ' OFFSET ' . (($strona - 1) * WPISOW_NA_STRONE)
);
$wpisy->execute($parametry);
$wpisy = $wpisy->fetchAll();

function linkAudytu(string $grupa, int $strona): string
{
    return url('admin/audyt.php?' . http_build_query(array_filter(['grupa' => $grupa, 'strona' => $strona > 1 ? $strona : ''])));
}

$tytul = 'Log operacji';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Log operacji</h1>
<p class="text-mobiTextMuted mb-6">Kto i kiedy zmienił dane. Wpisów nie można edytować ani usuwać.</p>

<?= menuAdmina('audyt.php') ?>

<nav class="flex flex-wrap gap-2 mb-4" aria-label="Rodzaj operacji">
    <?php foreach (['' => 'Wszystko'] + GRUPY_AKCJI as $kod => $nazwa): ?>
        <a href="<?= e(linkAudytu($kod, 1)) ?>" class="text-xs font-semibold px-3 py-2 rounded-lg border transition <?= $grupa === $kod ? 'bg-mobiRed text-white border-mobiRed' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300' ?>"><?= e($nazwa) ?></a>
    <?php endforeach; ?>
</nav>

<section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-label="Wpisy logu">
    <?php if (!$wpisy): ?>
        <p class="p-5 text-sm text-mobiTextMuted">Brak wpisów.</p>
    <?php endif; ?>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($wpisy as $w): ?>
            <li class="px-5 py-3 text-sm">
                <p class="text-gray-900 break-words"><?= e($w['description']) ?></p>
                <p class="text-xs text-mobiTextMuted">
                    <?= e(date('d.m.Y H:i', strtotime($w['created_at']))) ?> · <?= e($w['first_name'] . ' ' . $w['last_name']) ?> · <code><?= e($w['action_code']) ?></code>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($stron > 1): ?>
        <nav class="flex flex-wrap items-center justify-between gap-3 p-5 border-t border-gray-100 text-sm" aria-label="Strony">
            <?php if ($strona > 1): ?>
                <a href="<?= e(linkAudytu($grupa, $strona - 1)) ?>" class="font-semibold text-mobiRed">← Nowsze</a>
            <?php else: ?><span></span><?php endif; ?>
            <span class="text-mobiTextMuted">Strona <?= $strona ?> z <?= $stron ?></span>
            <?php if ($strona < $stron): ?>
                <a href="<?= e(linkAudytu($grupa, $strona + 1)) ?>" class="font-semibold text-mobiRed">Starsze →</a>
            <?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
