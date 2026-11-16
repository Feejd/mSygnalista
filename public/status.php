<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/zgloszenia.php';
header('Cache-Control: no-store');

const SPRAWDZEN_NA_10_MIN = 10;

$blad = '';
$zgloszenie = null;
$historia = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ostatnie próby w tej sesji - bez zapisywania IP zgłaszającego
    $proby = array_filter($_SESSION['sprawdzenia_statusu'] ?? [], fn ($t) => $t > time() - 600);

    if (!csrfPoprawny()) {
        $blad = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (count($proby) >= SPRAWDZEN_NA_10_MIN) {
        $blad = 'Zbyt wiele prób. Spróbuj ponownie za kilka minut.';
    } else {
        $proby[] = time();
        $zgloszenie = zgloszeniePoTokenie(pole('token'));

        if ($zgloszenie === null) {
            $blad = 'Nie znaleziono zgłoszenia o tym tokenie. Sprawdź, czy został wpisany dokładnie.';
        } else {
            // zgłaszający widzi tylko statusy i daty, bez wewnętrznych notatek inspektora
            $zapytanie = db()->prepare(
                'SELECT new_status_code, changed_at FROM report_status_history WHERE report_id = :id ORDER BY changed_at, id'
            );
            $zapytanie->execute(['id' => $zgloszenie['id']]);
            $historia = $zapytanie->fetchAll();
        }
    }
    $_SESSION['sprawdzenia_statusu'] = array_values($proby);
}

$kolory = [
    'new' => 'bg-gray-100 text-gray-700',
    'in_review' => 'bg-blue-50 text-blue-700',
    'resolved' => 'bg-emerald-50 text-emerald-700',
    'rejected' => 'bg-red-50 text-red-700',
];

$tytul = 'Status zgłoszenia';
require __DIR__ . '/../includes/header.php';
?>
<a href="<?= e(url('index.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-4">
    <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Strona główna
</a>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-1">Sprawdź status sprawy</h1>
<p class="text-mobiTextMuted mb-6">Wpisz token otrzymany po wysłaniu zgłoszenia.</p>

<section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm max-w-xl mb-4">
    <?php if ($blad !== ''): ?>
        <p role="alert" class="komunikat komunikat-blad mb-4"><?= e($blad) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('status.php')) ?>" class="flex flex-wrap gap-3 items-end">
        <?= csrfPole() ?>
        <div class="flex-1 min-w-[14rem]">
            <label for="token" class="block text-sm font-semibold mb-2">Token</label>
            <input type="text" id="token" name="token" required maxlength="64" autocomplete="off" spellcheck="false"
                   placeholder="MSY-XXXX-XXXX-XXXX-XXXX-XXXX" class="pole font-mono">
        </div>
        <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl">Sprawdź</button>
    </form>
</section>

<?php if ($zgloszenie !== null): ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm max-w-xl" aria-labelledby="sprawa-tytul">
        <div class="flex flex-wrap justify-between items-start gap-3 mb-3">
            <div>
                <p class="text-xs font-semibold text-mobiRed"><?= e($zgloszenie['kategoria']) ?></p>
                <h2 id="sprawa-tytul" class="text-lg font-bold text-gray-900"><?= e($zgloszenie['title']) ?></h2>
            </div>
            <span class="text-xs font-semibold px-2 py-1 rounded-lg <?= $kolory[$zgloszenie['status_code']] ?? '' ?>"><?= e(STATUSY_ZGLOSZEN[$zgloszenie['status_code']] ?? $zgloszenie['status_code']) ?></span>
        </div>

        <?php if ($zgloszenie['public_message']): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 text-sm mb-4">
                <p class="text-xs font-semibold text-mobiTextMuted mb-1">Informacja od inspektora</p>
                <?= nl2br(e($zgloszenie['public_message'])) ?>
            </div>
        <?php endif; ?>

        <h3 class="text-sm font-semibold mb-2">Przebieg sprawy</h3>
        <ol class="space-y-2 text-sm">
            <?php foreach ($historia as $h): ?>
                <li class="flex gap-3 border-l-2 border-gray-200 pl-3">
                    <span class="text-mobiTextMuted w-32 shrink-0"><?= e(date('d.m.Y H:i', strtotime($h['changed_at']))) ?></span>
                    <span><?= e(STATUSY_ZGLOSZEN[$h['new_status_code']] ?? $h['new_status_code']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
