<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/rezerwacje.php';

// Panel inspektora - administrator też może tu wejść, ale wizyty widzi tylko inspektor z profilem.
wymagajRoli(ROLA_INSPEKTOR, ROLA_ADMIN);

$uzytkownik = aktualnyUzytkownik();
$inspektor = profilInspektora((int) $uzytkownik['id']);

$liczby = null;
$dzisiaj = [];

if ($inspektor !== null && maRole(ROLA_INSPEKTOR)) {
    $zapytanie = db()->prepare(
        "SELECT
            SUM(DATE(starts_at) = CURDATE() AND status_code IN ('pending', 'confirmed')) AS dzisiaj,
            SUM(status_code = 'pending' AND starts_at >= NOW()) AS do_potwierdzenia,
            SUM(starts_at >= CURDATE() + INTERVAL 1 DAY AND starts_at < CURDATE() + INTERVAL 8 DAY
                AND status_code IN ('pending', 'confirmed')) AS tydzien
         FROM reservations WHERE inspector_id = :id"
    );
    $zapytanie->execute(['id' => $inspektor['id']]);
    $liczby = $zapytanie->fetch();
    $dzisiaj = wizytyInspektora((int) $inspektor['id'], 'dzis');
}

$tytul = 'Panel inspektora';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Panel inspektora</h1>
<p class="text-mobiTextMuted mb-6">Dzień dobry, <?= e($uzytkownik['first_name']) ?>. Dziś jest <?= e(date('d.m.Y')) ?>.</p>

<?php if ($inspektor === null || $liczby === null): ?>
    <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted">To konto nie ma aktywnego profilu inspektora.</p>
<?php else: ?>
    <?php if ((int) $inspektor['is_active'] !== 1): ?>
        <p class="komunikat komunikat-blad mb-4">Twój profil jest nieaktywny - klienci nie mogą umawiać nowych wizyt.</p>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-6">
        <?php foreach ([
            ['Dzisiaj', $liczby['dzisiaj'], 'inspektor/rezerwacje.php?widok=dzis'],
            ['Do potwierdzenia', $liczby['do_potwierdzenia'], 'inspektor/rezerwacje.php?widok=przyszle'],
            ['Najbliższe 7 dni', $liczby['tydzien'], 'inspektor/rezerwacje.php?widok=przyszle'],
        ] as [$nazwa, $liczba, $link]): ?>
            <a href="<?= e(url($link)) ?>" class="bg-white border border-gray-200 hover:border-gray-300 rounded-3xl p-4 sm:p-5 shadow-sm">
                <p class="text-2xl font-extrabold"><?= (int) $liczba ?></p>
                <p class="text-xs text-mobiTextMuted"><?= e($nazwa) ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden mb-4" aria-labelledby="dzis-tytul">
        <div class="flex flex-wrap justify-between items-center gap-2 p-5 pb-0">
            <h2 id="dzis-tytul" class="text-lg font-bold text-gray-900">Wizyty na dziś</h2>
            <a href="<?= e(url('inspektor/rezerwacje.php?widok=przyszle')) ?>" class="text-sm font-semibold text-mobiRed">Wszystkie wizyty →</a>
        </div>
        <?php if (!$dzisiaj): ?>
            <p class="p-5 text-sm text-mobiTextMuted">Dziś nie masz zaplanowanych wizyt.</p>
        <?php else: ?>
            <ul class="divide-y divide-gray-100 mt-3">
                <?php foreach ($dzisiaj as $w): ?>
                    <li>
                        <a href="<?= e(url('inspektor/rezerwacje.php?id=' . $w['id'])) ?>" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-gray-50">
                            <span class="font-semibold w-28"><?= e(date('H:i', strtotime($w['starts_at']))) ?>–<?= e(date('H:i', strtotime($w['ends_at']))) ?></span>
                            <span class="flex-1 min-w-[10rem]"><?= e($w['usluga']) ?><span class="block text-xs text-mobiTextMuted"><?= e($w['k_imie'] . ' ' . $w['k_nazwisko']) ?></span></span>
                            <?= znaczekStatusu($w['status_code']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
        <h2 class="font-bold text-gray-900 mb-1"><?= e($inspektor['institution']) ?></h2>
        <p class="text-sm text-mobiTextMuted"><?= e($inspektor['description']) ?></p>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
