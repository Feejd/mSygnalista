<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/zgloszenia.php';
header('Cache-Control: no-store');

// Zgłoszenie nie wymaga konta. Nie zapisujemy IP ani danych zgłaszającego.

const ODSTEP_MIEDZY_ZGLOSZENIAMI = 60; // sekund, prosta ochrona przed zalewem zgłoszeń

$kategorie = db()->query('SELECT id, name, description FROM report_categories WHERE is_active = 1 ORDER BY name')->fetchAll();
$idKategorii = array_map('intval', array_column($kategorie, 'id'));

$dane = ['kategoria' => '', 'tytul' => '', 'opis' => '', 'kiedy' => '', 'gdzie' => ''];
$bledy = [];
$bledyPlikow = [];

// token po udanym wysłaniu - pokazywany tylko raz
$nowyToken = $_SESSION['nowy_token'] ?? null;
unset($_SESSION['nowy_token']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // przy zbyt dużym pliku PHP odrzuca całe żądanie i $_POST jest pusty
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $bledy['formularz'] = 'Przesłane pliki są za duże. Każdy plik może mieć najwyżej 10 MB.';
    } elseif (!csrfPoprawny()) {
        http_response_code(403);
        $bledy['formularz'] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (time() - ($_SESSION['ostatnie_zgloszenie'] ?? 0) < ODSTEP_MIEDZY_ZGLOSZENIAMI) {
        $bledy['formularz'] = 'Wysłano już zgłoszenie przed chwilą. Poczekaj minutę przed kolejnym.';
    } else {
        $dane = [
            'kategoria' => pole('kategoria'),
            'tytul' => pole('tytul'),
            'opis' => pole('opis'),
            'kiedy' => pole('kiedy'),
            'gdzie' => pole('gdzie'),
        ];

        $kategoriaId = filter_var($dane['kategoria'], FILTER_VALIDATE_INT);
        if (!in_array($kategoriaId, $idKategorii, true)) {
            $bledy['kategoria'] = 'Wybierz rodzaj sprawy.';
        }
        if (mb_strlen($dane['tytul']) < 5 || mb_strlen($dane['tytul']) > 180) {
            $bledy['tytul'] = 'Tytuł musi mieć od 5 do 180 znaków.';
        }
        if (mb_strlen($dane['opis']) < 20 || mb_strlen($dane['opis']) > 5000) {
            $bledy['opis'] = 'Opisz sprawę w co najmniej 20 znakach (maks. 5000).';
        }
        if (mb_strlen($dane['gdzie']) > 255) {
            $bledy['gdzie'] = 'Miejsce może mieć najwyżej 255 znaków.';
        }

        $kiedy = null;
        if ($dane['kiedy'] !== '') {
            $data = DateTime::createFromFormat('!Y-m-d\TH:i', $dane['kiedy']);
            if ($data === false || $data->format('Y-m-d\TH:i') !== $dane['kiedy'] || $data > new DateTime()) {
                $bledy['kiedy'] = 'Podaj poprawną datę zdarzenia, nie z przyszłości.';
            } else {
                $kiedy = $data->format('Y-m-d H:i:s');
            }
        }

        [$zalaczniki, $bledyPlikow] = sprawdzZalaczniki($_FILES['zalaczniki'] ?? []);

        if (!$bledy && !$bledyPlikow) {
            $token = zapiszZgloszenie(
                ['kategoria' => $kategoriaId, 'tytul' => $dane['tytul'], 'opis' => $dane['opis'], 'kiedy' => $kiedy, 'gdzie' => $dane['gdzie']],
                $zalaczniki
            );
            $_SESSION['ostatnie_zgloszenie'] = time();
            $_SESSION['nowy_token'] = $token;
            przekieruj('zgloszenie.php');
        }

        if (!isset($bledy['formularz'])) {
            $bledy['formularz'] = 'Popraw zaznaczone pola. Załączniki trzeba wybrać ponownie.';
        }
    }
}

$tytul = 'Nowe zgłoszenie';
require __DIR__ . '/../includes/header.php';
?>
<a href="<?= e(url('index.php')) ?>" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-4">
    <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Strona główna
</a>

<?php if ($nowyToken !== null): ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm max-w-xl" aria-labelledby="token-tytul">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4">
            <i aria-hidden="true" class="fa-solid fa-check text-xl"></i>
        </div>
        <h1 id="token-tytul" class="text-2xl font-extrabold text-gray-900 mb-2">Zgłoszenie przyjęte</h1>
        <p class="text-sm text-mobiTextMuted mb-5">To Twój token. Zapisz go teraz - <strong>nie pokażemy go ponownie</strong> i nie da się go odzyskać. Token pozwala sprawdzić, co dzieje się ze sprawą.</p>
        <p class="font-mono text-lg sm:text-xl font-bold tracking-wider bg-gray-50 border border-gray-200 rounded-2xl px-4 py-4 text-center break-all select-all" id="token"><?= e($nowyToken) ?></p>
        <div class="flex flex-wrap gap-3 mt-5">
            <button type="button" data-kopiuj="token" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">
                <i aria-hidden="true" class="fa-regular fa-copy mr-1"></i> Kopiuj token
            </button>
            <a href="<?= e(url('status.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-semibold px-5 py-3 rounded-xl border border-gray-200">Sprawdź status</a>
        </div>
    </section>
<?php else: ?>
    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-1">Zgłoś nieprawidłowość</h1>
    <p class="text-mobiTextMuted mb-6 max-w-2xl">Nie musisz zakładać konta ani podawać danych osobowych. Nie zapisujemy Twojego adresu IP. Po wysłaniu dostaniesz token do sprawdzania statusu sprawy.</p>

    <section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm max-w-2xl">
        <?php if (isset($bledy['formularz'])): ?>
            <p role="alert" class="komunikat komunikat-blad mb-5"><?= e($bledy['formularz']) ?></p>
        <?php endif; ?>
        <?php foreach ($bledyPlikow as $b): ?>
            <p class="komunikat komunikat-blad mb-3"><?= e($b) ?></p>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('zgloszenie.php')) ?>" enctype="multipart/form-data" class="space-y-5">
            <?= csrfPole() ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= ZALACZNIK_MAX_BAJTOW ?>">

            <fieldset>
                <legend class="block text-sm font-semibold mb-2">Rodzaj sprawy</legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <?php foreach ($kategorie as $k): ?>
                        <label class="flex items-start gap-2 border border-gray-200 rounded-xl px-3 py-2 text-sm has-[:checked]:border-mobiRed has-[:checked]:bg-red-50">
                            <input type="radio" name="kategoria" value="<?= (int) $k['id'] ?>" required class="mt-1 accent-mobiRed" <?= $dane['kategoria'] === (string) $k['id'] ? 'checked' : '' ?>>
                            <span><?= e($k['name']) ?><span class="block text-xs text-mobiTextMuted"><?= e($k['description']) ?></span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($bledy['kategoria'])): ?><p class="blad-pola"><?= e($bledy['kategoria']) ?></p><?php endif; ?>
            </fieldset>

            <?= poleFormularza('tytul', 'Krótki tytuł', $dane['tytul'], $bledy, 'text', 'required minlength="5" maxlength="180" placeholder="np. Brak wypłaty za październik"') ?>

            <div>
                <label for="pole-opis" class="block text-sm font-semibold mb-2">Opis sprawy</label>
                <textarea id="pole-opis" name="opis" rows="6" minlength="20" maxlength="5000" required class="pole"
                    placeholder="Co się stało, kto był zaangażowany, czy są świadkowie lub dokumenty?"
                    <?= isset($bledy['opis']) ? 'aria-invalid="true"' : '' ?>><?= e($dane['opis']) ?></textarea>
                <?php if (isset($bledy['opis'])): ?><p class="blad-pola"><?= e($bledy['opis']) ?></p><?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <?= poleFormularza('kiedy', 'Kiedy to się stało? (opcjonalnie)', $dane['kiedy'], $bledy, 'datetime-local', 'max="' . e(date('Y-m-d\TH:i')) . '"') ?>
                <?= poleFormularza('gdzie', 'Miejsce (opcjonalnie)', $dane['gdzie'], $bledy, 'text', 'maxlength="255" placeholder="np. ul. Długa 5, Siedlce"') ?>
            </div>

            <div>
                <label for="zalaczniki" class="block text-sm font-semibold mb-2">Załączniki (opcjonalnie)</label>
                <input type="file" id="zalaczniki" name="zalaczniki[]" multiple accept=".pdf,.jpg,.jpeg,.png,.mp4,application/pdf,image/jpeg,image/png,video/mp4"
                       class="block w-full text-sm file:mr-3 file:px-4 file:py-2 file:rounded-xl file:border-0 file:bg-gray-100 file:font-semibold">
                <p class="text-xs text-mobiTextMuted mt-1">Do <?= ZALACZNIKI_MAX_PLIKOW ?> plików: PDF, JPG, PNG lub MP4, każdy do 10 MB. Usuń z nich dane, których nie chcesz ujawniać (np. metadane zdjęć).</p>
            </div>

            <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">Wyślij zgłoszenie</button>
        </form>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
