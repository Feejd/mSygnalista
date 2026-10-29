<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

function pobierzKategorie(int $id): ?array
{
    $zapytanie = db()->prepare('SELECT id, name, description, is_active FROM service_categories WHERE id = :id');
    $zapytanie->execute(['id' => $id]);
    return $zapytanie->fetch() ?: null;
}

$bledy = [];
$dane = ['nazwa' => '', 'opis' => ''];
$edytowana = null;

// tryb edycji: kategorie.php?edytuj=3
if (isset($_GET['edytuj'])) {
    $id = filter_var($_GET['edytuj'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $edytowana = $id ? pobierzKategorie($id) : null;

    if ($edytowana === null) {
        bladStrony(404, 'Nie znaleziono kategorii.');
    }
    $dane = ['nazwa' => $edytowana['name'], 'opis' => (string) $edytowana['description']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $akcja = pole('akcja');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

    if (in_array($akcja, ['zapisz', 'przelacz', 'usun'], true) && $id !== null && pobierzKategorie($id) === null) {
        bladStrony(404, 'Nie znaleziono kategorii.');
    }

    if ($akcja === 'dodaj' || $akcja === 'zapisz') {
        $dane = ['nazwa' => pole('nazwa'), 'opis' => pole('opis')];

        if ($dane['nazwa'] === '') {
            $bledy['nazwa'] = 'Podaj nazwę kategorii.';
        } elseif (mb_strlen($dane['nazwa']) < 2 || mb_strlen($dane['nazwa']) > 120) {
            $bledy['nazwa'] = 'Nazwa musi mieć od 2 do 120 znaków.';
        }
        if (mb_strlen($dane['opis']) > 1000) {
            $bledy['opis'] = 'Opis może mieć najwyżej 1000 znaków.';
        }

        if (!$bledy) {
            try {
                if ($akcja === 'dodaj') {
                    db()->prepare('INSERT INTO service_categories (name, description) VALUES (:nazwa, :opis)')
                        ->execute(['nazwa' => $dane['nazwa'], 'opis' => $dane['opis'] !== '' ? $dane['opis'] : null]);
                    $noweId = (int) db()->lastInsertId();
                    zapiszAudyt('category.create', 'service_categories', $noweId, 'Dodanie kategorii: ' . $dane['nazwa']);
                    komunikat('sukces', 'Dodano kategorię „' . $dane['nazwa'] . '”.');
                } else {
                    db()->prepare('UPDATE service_categories SET name = :nazwa, description = :opis WHERE id = :id')
                        ->execute(['nazwa' => $dane['nazwa'], 'opis' => $dane['opis'] !== '' ? $dane['opis'] : null, 'id' => $id]);
                    zapiszAudyt('category.update', 'service_categories', $id, 'Edycja kategorii: ' . $dane['nazwa']);
                    komunikat('sukces', 'Zapisano zmiany.');
                }
                przekieruj('admin/kategorie.php');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') {
                    throw $ex;
                }
                $bledy['nazwa'] = 'Kategoria o tej nazwie już istnieje.';
            }
        }

        if ($akcja === 'zapisz') {
            $edytowana = pobierzKategorie($id);
        }
    } elseif ($akcja === 'przelacz' && $id !== null) {
        db()->prepare('UPDATE service_categories SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
        $kategoria = pobierzKategorie($id);
        $wlaczona = (int) $kategoria['is_active'] === 1;
        zapiszAudyt('category.toggle', 'service_categories', $id,
            ($wlaczona ? 'Aktywacja' : 'Dezaktywacja') . ' kategorii: ' . $kategoria['name']);
        komunikat('sukces', 'Kategoria „' . $kategoria['name'] . '” jest teraz ' . ($wlaczona ? 'aktywna.' : 'ukryta.'));
        przekieruj('admin/kategorie.php');
    } elseif ($akcja === 'usun' && $id !== null) {
        // usuwamy tylko pustą kategorię - inaczej usługi straciłyby kategorię
        $ile = db()->prepare('SELECT COUNT(*) FROM services WHERE category_id = :id');
        $ile->execute(['id' => $id]);

        if ((int) $ile->fetchColumn() > 0) {
            komunikat('blad', 'Nie można usunąć kategorii, do której są przypisane usługi. Możesz ją ukryć.');
        } else {
            $kategoria = pobierzKategorie($id);
            db()->prepare('DELETE FROM service_categories WHERE id = :id')->execute(['id' => $id]);
            zapiszAudyt('category.delete', 'service_categories', $id, 'Usunięcie kategorii: ' . $kategoria['name']);
            komunikat('sukces', 'Usunięto kategorię „' . $kategoria['name'] . '”.');
        }
        przekieruj('admin/kategorie.php');
    } else {
        bladStrony(400, 'Nieznana operacja.');
    }
}

$kategorie = db()->query(
    'SELECT c.id, c.name, c.description, c.is_active, COUNT(s.id) AS liczba_uslug
     FROM service_categories c
     LEFT JOIN services s ON s.category_id = c.id
     GROUP BY c.id
     ORDER BY c.name'
)->fetchAll();

$tytul = 'Kategorie';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Kategorie usług</h1>
<p class="text-mobiTextMuted mb-6">Kategorie grupują konsultacje w ofercie.</p>

<?= menuAdmina('kategorie.php') ?>

<section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm mb-6" aria-labelledby="form-tytul">
    <h2 id="form-tytul" class="text-lg font-bold text-gray-900 mb-4">
        <?= $edytowana ? 'Edycja kategorii' : 'Nowa kategoria' ?>
    </h2>

    <form method="post" action="<?= e(url('admin/kategorie.php' . ($edytowana ? '?edytuj=' . $edytowana['id'] : ''))) ?>" class="space-y-5">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="<?= $edytowana ? 'zapisz' : 'dodaj' ?>">
        <?php if ($edytowana): ?>
            <input type="hidden" name="id" value="<?= (int) $edytowana['id'] ?>">
        <?php endif; ?>

        <?= poleFormularza('nazwa', 'Nazwa', $dane['nazwa'], $bledy, 'text', 'required maxlength="120"') ?>
        <div>
            <label for="pole-opis" class="block text-sm font-semibold mb-2">Opis (opcjonalnie)</label>
            <textarea id="pole-opis" name="opis" rows="3" maxlength="1000" class="pole"
                <?= isset($bledy['opis']) ? 'aria-invalid="true"' : '' ?>><?= e($dane['opis']) ?></textarea>
            <?php if (isset($bledy['opis'])): ?>
                <p class="blad-pola"><?= e($bledy['opis']) ?></p>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">
                <?= $edytowana ? 'Zapisz zmiany' : 'Dodaj kategorię' ?>
            </button>
            <?php if ($edytowana): ?>
                <a href="<?= e(url('admin/kategorie.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold px-5 py-3 rounded-xl border border-gray-200 transition">Anuluj</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-labelledby="lista-tytul">
    <h2 id="lista-tytul" class="text-lg font-bold text-gray-900 p-5 sm:p-6 pb-0 sm:pb-0">Lista kategorii</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm mt-4">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-5 py-3 min-w-[9rem]">Nazwa</th>
                    <th scope="col" class="px-5 py-3 hidden sm:table-cell">Usługi</th>
                    <th scope="col" class="px-5 py-3">Status</th>
                    <th scope="col" class="px-5 py-3 text-right">Akcje</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($kategorie as $k): ?>
                <tr>
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-900"><?= e($k['name']) ?></p>
                        <?php if ($k['description']): ?>
                            <p class="text-xs text-mobiTextMuted"><?= e($k['description']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-mobiTextMuted sm:hidden">Usługi: <?= (int) $k['liczba_uslug'] ?></p>
                    </td>
                    <td class="px-5 py-4 hidden sm:table-cell"><?= (int) $k['liczba_uslug'] ?></td>
                    <td class="px-5 py-4">
                        <?php if ((int) $k['is_active'] === 1): ?>
                            <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2 py-1 rounded-lg">Aktywna</span>
                        <?php else: ?>
                            <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-2 py-1 rounded-lg">Ukryta</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap justify-end gap-2">
                            <a href="<?= e(url('admin/kategorie.php?edytuj=' . $k['id'])) ?>" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300">Edytuj</a>
                            <form method="post" action="<?= e(url('admin/kategorie.php')) ?>">
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="przelacz">
                                <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300">
                                    <?= (int) $k['is_active'] === 1 ? 'Ukryj' : 'Pokaż' ?>
                                </button>
                            </form>
                            <?php if ((int) $k['liczba_uslug'] === 0): ?>
                                <form method="post" action="<?= e(url('admin/kategorie.php')) ?>" data-potwierdz="Usunąć kategorię „<?= e($k['name']) ?>”?">
                                    <?= csrfPole() ?>
                                    <input type="hidden" name="akcja" value="usun">
                                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50">Usuń</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
