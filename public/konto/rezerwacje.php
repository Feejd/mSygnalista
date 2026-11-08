<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_OBYWATEL);

$klientId = (int) aktualnyUzytkownik()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

    // właściciel sprawdzany w zapytaniu - podmiana id na cudzą wizytę daje "nie znaleziono"
    $zapytanie = db()->prepare(
        "SELECT id, starts_at, status_code FROM reservations WHERE id = :id AND client_id = :klient"
    );
    $zapytanie->execute(['id' => $id, 'klient' => $klientId]);
    $wizyta = $zapytanie->fetch();

    if (!$wizyta) {
        bladStrony(404, 'Nie znaleziono rezerwacji.');
    }

    if (strtotime($wizyta['starts_at']) <= time()) {
        komunikat('blad', 'Można anulować tylko wizytę, która jeszcze się nie odbyła.');
    } else {
        $blad = zmienStatusRezerwacji($id, 'cancelled', 'Anulowanie przez klienta');
        komunikat($blad === null ? 'sukces' : 'blad', $blad ?? 'Wizyta została anulowana.');
    }
    przekieruj('konto/rezerwacje.php');
}

$wizyty = db()->prepare(
    'SELECT r.id, r.starts_at, r.ends_at, r.status_code, r.price_at_booking, r.comment,
            s.name AS usluga, u.first_name, u.last_name, i.institution
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN inspectors i ON i.id = r.inspector_id
     JOIN users u ON u.id = i.user_id
     WHERE r.client_id = :klient
     ORDER BY r.starts_at'
);
$wizyty->execute(['klient' => $klientId]);

$przyszle = [];
$zakonczone = [];
foreach ($wizyty->fetchAll() as $w) {
    $aktywna = in_array($w['status_code'], ['pending', 'confirmed'], true);
    if ($aktywna && strtotime($w['ends_at']) > time()) {
        $przyszle[] = $w;
    } else {
        $zakonczone[] = $w;
    }
}
$zakonczone = array_reverse($zakonczone);

function kartaWizyty(array $w, bool $moznaAnulowac): void
{
    ?>
    <li class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
        <div class="flex flex-wrap justify-between items-start gap-3">
            <div>
                <p class="font-bold text-gray-900"><?= e($w['usluga']) ?></p>
                <p class="text-sm text-mobiTextMuted"><?= e($w['first_name'] . ' ' . $w['last_name']) ?> · <?= e($w['institution']) ?></p>
            </div>
            <?= znaczekStatusu($w['status_code']) ?>
        </div>
        <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm mt-3">
            <div><dt class="sr-only">Data</dt><dd><i aria-hidden="true" class="fa-regular fa-calendar text-mobiRed mr-1"></i><?= e(date('d.m.Y', strtotime($w['starts_at']))) ?></dd></div>
            <div><dt class="sr-only">Godzina</dt><dd><i aria-hidden="true" class="fa-regular fa-clock text-mobiRed mr-1"></i><?= e(date('H:i', strtotime($w['starts_at']))) ?>–<?= e(date('H:i', strtotime($w['ends_at']))) ?></dd></div>
            <div><dt class="sr-only">Cena</dt><dd><?= e(cena($w['price_at_booking'])) ?></dd></div>
        </dl>
        <?php if ($moznaAnulowac && strtotime($w['starts_at']) > time()): ?>
            <form method="post" action="<?= e(url('konto/rezerwacje.php')) ?>" class="mt-4" data-potwierdz="Anulować wizytę <?= e(date('d.m.Y H:i', strtotime($w['starts_at']))) ?>?">
                <?= csrfPole() ?>
                <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50">Anuluj wizytę</button>
            </form>
        <?php endif; ?>
    </li>
    <?php
}

$tytul = 'Moje wizyty';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap justify-between items-end gap-3 mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-1">Moje wizyty</h1>
        <p class="text-mobiTextMuted">Twoje konsultacje z inspektorami.</p>
    </div>
    <a href="<?= e(url('uslugi.php')) ?>" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl text-sm inline-flex items-center gap-2">
        <i aria-hidden="true" class="fa-solid fa-calendar-plus"></i> Umów wizytę
    </a>
</div>

<h2 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Nadchodzące (<?= count($przyszle) ?>)</h2>
<?php if (!$przyszle): ?>
    <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted mb-8">Nie masz zaplanowanych wizyt.</p>
<?php else: ?>
    <ul class="space-y-3 mb-8">
        <?php foreach ($przyszle as $w) { kartaWizyty($w, true); } ?>
    </ul>
<?php endif; ?>

<h2 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Zakończone i anulowane (<?= count($zakonczone) ?>)</h2>
<?php if (!$zakonczone): ?>
    <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted">Brak.</p>
<?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($zakonczone as $w) { kartaWizyty($w, false); } ?>
    </ul>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
