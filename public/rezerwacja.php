<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/rezerwacje.php';
header('Cache-Control: no-store');

// Kolejność z wytycznych: usługa -> inspektor -> data -> godzina -> potwierdzenie.
// Każdy krok to parametr w adresie, każdy jest sprawdzany na serwerze od nowa.

if (!zalogowany()) {
    $id = filter_var($_GET['usluga'] ?? null, FILTER_VALIDATE_INT);
    if ($id) {
        $_SESSION['wybrana_usluga'] = $id;
    }
    komunikat('blad', 'Zaloguj się, aby umówić konsultację.');
    przekieruj('logowanie.php');
}
wymagajRoli(ROLA_OBYWATEL);

$klientId = (int) aktualnyUzytkownik()['id'];

// --- krok 1: usługa ---
$uslugaId = filter_var($_GET['usluga'] ?? $_POST['usluga'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$uslugaId) {
    przekieruj('uslugi.php');
}
if (!czyAktywnaUsluga($uslugaId)) {
    komunikat('blad', 'Wybierz konsultację z oferty.');
    przekieruj('uslugi.php');
}

$usluga = db()->prepare('SELECT id, name, duration_minutes, price FROM services WHERE id = :id');
$usluga->execute(['id' => $uslugaId]);
$usluga = $usluga->fetch();

// --- krok 2: inspektor (tylko ci, którzy wykonują tę usługę) ---
$inspektorzy = db()->prepare(
    'SELECT i.id, i.institution, i.description, u.first_name, u.last_name
     FROM inspector_services isv
     JOIN inspectors i ON i.id = isv.inspector_id AND i.is_active = 1
     JOIN users u ON u.id = i.user_id AND u.is_active = 1
     WHERE isv.service_id = :id AND isv.is_active = 1
     ORDER BY u.last_name'
);
$inspektorzy->execute(['id' => $uslugaId]);
$inspektorzy = $inspektorzy->fetchAll();

$inspektorId = filter_var($_GET['inspektor'] ?? $_POST['inspektor'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$inspektor = null;
foreach ($inspektorzy as $i) {
    if ((int) $i['id'] === $inspektorId) {
        $inspektor = $i;
    }
}
if ($inspektorId !== null && $inspektor === null) {
    komunikat('blad', 'Ten inspektor nie prowadzi wybranej konsultacji.');
    przekieruj('rezerwacja.php?usluga=' . $uslugaId);
}

// --- krok 3 i 4: data i godzina ---
$data = is_string($_GET['data'] ?? $_POST['data'] ?? null) ? ($_GET['data'] ?? $_POST['data']) : null;
$godzina = is_string($_GET['godzina'] ?? $_POST['godzina'] ?? null) ? ($_GET['godzina'] ?? $_POST['godzina']) : null;

$wolne = [];
if ($inspektor !== null && $data !== null) {
    if (!poprawnaDataRezerwacji($data)) {
        $data = null;
    } else {
        $wolne = wolneGodziny($inspektorId, $data, (int) $usluga['duration_minutes'], $klientId);
    }
}

// AJAX: lista wolnych godzin dla wybranego dnia (używa jej assets/js/app.js)
if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => $data, 'godziny' => $wolne]);
    exit;
}

if ($godzina !== null && !in_array($godzina, $wolne, true)) {
    komunikat('blad', 'Ten termin jest już niedostępny. Wybierz inną godzinę.');
    przekieruj('rezerwacja.php?' . http_build_query(['usluga' => $uslugaId, 'inspektor' => $inspektorId, 'data' => $data]));
}

// --- krok 5: potwierdzenie i zapis ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }
    if ($inspektor === null || $data === null || $godzina === null) {
        bladStrony(400, 'Niepełne dane rezerwacji.');
    }

    $komentarz = mb_substr(pole('komentarz'), 0, 1000);
    [$noweId, $blad] = zarezerwuj($klientId, $uslugaId, $inspektorId, $data, $godzina, $komentarz);

    if ($blad !== null) {
        komunikat('blad', $blad);
        przekieruj('rezerwacja.php?' . http_build_query(['usluga' => $uslugaId, 'inspektor' => $inspektorId, 'data' => $data]));
    }

    komunikat('sukces', 'Zarezerwowano wizytę na ' . date('d.m.Y', strtotime($data)) . ' o ' . $godzina
        . '. Status: oczekująca na potwierdzenie inspektora.');
    przekieruj('konto/rezerwacje.php');
}

function adresKroku(array $parametry): string
{
    return url('rezerwacja.php?' . http_build_query($parametry));
}

$dniTygodnia = ['', 'pon', 'wt', 'śr', 'czw', 'pt', 'sob', 'nd'];
$krok = $godzina !== null ? 4 : ($data !== null ? 3 : ($inspektor !== null ? 2 : 1));

$tytul = 'Rezerwacja';
require __DIR__ . '/../includes/header.php';
?>
<a href="<?= e(url('uslugi.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-4">
    <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Oferta
</a>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-1">Rezerwacja konsultacji</h1>
<p class="text-mobiTextMuted mb-6"><?= e($usluga['name']) ?> · <?= (int) $usluga['duration_minutes'] ?> min · <?= e(cena($usluga['price'])) ?></p>

<ol class="flex flex-wrap gap-2 mb-6 text-xs font-semibold" aria-label="Kroki rezerwacji">
    <?php foreach (['Inspektor', 'Data', 'Godzina', 'Potwierdzenie'] as $nr => $nazwa): ?>
        <li class="px-3 py-2 rounded-xl border <?= $nr + 1 === $krok ? 'bg-gray-900 text-white border-gray-900' : ($nr + 1 < $krok ? 'bg-white text-gray-900 border-gray-300' : 'bg-white text-gray-400 border-gray-200') ?>"
            <?= $nr + 1 === $krok ? 'aria-current="step"' : '' ?>>
            <?= $nr + 1 ?>. <?= e($nazwa) ?>
        </li>
    <?php endforeach; ?>
</ol>

<?php if ($krok === 1): ?>
    <section aria-labelledby="krok-tytul">
        <h2 id="krok-tytul" class="text-lg font-bold text-gray-900 mb-3">Wybierz inspektora</h2>
        <?php if (!$inspektorzy): ?>
            <p class="bg-white border border-gray-200 rounded-3xl p-5 text-sm text-mobiTextMuted">Tę konsultację chwilowo nikt nie prowadzi.</p>
        <?php endif; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach ($inspektorzy as $i): ?>
                <a href="<?= e(adresKroku(['usluga' => $uslugaId, 'inspektor' => $i['id']])) ?>" class="bg-white border border-gray-200 hover:border-mobiRed rounded-3xl p-5 shadow-sm transition">
                    <p class="font-bold text-gray-900"><?= e($i['first_name'] . ' ' . $i['last_name']) ?></p>
                    <p class="text-sm text-mobiTextMuted"><?= e($i['institution']) ?></p>
                    <?php if ($i['description']): ?>
                        <p class="text-xs text-mobiTextMuted mt-2"><?= e($i['description']) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

<?php elseif ($krok === 2 || $krok === 3): ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm" aria-labelledby="krok-tytul">
        <p class="text-sm text-mobiTextMuted mb-1">
            Inspektor: <strong class="text-gray-900"><?= e($inspektor['first_name'] . ' ' . $inspektor['last_name']) ?></strong>
            · <a href="<?= e(adresKroku(['usluga' => $uslugaId])) ?>" class="text-mobiRed font-semibold">zmień</a>
        </p>
        <h2 id="krok-tytul" class="text-lg font-bold text-gray-900 mb-4">Wybierz dzień i godzinę</h2>

        <div class="flex gap-2 overflow-x-auto pb-2 mb-4" aria-label="Najbliższe dni">
            <?php for ($d = 0; $d < 14; $d++): ?>
                <?php
                $dzien = date('Y-m-d', strtotime("+$d days"));
                $ile = count(wolneGodziny($inspektorId, $dzien, (int) $usluga['duration_minutes'], $klientId));
                $aktywny = $dzien === $data;
                ?>
                <a href="<?= e(adresKroku(['usluga' => $uslugaId, 'inspektor' => $inspektorId, 'data' => $dzien])) ?>"
                   data-dzien="<?= e($dzien) ?>"
                   class="shrink-0 w-16 text-center rounded-2xl border px-2 py-2 transition <?= $aktywny ? 'bg-mobiRed text-white border-mobiRed' : ($ile ? 'bg-white border-gray-200 hover:border-mobiRed' : 'bg-gray-50 border-gray-100 text-gray-400') ?>"
                   <?= $aktywny ? 'aria-current="date"' : '' ?>>
                    <span class="block text-xs"><?= e($dniTygodnia[(int) date('N', strtotime($dzien))]) ?></span>
                    <span class="block text-lg font-extrabold"><?= e(date('j', strtotime($dzien))) ?></span>
                    <span class="block text-[10px]"><?= $ile ? $ile . ' wolnych' : 'brak' ?></span>
                </a>
            <?php endfor; ?>
        </div>

        <form method="get" action="<?= e(url('rezerwacja.php')) ?>" class="flex flex-wrap items-end gap-3 mb-5"
              data-terminy="<?= e(adresKroku(['usluga' => $uslugaId, 'inspektor' => $inspektorId, 'json' => 1])) ?>">
            <input type="hidden" name="usluga" value="<?= (int) $uslugaId ?>">
            <input type="hidden" name="inspektor" value="<?= (int) $inspektorId ?>">
            <div>
                <label for="data" class="block text-xs font-semibold mb-1">Inna data</label>
                <input type="date" id="data" name="data" value="<?= e($data ?? '') ?>" required
                       min="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d', strtotime('+' . REZERWACJA_DNI_DO_PRZODU . ' days'))) ?>" class="pole">
            </div>
            <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">Pokaż godziny</button>
        </form>

        <div id="godziny" aria-live="polite">
            <?php if ($data !== null): ?>
                <h3 class="text-sm font-semibold mb-2">Wolne godziny <?= e(date('d.m.Y', strtotime($data))) ?></h3>
                <?php if (!$wolne): ?>
                    <p class="text-sm text-mobiTextMuted">W tym dniu nie ma wolnych terminów. Wybierz inny dzień.</p>
                <?php else: ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($wolne as $g): ?>
                            <a href="<?= e(adresKroku(['usluga' => $uslugaId, 'inspektor' => $inspektorId, 'data' => $data, 'godzina' => $g])) ?>"
                               class="px-4 py-2 rounded-xl border border-gray-200 hover:border-mobiRed hover:text-mobiRed font-semibold text-sm"><?= e($g) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-sm text-mobiTextMuted">Wybierz dzień, aby zobaczyć wolne godziny.</p>
            <?php endif; ?>
        </div>
    </section>

<?php else: ?>
    <?php $koniec = naGodzine(naMinuty($godzina) + (int) $usluga['duration_minutes']); ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm max-w-xl" aria-labelledby="krok-tytul">
        <h2 id="krok-tytul" class="text-lg font-bold text-gray-900 mb-4">Sprawdź i potwierdź</h2>
        <dl class="text-sm space-y-2 mb-5">
            <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Konsultacja</dt><dd class="font-semibold text-right"><?= e($usluga['name']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Inspektor</dt><dd class="font-semibold text-right"><?= e($inspektor['first_name'] . ' ' . $inspektor['last_name']) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Termin</dt><dd class="font-semibold text-right"><?= e(date('d.m.Y', strtotime($data))) ?>, <?= e($godzina) ?>–<?= e($koniec) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-mobiTextMuted">Cena</dt><dd class="font-semibold"><?= e(cena($usluga['price'])) ?></dd></div>
        </dl>

        <form method="post" action="<?= e(url('rezerwacja.php')) ?>" class="space-y-4">
            <?= csrfPole() ?>
            <input type="hidden" name="usluga" value="<?= (int) $uslugaId ?>">
            <input type="hidden" name="inspektor" value="<?= (int) $inspektorId ?>">
            <input type="hidden" name="data" value="<?= e($data) ?>">
            <input type="hidden" name="godzina" value="<?= e($godzina) ?>">
            <div>
                <label for="komentarz" class="block text-sm font-semibold mb-2">Czego dotyczy sprawa? (opcjonalnie)</label>
                <textarea id="komentarz" name="komentarz" rows="3" maxlength="1000" class="pole"></textarea>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">Potwierdzam rezerwację</button>
                <a href="<?= e(adresKroku(['usluga' => $uslugaId, 'inspektor' => $inspektorId, 'data' => $data])) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold px-5 py-3 rounded-xl border border-gray-200">Zmień godzinę</a>
            </div>
        </form>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
