<?php
require_once __DIR__ . '/../includes/init.php';

if (isset($_GET['umow'])) {
    $uslugaId = filter_var($_GET['umow'], FILTER_VALIDATE_INT);

    if ($uslugaId === false || !czyAktywnaUsluga($uslugaId)) {
        bladStrony(400, 'Nieprawidłowa usługa.');
    }

    if (!zalogowany()) {
        $_SESSION['wybrana_usluga'] = $uslugaId;
        komunikat('blad', 'Zaloguj się, aby umówić konsultację.');
        przekieruj('logowanie.php');
    }

    przekieruj('rezerwacja.php?usluga=' . $uslugaId);
}

$kategorie = db()->query(
    'SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'
)->fetchAll();

$wybranaKategoria = filter_var($_GET['kategoria'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$wybranaKategoria = $wybranaKategoria === false ? null : $wybranaKategoria;
$uslugi = aktywneUslugi($wybranaKategoria);

$tytul = 'Konsultacje';
require __DIR__ . '/../includes/header.php';
?>
    <a href="index.php" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-6">
        <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Strona główna
    </a>

    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Konsultacje</h2>
    <p class="text-mobiTextMuted mb-6">Sprawdź zakres i czas trwania konsultacji.</p>

    <nav class="flex flex-wrap gap-2 mb-6" aria-label="Kategorie">
        <a href="<?= e(url('uslugi.php')) ?>"
           class="text-xs font-semibold px-4 py-2 rounded-xl border transition <?= $wybranaKategoria === null ? 'bg-mobiRed text-white border-mobiRed' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300' ?>">
            Wszystkie
        </a>
        <?php foreach ($kategorie as $k): ?>
            <a href="<?= e(url('uslugi.php?kategoria=' . $k['id'])) ?>"
               class="text-xs font-semibold px-4 py-2 rounded-xl border transition <?= $wybranaKategoria === (int) $k['id'] ? 'bg-mobiRed text-white border-mobiRed' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300' ?>">
                <?= e($k['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$uslugi): ?>
        <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted">Brak dostępnych konsultacji w tej kategorii.</p>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php foreach ($uslugi as $u): ?>
            <article class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm flex flex-col">
                <span class="text-xs font-semibold text-mobiRed mb-2"><?= e($u['category_name']) ?></span>
                <h3 class="text-lg font-bold text-gray-900 mb-2"><?= e($u['name']) ?></h3>
                <p class="text-sm text-mobiTextMuted mb-5"><?= e($u['description']) ?></p>
                <dl class="flex justify-between gap-4 border-t border-gray-100 pt-4 mt-auto text-sm">
                    <div>
                        <dt class="text-mobiTextMuted">Czas trwania</dt>
                        <dd class="font-semibold"><?= (int) $u['duration_minutes'] ?> minut</dd>
                    </div>
                    <div class="text-right">
                        <dt class="text-mobiTextMuted">Cena</dt>
                        <dd class="font-semibold"><?= e(cena($u['price'])) ?></dd>
                    </div>
                </dl>
                <?php if ((int) $u['liczba_inspektorow'] > 0): ?>
                    <a href="<?= e(url('uslugi.php?umow=' . $u['id'])) ?>" aria-label="Umów wizytę: <?= e($u['name']) ?>" class="mt-5 bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-2xl transition inline-flex items-center justify-center gap-2 text-sm">
                        <i aria-hidden="true" class="fa-solid fa-calendar-plus"></i>
                        Umów wizytę
                    </a>
                <?php else: ?>
                    <p class="mt-5 text-center text-sm text-mobiTextMuted bg-gray-50 rounded-2xl px-5 py-3">Chwilowo brak dostępnych inspektorów</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
