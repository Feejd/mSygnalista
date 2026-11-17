<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/zgloszenia.php';
header('Cache-Control: no-store');

// Inspektor widzi tylko sprawy przydzielone jemu, administrator wszystkie.
wymagajRoli(ROLA_INSPEKTOR, ROLA_ADMIN);

$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$z = zgloszenieDlaPracownika($id);
if ($z === null) {
    bladStrony(404, 'Nie znaleziono zgłoszenia.');
}
$jestAdminem = maRole(ROLA_ADMIN);
$adres = 'inspektor/zgloszenie.php?id=' . $z['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }
    $akcja = pole('akcja');

    if ($akcja === 'status') {
        $nowy = pole('status');
        $wiadomosc = mb_substr(pole('wiadomosc'), 0, 1000);
        $notatka = mb_substr(pole('notatka'), 0, 255);

        if (in_array($nowy, ['resolved', 'rejected'], true) && $wiadomosc === '' && !$z['public_message']) {
            komunikat('blad', 'Przy zamknięciu sprawy napisz krótką informację dla zgłaszającego.');
        } else {
            $blad = zmienStatusZgloszenia($z['id'], $nowy, $notatka !== '' ? $notatka : 'Zmiana statusu', $wiadomosc);
            if ($blad === null) {
                zapiszAudyt('report.status_change', 'reports', (int) $z['id'],
                    'Zgłoszenie #' . $z['id'] . ' -> ' . STATUSY_ZGLOSZEN[$nowy] . ($notatka !== '' ? ' (' . $notatka . ')' : ''));
            }
            komunikat($blad === null ? 'sukces' : 'blad', $blad ?? 'Status sprawy: ' . STATUSY_ZGLOSZEN[$nowy] . '.');
        }
    } elseif ($akcja === 'przydziel' && $jestAdminem) {
        $inspektor = filter_var(pole('inspektor'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        if ($inspektor !== null) {
            $istnieje = db()->prepare('SELECT 1 FROM inspectors WHERE id = :id AND is_active = 1');
            $istnieje->execute(['id' => $inspektor]);
            if (!$istnieje->fetchColumn()) {
                bladStrony(400, 'Nieprawidłowy inspektor.');
            }
        }

        db()->prepare('UPDATE reports SET assigned_inspector_id = :inspektor WHERE id = :id')
            ->execute(['inspektor' => $inspektor, 'id' => $z['id']]);
        zapiszAudyt('report.assign', 'reports', (int) $z['id'],
            'Zgłoszenie #' . $z['id'] . ($inspektor ? ' przydzielone inspektorowi #' . $inspektor : ' bez przydziału'));
        komunikat('sukces', $inspektor ? 'Przydzielono inspektora.' : 'Usunięto przydział.');
    } else {
        bladStrony(400, 'Nieznana operacja.');
    }
    przekieruj($adres);
}

$zalaczniki = db()->prepare('SELECT id, original_name, mime_type, size_bytes FROM report_attachments WHERE report_id = :id ORDER BY id');
$zalaczniki->execute(['id' => $z['id']]);
$zalaczniki = $zalaczniki->fetchAll();

$historia = db()->prepare(
    'SELECT h.old_status_code, h.new_status_code, h.reason, h.changed_at, u.first_name, u.last_name
     FROM report_status_history h LEFT JOIN users u ON u.id = h.changed_by
     WHERE h.report_id = :id ORDER BY h.changed_at, h.id'
);
$historia->execute(['id' => $z['id']]);
$historia = $historia->fetchAll();

$inspektorzy = $jestAdminem ? db()->query(
    'SELECT i.id, u.first_name, u.last_name, i.institution FROM inspectors i JOIN users u ON u.id = i.user_id
     WHERE i.is_active = 1 ORDER BY u.last_name'
)->fetchAll() : [];

$mozliwe = PRZEJSCIA_ZGLOSZEN[$z['status_code']];
$napisy = ['in_review' => 'Rozpocznij analizę', 'resolved' => 'Zakończ - rozpatrzone', 'rejected' => 'Odrzuć'];

$tytul = 'Zgłoszenie #' . $z['id'];
require __DIR__ . '/../../includes/header.php';
?>
<a href="<?= e(url($jestAdminem ? 'admin/zgloszenia.php' : 'inspektor/zgloszenia.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-4">
    <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Zgłoszenia
</a>
<div class="flex flex-wrap items-center gap-3 mb-1">
    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900"><?= e($z['title']) ?></h1>
    <?= znaczekZgloszenia($z['status_code']) ?>
</div>
<p class="text-mobiTextMuted mb-6">#<?= (int) $z['id'] ?> · <?= e($z['kategoria']) ?> · wpłynęło <?= e(date('d.m.Y H:i', strtotime($z['created_at']))) ?></p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm md:col-span-2">
        <h2 class="font-bold text-gray-900 mb-3">Opis</h2>
        <p class="text-sm whitespace-pre-line break-words"><?= e($z['description']) ?></p>
        <dl class="text-sm mt-4 space-y-1">
            <div><dt class="inline text-mobiTextMuted">Kiedy:</dt> <dd class="inline"><?= $z['incident_at'] ? e(date('d.m.Y H:i', strtotime($z['incident_at']))) : 'nie podano' ?></dd></div>
            <div><dt class="inline text-mobiTextMuted">Gdzie:</dt> <dd class="inline"><?= $z['incident_location'] ? e($z['incident_location']) : 'nie podano' ?></dd></div>
        </dl>
    </section>

    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
        <h2 class="font-bold text-gray-900 mb-3">Załączniki</h2>
        <?php if (!$zalaczniki): ?>
            <p class="text-sm text-mobiTextMuted">Brak.</p>
        <?php endif; ?>
        <ul class="space-y-2 text-sm">
            <?php foreach ($zalaczniki as $p): ?>
                <li>
                    <a href="<?= e(url('pobierz-zalacznik.php?id=' . $p['id'])) ?>" class="text-mobiRed font-semibold break-all">
                        <i aria-hidden="true" class="fa-solid fa-paperclip mr-1"></i><?= e($p['original_name']) ?>
                    </a>
                    <span class="block text-xs text-mobiTextMuted"><?= e(ZALACZNIKI_TYPY[$p['mime_type']] ?? '') ?>, <?= e(number_format($p['size_bytes'] / 1024, 0, ',', ' ')) ?> KB</span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="text-xs text-mobiTextMuted mt-3">Pliki od anonimowych osób otwieraj ostrożnie.</p>
    </section>
</div>

<?php if ($jestAdminem): ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4" aria-labelledby="przydzial-tytul">
        <h2 id="przydzial-tytul" class="font-bold text-gray-900 mb-3">Przydział</h2>
        <form method="post" action="<?= e(url($adres)) ?>" class="flex flex-wrap gap-3 items-end">
            <?= csrfPole() ?>
            <input type="hidden" name="akcja" value="przydziel">
            <div class="flex-1 min-w-[12rem]">
                <label for="inspektor" class="block text-xs font-semibold mb-1">Inspektor prowadzący</label>
                <select id="inspektor" name="inspektor" class="pole">
                    <option value="">- nikt -</option>
                    <?php foreach ($inspektorzy as $i): ?>
                        <option value="<?= (int) $i['id'] ?>" <?= (int) $z['assigned_inspector_id'] === (int) $i['id'] ? 'selected' : '' ?>>
                            <?= e($i['first_name'] . ' ' . $i['last_name'] . ' - ' . $i['institution']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">Zapisz</button>
        </form>
    </section>
<?php else: ?>
    <p class="text-sm text-mobiTextMuted mb-4">Sprawa przydzielona Tobie.</p>
<?php endif; ?>

<?php if ($mozliwe): ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4" aria-labelledby="status-tytul">
        <h2 id="status-tytul" class="font-bold text-gray-900 mb-3">Obsługa sprawy</h2>
        <form method="post" action="<?= e(url($adres)) ?>" class="space-y-4">
            <?= csrfPole() ?>
            <input type="hidden" name="akcja" value="status">
            <div>
                <label for="wiadomosc" class="block text-sm font-semibold mb-1">Informacja dla zgłaszającego</label>
                <p class="text-xs text-mobiTextMuted mb-2">Zgłaszający zobaczy ją po wpisaniu tokenu. Puste pole zostawia poprzednią informację.</p>
                <textarea id="wiadomosc" name="wiadomosc" rows="3" maxlength="1000" class="pole"><?= e($z['public_message'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="notatka" class="block text-sm font-semibold mb-1">Notatka wewnętrzna (opcjonalnie)</label>
                <input type="text" id="notatka" name="notatka" maxlength="255" class="pole" placeholder="widoczna tylko w historii sprawy">
            </div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($mozliwe as $kod): ?>
                    <button type="submit" name="status" value="<?= e($kod) ?>"
                        class="text-sm font-semibold px-4 py-2 rounded-xl border transition <?= $kod === 'rejected' ? 'border-red-200 text-red-700 hover:bg-red-50' : 'bg-gray-900 text-white border-gray-900 hover:bg-gray-700' ?>">
                        <?= e($napisy[$kod]) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </form>
    </section>
<?php elseif ($z['public_message']): ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4">
        <h2 class="font-bold text-gray-900 mb-2">Informacja dla zgłaszającego</h2>
        <p class="text-sm"><?= nl2br(e($z['public_message'])) ?></p>
    </section>
<?php endif; ?>

<section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm" aria-labelledby="historia-tytul">
    <h2 id="historia-tytul" class="font-bold text-gray-900 mb-3">Historia</h2>
    <ol class="space-y-3 text-sm">
        <?php foreach ($historia as $h): ?>
            <li class="border-l-2 border-gray-200 pl-3">
                <span class="text-mobiTextMuted"><?= e(date('d.m.Y H:i', strtotime($h['changed_at']))) ?></span>
                <span class="block mt-1"><?= $h['old_status_code'] ? znaczekZgloszenia($h['old_status_code']) . ' → ' : '' ?><?= znaczekZgloszenia($h['new_status_code']) ?></span>
                <span class="block text-xs text-mobiTextMuted mt-1"><?= e($h['reason']) ?><?= $h['first_name'] ? ' · ' . e($h['first_name'] . ' ' . $h['last_name']) : '' ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
