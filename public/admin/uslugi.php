<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

function pobierzUsluge(int $id): ?array
{
    $zapytanie = db()->prepare(
        'SELECT id, category_id, name, description, duration_minutes, price, is_active FROM services WHERE id = :id'
    );
    $zapytanie->execute(['id' => $id]);
    return $zapytanie->fetch() ?: null;
}

$kategorie = db()->query('SELECT id, name, is_active FROM service_categories ORDER BY name')->fetchAll();
$idKategorii = array_map('intval', array_column($kategorie, 'id'));

$bledy = [];
$dane = ['kategoria' => '', 'nazwa' => '', 'opis' => '', 'czas' => '30', 'cena' => '0.00'];
$edytowana = null;

if (isset($_GET['edytuj'])) {
    $id = filter_var($_GET['edytuj'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $edytowana = $id ? pobierzUsluge($id) : null;

    if ($edytowana === null) {
        bladStrony(404, 'Nie znaleziono usługi.');
    }
    $dane = [
        'kategoria' => (string) $edytowana['category_id'],
        'nazwa' => $edytowana['name'],
        'opis' => $edytowana['description'],
        'czas' => (string) $edytowana['duration_minutes'],
        'cena' => $edytowana['price'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $akcja = pole('akcja');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $usluga = $id !== null ? pobierzUsluge($id) : null;

    if (in_array($akcja, ['zapisz', 'przelacz', 'usun'], true) && $usluga === null) {
        bladStrony(404, 'Nie znaleziono usługi.');
    }

    if ($akcja === 'dodaj' || $akcja === 'zapisz') {
        $dane = [
            'kategoria' => pole('kategoria'),
            'nazwa' => pole('nazwa'),
            'opis' => pole('opis'),
            'czas' => pole('czas'),
            'cena' => str_replace([',', ' '], ['.', ''], pole('cena')),
        ];

        $kategoriaId = filter_var($dane['kategoria'], FILTER_VALIDATE_INT);
        if (!in_array($kategoriaId, $idKategorii, true)) {
            $bledy['kategoria'] = 'Wybierz kategorię z listy.';
        }

        if ($dane['nazwa'] === '') {
            $bledy['nazwa'] = 'Podaj nazwę usługi.';
        } elseif (mb_strlen($dane['nazwa']) < 3 || mb_strlen($dane['nazwa']) > 150) {
            $bledy['nazwa'] = 'Nazwa musi mieć od 3 do 150 znaków.';
        }

        if (mb_strlen($dane['opis']) < 10 || mb_strlen($dane['opis']) > 2000) {
            $bledy['opis'] = 'Opis musi mieć od 10 do 2000 znaków.';
        }

        // czas co 5 minut, tak jak w ograniczeniu CHECK w bazie (5-480)
        $czas = filter_var($dane['czas'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 480]]);
        if ($czas === false || $czas % 5 !== 0) {
            $bledy['czas'] = 'Czas trwania: od 5 do 480 minut, co 5 minut.';
        }

        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $dane['cena']) || (float) $dane['cena'] > 10000) {
            $bledy['cena'] = 'Podaj cenę od 0 do 10 000 zł, np. 80 albo 79,99.';
        }

        if (!$bledy) {
            $parametry = [
                'kategoria' => $kategoriaId,
                'nazwa' => $dane['nazwa'],
                'opis' => $dane['opis'],
                'czas' => $czas,
                'cena' => number_format((float) $dane['cena'], 2, '.', ''),
            ];

            try {
                if ($akcja === 'dodaj') {
                    db()->prepare(
                        'INSERT INTO services (category_id, name, description, duration_minutes, price)
                         VALUES (:kategoria, :nazwa, :opis, :czas, :cena)'
                    )->execute($parametry);
                    $noweId = (int) db()->lastInsertId();
                    zapiszAudyt('service.create', 'services', $noweId, 'Dodanie usługi: ' . $dane['nazwa']);
                    komunikat('sukces', 'Dodano usługę „' . $dane['nazwa'] . '”. Przypisz ją inspektorom w zakładce Inspektorzy.');
                } else {
                    // zmiana ceny nie rusza istniejących rezerwacji - mają własne price_at_booking
                    db()->prepare(
                        'UPDATE services SET category_id = :kategoria, name = :nazwa, description = :opis,
                                duration_minutes = :czas, price = :cena
                         WHERE id = :id'
                    )->execute($parametry + ['id' => $id]);
                    zapiszAudyt('service.update', 'services', $id, 'Edycja usługi: ' . $dane['nazwa']);
                    komunikat('sukces', 'Zapisano zmiany w usłudze.');
                }
                przekieruj('admin/uslugi.php');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') {
                    throw $ex;
                }
                $bledy['nazwa'] = 'W tej kategorii jest już usługa o takiej nazwie.';
            }
        }

        if ($akcja === 'zapisz') {
            $edytowana = $usluga;
        }
    } elseif ($akcja === 'przelacz') {
        db()->prepare('UPDATE services SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
        $wlaczona = (int) $usluga['is_active'] === 0;
        zapiszAudyt('service.toggle', 'services', $id, ($wlaczona ? 'Aktywacja' : 'Dezaktywacja') . ' usługi: ' . $usluga['name']);
        komunikat('sukces', 'Usługa „' . $usluga['name'] . '” jest teraz ' . ($wlaczona ? 'aktywna.' : 'wyłączona.'));
        przekieruj('admin/uslugi.php');
    } elseif ($akcja === 'usun') {
        // usługi z rezerwacjami nie usuwamy - historia wizyt musi zostać
        $ile = db()->prepare('SELECT COUNT(*) FROM reservations WHERE service_id = :id');
        $ile->execute(['id' => $id]);

        if ((int) $ile->fetchColumn() > 0) {
            komunikat('blad', 'Usługa ma rezerwacje, więc nie można jej usunąć. Możesz ją wyłączyć.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM inspector_services WHERE service_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM services WHERE id = :id')->execute(['id' => $id]);
            zapiszAudyt('service.delete', 'services', $id, 'Usunięcie usługi: ' . $usluga['name']);
            $pdo->commit();
            komunikat('sukces', 'Usunięto usługę „' . $usluga['name'] . '”.');
        }
        przekieruj('admin/uslugi.php');
    } else {
        bladStrony(400, 'Nieznana operacja.');
    }
}

// lista z wyszukiwaniem po nazwie i filtrem kategorii
$szukaj = is_string($_GET['szukaj'] ?? null) ? trim($_GET['szukaj']) : '';
$filtrKategorii = filter_var($_GET['kategoria'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

$sql = 'SELECT s.id, s.name, s.duration_minutes, s.price, s.is_active, c.name AS kategoria,
               (SELECT COUNT(*) FROM reservations r WHERE r.service_id = s.id) AS liczba_rezerwacji,
               (SELECT COUNT(*) FROM inspector_services isv WHERE isv.service_id = s.id AND isv.is_active = 1) AS liczba_inspektorow
        FROM services s
        JOIN service_categories c ON c.id = s.category_id
        WHERE 1 = 1';
$parametry = [];

if ($szukaj !== '') {
    $sql .= ' AND s.name LIKE :szukaj';
    $parametry['szukaj'] = '%' . addcslashes($szukaj, '%_\\') . '%';
}
if ($filtrKategorii !== null) {
    $sql .= ' AND s.category_id = :kategoria';
    $parametry['kategoria'] = $filtrKategorii;
}

$zapytanie = db()->prepare($sql . ' ORDER BY c.name, s.name');
$zapytanie->execute($parametry);
$uslugi = $zapytanie->fetchAll();

$tytul = 'Usługi';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Usługi</h1>
<p class="text-mobiTextMuted mb-6">Konsultacje widoczne w ofercie dla obywateli.</p>

<?= menuAdmina('uslugi.php') ?>

<section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm mb-6" aria-labelledby="form-tytul">
    <h2 id="form-tytul" class="text-lg font-bold text-gray-900 mb-4"><?= $edytowana ? 'Edycja usługi' : 'Nowa usługa' ?></h2>

    <form method="post" action="<?= e(url('admin/uslugi.php' . ($edytowana ? '?edytuj=' . $edytowana['id'] : ''))) ?>" class="space-y-5">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="<?= $edytowana ? 'zapisz' : 'dodaj' ?>">
        <?php if ($edytowana): ?>
            <input type="hidden" name="id" value="<?= (int) $edytowana['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="pole-kategoria" class="block text-sm font-semibold mb-2">Kategoria</label>
                <select id="pole-kategoria" name="kategoria" class="pole" required <?= isset($bledy['kategoria']) ? 'aria-invalid="true"' : '' ?>>
                    <option value="">- wybierz -</option>
                    <?php foreach ($kategorie as $k): ?>
                        <option value="<?= (int) $k['id'] ?>" <?= $dane['kategoria'] === (string) $k['id'] ? 'selected' : '' ?>>
                            <?= e($k['name']) ?><?= (int) $k['is_active'] === 1 ? '' : ' (ukryta)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($bledy['kategoria'])): ?><p class="blad-pola"><?= e($bledy['kategoria']) ?></p><?php endif; ?>
            </div>
            <?= poleFormularza('nazwa', 'Nazwa', $dane['nazwa'], $bledy, 'text', 'required maxlength="150"') ?>
            <?= poleFormularza('czas', 'Czas trwania (minuty)', $dane['czas'], $bledy, 'number', 'required min="5" max="480" step="5"') ?>
            <?= poleFormularza('cena', 'Cena (zł, 0 = bezpłatna)', $dane['cena'], $bledy, 'text', 'required inputmode="decimal" maxlength="9"') ?>
        </div>
        <div>
            <label for="pole-opis" class="block text-sm font-semibold mb-2">Opis</label>
            <textarea id="pole-opis" name="opis" rows="3" maxlength="2000" required class="pole"
                <?= isset($bledy['opis']) ? 'aria-invalid="true"' : '' ?>><?= e($dane['opis']) ?></textarea>
            <?php if (isset($bledy['opis'])): ?><p class="blad-pola"><?= e($bledy['opis']) ?></p><?php endif; ?>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">
                <?= $edytowana ? 'Zapisz zmiany' : 'Dodaj usługę' ?>
            </button>
            <?php if ($edytowana): ?>
                <a href="<?= e(url('admin/uslugi.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold px-5 py-3 rounded-xl border border-gray-200 transition">Anuluj</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-labelledby="lista-tytul">
    <div class="p-5 sm:p-6 pb-0 sm:pb-0">
        <h2 id="lista-tytul" class="text-lg font-bold text-gray-900 mb-4">Lista usług</h2>
        <form method="get" action="<?= e(url('admin/uslugi.php')) ?>" class="flex flex-wrap gap-3" role="search">
            <label for="szukaj" class="sr-only">Szukaj usługi</label>
            <input type="search" id="szukaj" name="szukaj" value="<?= e($szukaj) ?>" placeholder="Szukaj po nazwie" maxlength="150" class="pole flex-1 min-w-[10rem]">
            <label for="filtr-kategoria" class="sr-only">Kategoria</label>
            <select id="filtr-kategoria" name="kategoria" class="pole w-auto">
                <option value="">Wszystkie kategorie</option>
                <?php foreach ($kategorie as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $filtrKategorii === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">Filtruj</button>
        </form>
    </div>

    <?php if (!$uslugi): ?>
        <p class="p-5 sm:p-6 text-sm text-mobiTextMuted">Brak usług spełniających kryteria.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm mt-4">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-5 py-3 min-w-[10rem]">Usługa</th>
                    <th scope="col" class="px-5 py-3 hidden sm:table-cell">Czas / cena</th>
                    <th scope="col" class="px-5 py-3 hidden md:table-cell">Inspektorzy</th>
                    <th scope="col" class="px-5 py-3">Status</th>
                    <th scope="col" class="px-5 py-3 text-right">Akcje</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($uslugi as $u): ?>
                <tr>
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-900"><?= e($u['name']) ?></p>
                        <p class="text-xs text-mobiTextMuted"><?= e($u['kategoria']) ?></p>
                        <p class="text-xs text-mobiTextMuted sm:hidden"><?= (int) $u['duration_minutes'] ?> min, <?= e(cena($u['price'])) ?></p>
                    </td>
                    <td class="px-5 py-4 hidden sm:table-cell whitespace-nowrap"><?= (int) $u['duration_minutes'] ?> min<br><span class="text-mobiTextMuted"><?= e(cena($u['price'])) ?></span></td>
                    <td class="px-5 py-4 hidden md:table-cell"><?= (int) $u['liczba_inspektorow'] ?></td>
                    <td class="px-5 py-4">
                        <?php if ((int) $u['is_active'] === 1): ?>
                            <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2 py-1 rounded-lg">Aktywna</span>
                        <?php else: ?>
                            <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-2 py-1 rounded-lg">Wyłączona</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap justify-end gap-2">
                            <a href="<?= e(url('admin/uslugi.php?edytuj=' . $u['id'])) ?>" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300">Edytuj</a>
                            <form method="post" action="<?= e(url('admin/uslugi.php')) ?>">
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="przelacz">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300">
                                    <?= (int) $u['is_active'] === 1 ? 'Wyłącz' : 'Włącz' ?>
                                </button>
                            </form>
                            <?php if ((int) $u['liczba_rezerwacji'] === 0): ?>
                                <form method="post" action="<?= e(url('admin/uslugi.php')) ?>" data-potwierdz="Usunąć usługę „<?= e($u['name']) ?>”?">
                                    <?= csrfPole() ?>
                                    <input type="hidden" name="akcja" value="usun">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
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
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
