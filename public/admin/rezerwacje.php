<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

const NA_STRONE = 15;

// --- zmiana statusu (POST z widoku szczegółów) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $nowy = pole('status');
    $powod = pole('powod');

    if (!$id || !array_key_exists($nowy, STATUSY_REZERWACJI)) {
        bladStrony(400, 'Nieprawidłowe dane formularza.');
    }
    if (mb_strlen($powod) > 255) {
        $powod = mb_substr($powod, 0, 255);
    }
    if ($powod === '') {
        $powod = 'Zmiana statusu przez administratora';
    }

    $blad = zmienStatusRezerwacji($id, $nowy, $powod);

    if ($blad !== null) {
        komunikat('blad', $blad);
    } else {
        zapiszAudyt('reservation.status_change', 'reservations', $id,
            'Status rezerwacji #' . $id . ' -> ' . STATUSY_REZERWACJI[$nowy] . ' (' . $powod . ')');
        komunikat('sukces', 'Zmieniono status rezerwacji na „' . STATUSY_REZERWACJI[$nowy] . '”.');
    }
    przekieruj('admin/rezerwacje.php?id=' . $id);
}

// --- widok szczegółów: rezerwacje.php?id=5 ---
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $zapytanie = db()->prepare(
        'SELECT r.*, s.name AS usluga, s.duration_minutes,
                k.first_name AS k_imie, k.last_name AS k_nazwisko, k.email AS k_email, k.phone AS k_telefon,
                iu.first_name AS i_imie, iu.last_name AS i_nazwisko, i.institution
         FROM reservations r
         JOIN services s ON s.id = r.service_id
         JOIN users k ON k.id = r.client_id
         JOIN inspectors i ON i.id = r.inspector_id
         JOIN users iu ON iu.id = i.user_id
         WHERE r.id = :id'
    );
    $zapytanie->execute(['id' => $id ?: 0]);
    $r = $zapytanie->fetch();

    if (!$r) {
        bladStrony(404, 'Nie znaleziono rezerwacji.');
    }

    $historia = db()->prepare(
        'SELECT h.old_status_code, h.new_status_code, h.reason, h.changed_at, u.first_name, u.last_name
         FROM reservation_status_history h
         LEFT JOIN users u ON u.id = h.changed_by
         WHERE h.reservation_id = :id
         ORDER BY h.changed_at, h.id'
    );
    $historia->execute(['id' => $r['id']]);
    $historia = $historia->fetchAll();

    $tytul = 'Rezerwacja #' . $r['id'];
    require __DIR__ . '/../../includes/header.php';
    ?>
    <a href="<?= e(url('admin/rezerwacje.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-4">
        <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Wszystkie rezerwacje
    </a>
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Rezerwacja #<?= (int) $r['id'] ?></h1>
        <?= znaczekStatusu($r['status_code']) ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
            <h2 class="font-bold text-gray-900 mb-3">Wizyta</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Usługa</dt><dd class="font-semibold text-right"><?= e($r['usluga']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Termin</dt><dd class="font-semibold text-right"><?= e(date('d.m.Y', strtotime($r['starts_at']))) ?>, <?= e(date('H:i', strtotime($r['starts_at']))) ?>–<?= e(date('H:i', strtotime($r['ends_at']))) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Cena</dt><dd class="font-semibold"><?= e(cena($r['price_at_booking'])) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Inspektor</dt><dd class="font-semibold text-right"><?= e($r['i_imie'] . ' ' . $r['i_nazwisko']) ?><span class="block text-xs font-normal text-mobiTextMuted"><?= e($r['institution']) ?></span></dd></div>
                <?php if ($r['comment']): ?>
                    <div><dt class="text-mobiTextMuted">Komentarz klienta</dt><dd class="mt-1"><?= nl2br(e($r['comment'])) ?></dd></div>
                <?php endif; ?>
            </dl>
        </section>

        <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
            <h2 class="font-bold text-gray-900 mb-3">Klient</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Imię i nazwisko</dt><dd class="font-semibold text-right"><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">E-mail</dt><dd class="font-semibold text-right break-all"><?= e($r['k_email']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Telefon</dt><dd class="font-semibold"><?= e($r['k_telefon']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Utworzona</dt><dd class="font-semibold"><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></dd></div>
            </dl>
        </section>
    </div>

    <?php $mozliwe = PRZEJSCIA_STATUSOW[$r['status_code']]; ?>
    <?php if ($mozliwe): ?>
        <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4" aria-labelledby="status-tytul">
            <h2 id="status-tytul" class="font-bold text-gray-900 mb-3">Zmień status</h2>
            <form method="post" action="<?= e(url('admin/rezerwacje.php')) ?>" class="space-y-4">
                <?= csrfPole() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <div>
                    <label for="powod" class="block text-sm font-semibold mb-2">Powód / notatka (opcjonalnie)</label>
                    <input type="text" id="powod" name="powod" maxlength="255" class="pole" placeholder="np. potwierdzenie telefoniczne">
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($mozliwe as $kod): ?>
                        <button type="submit" name="status" value="<?= e($kod) ?>"
                            <?= $kod === 'cancelled' ? 'formnovalidate' : '' ?>
                            class="text-sm font-semibold px-4 py-2 rounded-xl border transition <?= $kod === 'cancelled' ? 'border-red-200 text-red-700 hover:bg-red-50' : 'bg-gray-900 text-white border-gray-900 hover:bg-gray-700' ?>">
                            <?= $kod === 'cancelled' ? 'Anuluj wizytę' : 'Oznacz jako: ' . e(STATUSY_REZERWACJI[$kod]) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm" aria-labelledby="historia-tytul">
        <h2 id="historia-tytul" class="font-bold text-gray-900 mb-3">Historia statusów</h2>
        <ol class="space-y-3 text-sm">
            <?php foreach ($historia as $h): ?>
                <li class="flex flex-wrap items-start gap-x-3 gap-y-1 border-l-2 border-gray-200 pl-3">
                    <span class="text-mobiTextMuted w-32 shrink-0"><?= e(date('d.m.Y H:i', strtotime($h['changed_at']))) ?></span>
                    <span class="flex-1 min-w-[12rem]">
                        <?= $h['old_status_code'] ? znaczekStatusu($h['old_status_code']) . ' → ' : '' ?><?= znaczekStatusu($h['new_status_code']) ?>
                        <span class="block text-xs text-mobiTextMuted mt-1"><?= e($h['reason']) ?><?= $h['first_name'] ? ' · ' . e($h['first_name'] . ' ' . $h['last_name']) : '' ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

// --- lista z filtrami ---
$filtry = [
    'od' => is_string($_GET['od'] ?? null) ? $_GET['od'] : '',
    'do' => is_string($_GET['do'] ?? null) ? $_GET['do'] : '',
    'status' => is_string($_GET['status'] ?? null) ? $_GET['status'] : '',
    'inspektor' => filter_var($_GET['inspektor'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
    'szukaj' => is_string($_GET['szukaj'] ?? null) ? trim($_GET['szukaj']) : '',
];

function poprawnaData(string $d): bool
{
    $data = DateTime::createFromFormat('!Y-m-d', $d);
    return $data !== false && $data->format('Y-m-d') === $d;
}

$warunki = [];
$parametry = [];

if (poprawnaData($filtry['od'])) {
    $warunki[] = 'r.starts_at >= :od';
    $parametry['od'] = $filtry['od'] . ' 00:00:00';
} else {
    $filtry['od'] = '';
}
if (poprawnaData($filtry['do'])) {
    $warunki[] = 'r.starts_at < :do + INTERVAL 1 DAY';
    $parametry['do'] = $filtry['do'];
} else {
    $filtry['do'] = '';
}
if (array_key_exists($filtry['status'], STATUSY_REZERWACJI)) {
    $warunki[] = 'r.status_code = :status';
    $parametry['status'] = $filtry['status'];
} else {
    $filtry['status'] = '';
}
if ($filtry['inspektor'] !== null) {
    $warunki[] = 'r.inspector_id = :inspektor';
    $parametry['inspektor'] = $filtry['inspektor'];
}
if ($filtry['szukaj'] !== '') {
    $warunki[] = '(CONCAT(k.first_name, \' \', k.last_name) LIKE :szukaj1 OR k.email LIKE :szukaj2)';
    $wzor = '%' . addcslashes($filtry['szukaj'], '%_\\') . '%';
    $parametry['szukaj1'] = $wzor;
    $parametry['szukaj2'] = $wzor;
}

$gdzie = $warunki ? 'WHERE ' . implode(' AND ', $warunki) : '';
$zlaczenia = 'FROM reservations r
              JOIN services s ON s.id = r.service_id
              JOIN users k ON k.id = r.client_id
              JOIN inspectors i ON i.id = r.inspector_id
              JOIN users iu ON iu.id = i.user_id';

// eksport do CSV z tymi samymi filtrami co lista (Excel: średnik i BOM dla polskich znaków)
if (($_GET['eksport'] ?? '') === 'csv') {
    // komórka zaczynająca się od = + - @ byłaby w Excelu formułą
    $bezFormuly = fn (string $w): string => preg_match('/^[=+\-@\t\r]/', $w) ? "'" . $w : $w;

    $wiersze = db()->prepare(
        "SELECT r.id, r.starts_at, r.ends_at, r.status_code, r.price_at_booking, s.name AS usluga,
                CONCAT(k.first_name, ' ', k.last_name) AS klient, k.email,
                CONCAT(iu.first_name, ' ', iu.last_name) AS inspektor
         $zlaczenia $gdzie
         ORDER BY r.starts_at DESC, r.id DESC"
    );
    $wiersze->execute($parametry);

    zapiszAudyt('reservation.export', 'reservations', 0, 'Eksport rezerwacji do CSV');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rezerwacje-' . date('Y-m-d') . '.csv"');

    $plik = fopen('php://output', 'w');
    fwrite($plik, "\xEF\xBB\xBF");
    fputcsv($plik, ['ID', 'Początek', 'Koniec', 'Status', 'Cena', 'Usługa', 'Klient', 'E-mail', 'Inspektor'], ';', '"', '');

    while ($w = $wiersze->fetch()) {
        fputcsv($plik, [
            $w['id'], $w['starts_at'], $w['ends_at'], STATUSY_REZERWACJI[$w['status_code']], $w['price_at_booking'],
            $bezFormuly($w['usluga']), $bezFormuly($w['klient']), $bezFormuly($w['email']), $bezFormuly($w['inspektor']),
        ], ';', '"', '');
    }
    fclose($plik);
    exit;
}

$ile = db()->prepare("SELECT COUNT(*) $zlaczenia $gdzie");
$ile->execute($parametry);
$wszystkich = (int) $ile->fetchColumn();

$stron = max(1, (int) ceil($wszystkich / NA_STRONE));
$strona = filter_var($_GET['strona'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$strona = min($strona, $stron);

$lista = db()->prepare(
    "SELECT r.id, r.starts_at, r.ends_at, r.status_code, r.price_at_booking,
            s.name AS usluga, k.first_name AS k_imie, k.last_name AS k_nazwisko,
            iu.first_name AS i_imie, iu.last_name AS i_nazwisko
     $zlaczenia $gdzie
     ORDER BY r.starts_at DESC, r.id DESC
     LIMIT " . NA_STRONE . ' OFFSET ' . (($strona - 1) * NA_STRONE)
);
$lista->execute($parametry);
$rezerwacje = $lista->fetchAll();

$inspektorzy = db()->query(
    'SELECT i.id, u.first_name, u.last_name FROM inspectors i JOIN users u ON u.id = i.user_id ORDER BY u.last_name'
)->fetchAll();

// link do innej strony z zachowaniem filtrów
function linkStrony(array $filtry, int $strona): string
{
    $parametry = array_filter($filtry + ['strona' => $strona], fn ($w) => $w !== '' && $w !== null);
    return url('admin/rezerwacje.php?' . http_build_query($parametry));
}

$tytul = 'Rezerwacje';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Rezerwacje</h1>
<p class="text-mobiTextMuted mb-6">Wszystkie wizyty w systemie.</p>

<?= menuAdmina('rezerwacje.php') ?>

<form method="get" action="<?= e(url('admin/rezerwacje.php')) ?>" class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end" role="search">
    <div class="col-span-2 md:col-span-2">
        <label for="szukaj" class="block text-xs font-semibold mb-1">Klient</label>
        <input type="search" id="szukaj" name="szukaj" value="<?= e($filtry['szukaj']) ?>" maxlength="100" placeholder="Imię, nazwisko lub e-mail" class="pole">
    </div>
    <div>
        <label for="od" class="block text-xs font-semibold mb-1">Od</label>
        <input type="date" id="od" name="od" value="<?= e($filtry['od']) ?>" class="pole">
    </div>
    <div>
        <label for="do" class="block text-xs font-semibold mb-1">Do</label>
        <input type="date" id="do" name="do" value="<?= e($filtry['do']) ?>" class="pole">
    </div>
    <div>
        <label for="status" class="block text-xs font-semibold mb-1">Status</label>
        <select id="status" name="status" class="pole">
            <option value="">Wszystkie</option>
            <?php foreach (STATUSY_REZERWACJI as $kod => $nazwa): ?>
                <option value="<?= e($kod) ?>" <?= $filtry['status'] === $kod ? 'selected' : '' ?>><?= e($nazwa) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="inspektor" class="block text-xs font-semibold mb-1">Inspektor</label>
        <select id="inspektor" name="inspektor" class="pole">
            <option value="">Wszyscy</option>
            <?php foreach ($inspektorzy as $i): ?>
                <option value="<?= (int) $i['id'] ?>" <?= $filtry['inspektor'] === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['first_name'] . ' ' . $i['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-2 md:col-span-6 flex flex-wrap gap-2">
        <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">Filtruj</button>
        <a href="<?= e(url('admin/rezerwacje.php?od=' . date('Y-m-d') . '&do=' . date('Y-m-d'))) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-semibold px-5 py-3 rounded-xl border border-gray-200">Dzisiaj</a>
        <a href="<?= e(url('admin/rezerwacje.php')) ?>" class="text-sm font-semibold px-5 py-3 text-gray-600 hover:text-mobiRed">Wyczyść</a>
        <a href="<?= e(url('admin/rezerwacje.php?' . http_build_query(array_filter($filtry + ['eksport' => 'csv'], fn ($w) => $w !== '' && $w !== null)))) ?>" class="ml-auto text-sm font-semibold px-5 py-3 rounded-xl border border-gray-200 hover:border-gray-300 inline-flex items-center gap-2">
            <i aria-hidden="true" class="fa-solid fa-file-csv text-mobiRed"></i> Eksport CSV
        </a>
    </div>
</form>

<section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-label="Lista rezerwacji">
    <p class="px-5 pt-5 text-sm text-mobiTextMuted">Znaleziono: <strong class="text-gray-900"><?= $wszystkich ?></strong></p>
    <?php if (!$rezerwacje): ?>
        <p class="p-5 text-sm text-mobiTextMuted">Brak rezerwacji spełniających kryteria.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm mt-3">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-5 py-3">Termin</th>
                    <th scope="col" class="px-5 py-3 min-w-[10rem]">Usługa / klient</th>
                    <th scope="col" class="px-5 py-3 hidden md:table-cell">Inspektor</th>
                    <th scope="col" class="px-5 py-3">Status</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">Szczegóły</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($rezerwacje as $r): ?>
                <tr>
                    <td class="px-5 py-4 whitespace-nowrap">
                        <p class="font-semibold"><?= e(date('d.m.Y', strtotime($r['starts_at']))) ?></p>
                        <p class="text-xs text-mobiTextMuted"><?= e(date('H:i', strtotime($r['starts_at']))) ?>–<?= e(date('H:i', strtotime($r['ends_at']))) ?></p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-900"><?= e($r['usluga']) ?></p>
                        <p class="text-xs text-mobiTextMuted"><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?> · <?= e(cena($r['price_at_booking'])) ?></p>
                        <p class="text-xs text-mobiTextMuted md:hidden"><?= e($r['i_imie'] . ' ' . $r['i_nazwisko']) ?></p>
                    </td>
                    <td class="px-5 py-4 hidden md:table-cell"><?= e($r['i_imie'] . ' ' . $r['i_nazwisko']) ?></td>
                    <td class="px-5 py-4"><?= znaczekStatusu($r['status_code']) ?></td>
                    <td class="px-5 py-4 text-right">
                        <a href="<?= e(url('admin/rezerwacje.php?id=' . $r['id'])) ?>" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300 whitespace-nowrap">Szczegóły</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($stron > 1): ?>
        <nav class="flex flex-wrap items-center justify-between gap-3 p-5 border-t border-gray-100 text-sm" aria-label="Strony">
            <?php if ($strona > 1): ?>
                <a href="<?= e(linkStrony($filtry, $strona - 1)) ?>" class="font-semibold text-mobiRed">← Poprzednia</a>
            <?php else: ?><span></span><?php endif; ?>
            <span class="text-mobiTextMuted">Strona <?= $strona ?> z <?= $stron ?></span>
            <?php if ($strona < $stron): ?>
                <a href="<?= e(linkStrony($filtry, $strona + 1)) ?>" class="font-semibold text-mobiRed">Następna →</a>
            <?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
