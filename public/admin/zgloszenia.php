<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/zgloszenia.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

const ZGLOSZEN_NA_STRONE = 20;

$status = is_string($_GET['status'] ?? null) && isset(STATUSY_ZGLOSZEN[$_GET['status']]) ? $_GET['status'] : '';
$kategoria = filter_var($_GET['kategoria'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$bezPrzydzialu = ($_GET['bez_przydzialu'] ?? '') === '1';

$warunki = [];
$parametry = [];
if ($status !== '') {
    $warunki[] = 'r.status_code = :status';
    $parametry['status'] = $status;
}
if ($kategoria !== null) {
    $warunki[] = 'r.category_id = :kategoria';
    $parametry['kategoria'] = $kategoria;
}
if ($bezPrzydzialu) {
    $warunki[] = 'r.assigned_inspector_id IS NULL';
}
$gdzie = $warunki ? ' WHERE ' . implode(' AND ', $warunki) : '';

$ile = db()->prepare('SELECT COUNT(*) FROM reports r' . $gdzie);
$ile->execute($parametry);
$wszystkich = (int) $ile->fetchColumn();
$stron = max(1, (int) ceil($wszystkich / ZGLOSZEN_NA_STRONE));
$strona = min($stron, filter_var($_GET['strona'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1);

$lista = db()->prepare(
    ZAPYTANIE_LISTY_ZGLOSZEN . $gdzie . ' ORDER BY r.created_at DESC, r.id DESC LIMIT ' . ZGLOSZEN_NA_STRONE
    . ' OFFSET ' . (($strona - 1) * ZGLOSZEN_NA_STRONE)
);
$lista->execute($parametry);
$zgloszenia = $lista->fetchAll();

$kategorie = db()->query('SELECT id, name FROM report_categories ORDER BY name')->fetchAll();
$doPrzydzialu = (int) db()->query("SELECT COUNT(*) FROM reports WHERE assigned_inspector_id IS NULL AND status_code IN ('new', 'in_review')")->fetchColumn();

$filtry = array_filter(['status' => $status, 'kategoria' => $kategoria, 'bez_przydzialu' => $bezPrzydzialu ? '1' : '']);
$linkStrony = fn (int $s): string => url('admin/zgloszenia.php?' . http_build_query($filtry + ($s > 1 ? ['strona' => $s] : [])));

$tytul = 'Zgłoszenia';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Zgłoszenia</h1>
<p class="text-mobiTextMuted mb-6">Anonimowe zgłoszenia obywateli. Otwórz sprawę, aby przydzielić inspektora.</p>

<?= menuAdmina('zgloszenia.php') ?>

<?php if ($doPrzydzialu > 0): ?>
    <p class="komunikat komunikat-blad mb-4">
        Otwarte sprawy bez inspektora: <strong><?= $doPrzydzialu ?></strong> ·
        <a href="<?= e(url('admin/zgloszenia.php?bez_przydzialu=1')) ?>" class="font-semibold underline">pokaż</a>
    </p>
<?php endif; ?>

<form method="get" action="<?= e(url('admin/zgloszenia.php')) ?>" class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label for="status" class="block text-xs font-semibold mb-1">Status</label>
        <select id="status" name="status" class="pole">
            <option value="">Wszystkie</option>
            <?php foreach (STATUSY_ZGLOSZEN as $kod => $nazwa): ?>
                <option value="<?= e($kod) ?>" <?= $status === $kod ? 'selected' : '' ?>><?= e($nazwa) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="kategoria" class="block text-xs font-semibold mb-1">Rodzaj</label>
        <select id="kategoria" name="kategoria" class="pole">
            <option value="">Wszystkie</option>
            <?php foreach ($kategorie as $k): ?>
                <option value="<?= (int) $k['id'] ?>" <?= $kategoria === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <label class="flex items-center gap-2 text-sm py-3">
        <input type="checkbox" name="bez_przydzialu" value="1" class="accent-mobiRed" <?= $bezPrzydzialu ? 'checked' : '' ?>> Bez przydziału
    </label>
    <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">Filtruj</button>
</form>

<p class="text-sm text-mobiTextMuted mb-2">Znaleziono: <strong class="text-gray-900"><?= $wszystkich ?></strong></p>
<?php if (!$zgloszenia): ?>
    <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted">Brak zgłoszeń spełniających kryteria.</p>
<?php else: ?>
    <ul class="bg-white border border-gray-200 rounded-3xl shadow-sm divide-y divide-gray-100 overflow-hidden">
        <?php foreach ($zgloszenia as $z) { echo wierszZgloszenia($z, true); } ?>
    </ul>
<?php endif; ?>

<?php if ($stron > 1): ?>
    <nav class="flex justify-between items-center gap-3 mt-4 text-sm" aria-label="Strony">
        <?php if ($strona > 1): ?><a href="<?= e($linkStrony($strona - 1)) ?>" class="font-semibold text-mobiRed">← Poprzednia</a><?php else: ?><span></span><?php endif; ?>
        <span class="text-mobiTextMuted">Strona <?= $strona ?> z <?= $stron ?></span>
        <?php if ($strona < $stron): ?><a href="<?= e($linkStrony($strona + 1)) ?>" class="font-semibold text-mobiRed">Następna →</a><?php else: ?><span></span><?php endif; ?>
    </nav>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
