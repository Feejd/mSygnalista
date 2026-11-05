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
        (SELECT COUNT(*) FROM reservations) AS rezerwacje,
        (SELECT COUNT(*) FROM reservations WHERE DATE(starts_at) = CURDATE() AND status_code <> 'cancelled') AS dzisiaj,
        (SELECT COUNT(*) FROM reservations WHERE status_code = 'pending' AND starts_at >= NOW()) AS do_potwierdzenia"
)->fetch();

$dzisiejsze = db()->query(
    "SELECT r.id, r.starts_at, r.ends_at, r.status_code, s.name AS usluga,
            k.first_name AS k_imie, k.last_name AS k_nazwisko, iu.first_name AS i_imie, iu.last_name AS i_nazwisko
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN users k ON k.id = r.client_id
     JOIN inspectors i ON i.id = r.inspector_id
     JOIN users iu ON iu.id = i.user_id
     WHERE DATE(r.starts_at) = CURDATE()
     ORDER BY r.starts_at"
)->fetchAll();

// wykres: wizyty w ostatnich 6 miesiącach (bez anulowanych), także miesiące bez wizyt
$naMiesiac = db()->query(
    "SELECT DATE_FORMAT(starts_at, '%Y-%m') AS miesiac, COUNT(*) AS liczba
     FROM reservations
     WHERE status_code <> 'cancelled'
       AND starts_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
       AND starts_at < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')
     GROUP BY miesiac"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$nazwyMiesiecy = ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];
$wykres = [];
for ($i = 5; $i >= 0; $i--) {
    $data = new DateTimeImmutable('first day of -' . $i . ' month');
    $wykres[] = [
        'nazwa' => $nazwyMiesiecy[(int) $data->format('n') - 1],
        'liczba' => (int) ($naMiesiac[$data->format('Y-m')] ?? 0),
    ];
}
$najwiecej = max(1, ...array_column($wykres, 'liczba'));

$tytul = 'Panel administratora';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Panel administratora</h1>
<p class="text-mobiTextMuted mb-6">Zalogowano jako <?= e($uzytkownik['first_name'] . ' ' . $uzytkownik['last_name']) ?>.</p>

<?= menuAdmina('index.php') ?>

<div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
    <?php foreach ([
        ['Obywatele', $liczby['obywatele'], 'fa-users'],
        ['Inspektorzy', $liczby['inspektorzy'], 'fa-user-tie'],
        ['Usługi', $liczby['uslugi'], 'fa-list-check'],
        ['Rezerwacje', $liczby['rezerwacje'], 'fa-calendar-days'],
        ['Wizyty dzisiaj', $liczby['dzisiaj'], 'fa-calendar-day'],
        ['Do potwierdzenia', $liczby['do_potwierdzenia'], 'fa-hourglass-half'],
    ] as [$nazwa, $liczba, $ikona]): ?>
        <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
            <i aria-hidden="true" class="fa-solid <?= e($ikona) ?> text-mobiRed mb-3"></i>
            <p class="text-2xl font-extrabold"><?= (int) $liczba ?></p>
            <p class="text-xs text-mobiTextMuted"><?= e($nazwa) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-6" aria-labelledby="wykres-tytul">
    <h2 id="wykres-tytul" class="text-lg font-bold text-gray-900">Wizyty w ostatnich 6 miesiącach</h2>
    <p class="text-xs text-mobiTextMuted mb-4">Liczba wizyt według miesiąca, bez anulowanych.</p>

    <div class="wykres" aria-hidden="true">
        <?php foreach ($wykres as $m): ?>
            <div class="wykres-kolumna">
                <span class="wykres-dymek"><?= e($m['nazwa']) ?>: <?= $m['liczba'] ?></span>
                <div class="wykres-pole">
                    <?php if ($m['liczba'] > 0): ?>
                        <div class="wykres-slupek" style="height: <?= round($m['liczba'] / $najwiecej * 100, 1) ?>%"></div>
                    <?php endif; ?>
                </div>
                <span class="wykres-opis"><?= e($m['nazwa']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-mobiTextMuted text-xs font-semibold">Pokaż jako tabelę</summary>
        <table class="mt-2 w-full max-w-xs">
            <thead><tr class="text-left text-xs text-gray-500"><th scope="col" class="py-1">Miesiąc</th><th scope="col" class="py-1 text-right">Wizyty</th></tr></thead>
            <tbody>
                <?php foreach ($wykres as $m): ?>
                    <tr class="border-t border-gray-100"><td class="py-1"><?= e($m['nazwa']) ?></td><td class="py-1 text-right"><?= $m['liczba'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>
</section>

<section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-labelledby="dzis-tytul">
    <div class="flex flex-wrap justify-between items-center gap-2 p-5 pb-0">
        <h2 id="dzis-tytul" class="text-lg font-bold text-gray-900">Wizyty na dziś, <?= e(date('d.m.Y')) ?></h2>
        <a href="<?= e(url('admin/rezerwacje.php?status=pending')) ?>" class="text-sm font-semibold text-mobiRed">Oczekujące na potwierdzenie →</a>
    </div>
    <?php if (!$dzisiejsze): ?>
        <p class="p-5 text-sm text-mobiTextMuted">Na dziś nie ma zaplanowanych wizyt.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100 mt-3">
            <?php foreach ($dzisiejsze as $r): ?>
                <li>
                    <a href="<?= e(url('admin/rezerwacje.php?id=' . $r['id'])) ?>" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-gray-50">
                        <span class="font-semibold w-24"><?= e(date('H:i', strtotime($r['starts_at']))) ?>–<?= e(date('H:i', strtotime($r['ends_at']))) ?></span>
                        <span class="flex-1 min-w-[10rem]">
                            <?= e($r['usluga']) ?>
                            <span class="block text-xs text-mobiTextMuted"><?= e($r['k_imie'] . ' ' . $r['k_nazwisko']) ?> · <?= e($r['i_imie'] . ' ' . $r['i_nazwisko']) ?></span>
                        </span>
                        <?= znaczekStatusu($r['status_code']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
