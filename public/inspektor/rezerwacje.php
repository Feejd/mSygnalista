<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/rezerwacje.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_INSPEKTOR);

$profil = profilInspektora((int) aktualnyUzytkownik()['id']);
if ($profil === null) {
    bladStrony(403, 'To konto nie ma profilu inspektora.');
}
$inspektorId = (int) $profil['id'];

// Wizyta tylko tego inspektora - cudze id daje 404, tak samo jak nieistniejące.
function wizytaInspektora(int $id, int $inspektorId): ?array
{
    $zapytanie = db()->prepare(
        'SELECT r.*, s.name AS usluga, s.duration_minutes,
                k.first_name AS k_imie, k.last_name AS k_nazwisko, k.email AS k_email, k.phone AS k_telefon
         FROM reservations r
         JOIN services s ON s.id = r.service_id
         JOIN users k ON k.id = r.client_id
         WHERE r.id = :id AND r.inspector_id = :inspektor'
    );
    $zapytanie->execute(['id' => $id, 'inspektor' => $inspektorId]);
    return $zapytanie->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $wizyta = wizytaInspektora($id, $inspektorId);
    if ($wizyta === null) {
        bladStrony(404, 'Nie znaleziono wizyty.');
    }

    $nowy = pole('status');
    $powod = mb_substr(pole('powod'), 0, 255) ?: 'Zmiana statusu przez inspektora';

    if (!in_array($nowy, dozwoloneStatusyInspektora($wizyta), true)) {
        komunikat('blad', 'Tej zmiany statusu nie można teraz wykonać.');
    } else {
        $blad = zmienStatusRezerwacji($id, $nowy, $powod);
        komunikat($blad === null ? 'sukces' : 'blad', $blad ?? 'Status wizyty: ' . STATUSY_REZERWACJI[$nowy] . '.');
    }
    przekieruj('inspektor/rezerwacje.php?id=' . $id);
}

// --- szczegóły wizyty ---
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $w = wizytaInspektora($id, $inspektorId);
    if ($w === null) {
        bladStrony(404, 'Nie znaleziono wizyty.');
    }

    $historia = db()->prepare(
        'SELECT h.old_status_code, h.new_status_code, h.reason, h.changed_at
         FROM reservation_status_history h WHERE h.reservation_id = :id ORDER BY h.changed_at, h.id'
    );
    $historia->execute(['id' => $w['id']]);
    $historia = $historia->fetchAll();
    $mozliwe = dozwoloneStatusyInspektora($w);

    $tytul = 'Wizyta #' . $w['id'];
    require __DIR__ . '/../../includes/header.php';
    ?>
    <a href="<?= e(url('inspektor/rezerwacje.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-4">
        <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Moje wizyty
    </a>
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900"><?= e($w['usluga']) ?></h1>
        <?= znaczekStatusu($w['status_code']) ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
            <h2 class="font-bold text-gray-900 mb-3">Termin</h2>
            <p class="text-lg font-semibold"><?= e(date('d.m.Y', strtotime($w['starts_at']))) ?></p>
            <p class="text-sm text-mobiTextMuted"><?= e(date('H:i', strtotime($w['starts_at']))) ?>–<?= e(date('H:i', strtotime($w['ends_at']))) ?> (<?= (int) $w['duration_minutes'] ?> min)</p>
            <?php if ($w['comment']): ?>
                <h3 class="text-sm font-semibold mt-4 mb-1">Sprawa klienta</h3>
                <p class="text-sm"><?= nl2br(e($w['comment'])) ?></p>
            <?php endif; ?>
        </section>
        <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
            <h2 class="font-bold text-gray-900 mb-3">Klient</h2>
            <p class="font-semibold"><?= e($w['k_imie'] . ' ' . $w['k_nazwisko']) ?></p>
            <p class="text-sm"><a href="tel:<?= e($w['k_telefon']) ?>" class="text-mobiRed font-semibold"><?= e($w['k_telefon']) ?></a></p>
            <p class="text-sm break-all"><a href="mailto:<?= e($w['k_email']) ?>" class="text-mobiRed"><?= e($w['k_email']) ?></a></p>
        </section>
    </div>

    <?php if ($mozliwe): ?>
        <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4" aria-labelledby="status-tytul">
            <h2 id="status-tytul" class="font-bold text-gray-900 mb-3">Zmień status</h2>
            <form method="post" action="<?= e(url('inspektor/rezerwacje.php')) ?>" class="space-y-4">
                <?= csrfPole() ?>
                <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                <div>
                    <label for="powod" class="block text-sm font-semibold mb-2">Notatka (opcjonalnie)</label>
                    <input type="text" id="powod" name="powod" maxlength="255" class="pole">
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($mozliwe as $kod): ?>
                        <button type="submit" name="status" value="<?= e($kod) ?>"
                            class="text-sm font-semibold px-4 py-2 rounded-xl border transition <?= $kod === 'cancelled' ? 'border-red-200 text-red-700 hover:bg-red-50' : 'bg-gray-900 text-white border-gray-900 hover:bg-gray-700' ?>">
                            <?= $kod === 'cancelled' ? 'Odwołaj wizytę' : ($kod === 'confirmed' ? 'Potwierdź wizytę' : 'Wizyta odbyła się') ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>
        </section>
    <?php elseif ($w['status_code'] === 'confirmed'): ?>
        <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted mb-4">Wizytę oznaczysz jako zrealizowaną, gdy się rozpocznie.</p>
    <?php endif; ?>

    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm" aria-labelledby="historia-tytul">
        <h2 id="historia-tytul" class="font-bold text-gray-900 mb-3">Historia statusów</h2>
        <ol class="space-y-3 text-sm">
            <?php foreach ($historia as $h): ?>
                <li class="border-l-2 border-gray-200 pl-3">
                    <span class="text-mobiTextMuted"><?= e(date('d.m.Y H:i', strtotime($h['changed_at']))) ?></span>
                    <span class="block mt-1"><?= $h['old_status_code'] ? znaczekStatusu($h['old_status_code']) . ' → ' : '' ?><?= znaczekStatusu($h['new_status_code']) ?></span>
                    <span class="block text-xs text-mobiTextMuted mt-1"><?= e($h['reason']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

// --- lista: dziś / przyszłe / historia ---
$widoki = ['dzis' => 'Dzisiaj', 'przyszle' => 'Nadchodzące', 'historia' => 'Historia'];
$widok = is_string($_GET['widok'] ?? null) && isset($widoki[$_GET['widok']]) ? $_GET['widok'] : 'dzis';

const HISTORIA_NA_STRONE = 20;
$strona = filter_var($_GET['strona'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]) ?: 1;
$wizyty = $widok === 'historia'
    ? wizytyInspektora($inspektorId, 'historia', HISTORIA_NA_STRONE + 1, ($strona - 1) * HISTORIA_NA_STRONE)
    : wizytyInspektora($inspektorId, $widok);

// pobieramy o jedną więcej, żeby wiedzieć, czy jest następna strona
$jestNastepna = $widok === 'historia' && count($wizyty) > HISTORIA_NA_STRONE;
$wizyty = array_slice($wizyty, 0, $widok === 'historia' ? HISTORIA_NA_STRONE : null);

$tytul = 'Moje wizyty';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Moje wizyty</h1>
<p class="text-mobiTextMuted mb-6">Konsultacje prowadzone przez Ciebie.</p>

<nav class="flex flex-wrap gap-2 mb-4" aria-label="Widok wizyt">
    <?php foreach ($widoki as $kod => $nazwa): ?>
        <a href="<?= e(url('inspektor/rezerwacje.php?widok=' . $kod)) ?>" <?= $kod === $widok ? 'aria-current="page"' : '' ?>
           class="text-xs font-semibold px-4 py-2 rounded-xl border transition <?= $kod === $widok ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300' ?>"><?= e($nazwa) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$wizyty): ?>
    <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted">Brak wizyt.</p>
<?php else: ?>
    <ul class="bg-white border border-gray-200 rounded-3xl shadow-sm divide-y divide-gray-100 overflow-hidden">
        <?php foreach ($wizyty as $w): ?>
            <li>
                <a href="<?= e(url('inspektor/rezerwacje.php?id=' . $w['id'])) ?>" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-gray-50">
                    <span class="w-28 shrink-0">
                        <span class="block font-semibold"><?= e(date('H:i', strtotime($w['starts_at']))) ?>–<?= e(date('H:i', strtotime($w['ends_at']))) ?></span>
                        <?php if ($widok !== 'dzis'): ?>
                            <span class="block text-xs text-mobiTextMuted"><?= e(date('d.m.Y', strtotime($w['starts_at']))) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="flex-1 min-w-[10rem]">
                        <?= e($w['usluga']) ?>
                        <span class="block text-xs text-mobiTextMuted"><?= e($w['k_imie'] . ' ' . $w['k_nazwisko']) ?> · <?= e($w['k_telefon']) ?></span>
                    </span>
                    <?= znaczekStatusu($w['status_code']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($widok === 'historia' && ($strona > 1 || $jestNastepna)): ?>
    <nav class="flex justify-between gap-3 mt-4 text-sm" aria-label="Strony">
        <?php if ($strona > 1): ?>
            <a href="<?= e(url('inspektor/rezerwacje.php?widok=historia&strona=' . ($strona - 1))) ?>" class="font-semibold text-mobiRed">← Nowsze</a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($jestNastepna): ?>
            <a href="<?= e(url('inspektor/rezerwacje.php?widok=historia&strona=' . ($strona + 1))) ?>" class="font-semibold text-mobiRed">Starsze →</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
