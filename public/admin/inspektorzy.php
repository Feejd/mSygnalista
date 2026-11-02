<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

function pobierzInspektora(int $id): ?array
{
    $zapytanie = db()->prepare(
        'SELECT i.id, i.user_id, i.employee_number, i.institution, i.description, i.is_active,
                u.first_name, u.last_name, u.email
         FROM inspectors i JOIN users u ON u.id = i.user_id
         WHERE i.id = :id'
    );
    $zapytanie->execute(['id' => $id]);
    return $zapytanie->fetch() ?: null;
}

function walidujInspektora(array $dane): array
{
    $bledy = [];
    if (!preg_match('/^[A-Za-z0-9\-\/]{3,30}$/', $dane['numer'])) {
        $bledy['numer'] = 'Numer służbowy: 3-30 znaków (litery, cyfry, myślnik, ukośnik).';
    }
    if (mb_strlen($dane['instytucja']) < 3 || mb_strlen($dane['instytucja']) > 150) {
        $bledy['instytucja'] = 'Nazwa jednostki musi mieć od 3 do 150 znaków.';
    }
    if (mb_strlen($dane['opis']) > 1000) {
        $bledy['opis'] = 'Opis może mieć najwyżej 1000 znaków.';
    }
    return $bledy;
}

$wszystkieUslugi = db()->query(
    'SELECT s.id, s.name, s.is_active, c.name AS kategoria
     FROM services s JOIN service_categories c ON c.id = s.category_id
     ORDER BY c.name, s.name'
)->fetchAll();
$idUslug = array_map('intval', array_column($wszystkieUslugi, 'id'));

$bledy = [];
$dane = ['email' => '', 'numer' => '', 'instytucja' => '', 'opis' => ''];
$wybraneUslugi = [];
$edytowany = null;

if (isset($_GET['edytuj'])) {
    $id = filter_var($_GET['edytuj'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $edytowany = $id ? pobierzInspektora($id) : null;

    if ($edytowany === null) {
        bladStrony(404, 'Nie znaleziono inspektora.');
    }
    $dane = [
        'email' => $edytowany['email'],
        'numer' => $edytowany['employee_number'],
        'instytucja' => $edytowany['institution'],
        'opis' => (string) $edytowany['description'],
    ];
    $przypisane = db()->prepare('SELECT service_id FROM inspector_services WHERE inspector_id = :id AND is_active = 1');
    $przypisane->execute(['id' => $edytowany['id']]);
    $wybraneUslugi = array_map('intval', $przypisane->fetchAll(PDO::FETCH_COLUMN));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $akcja = pole('akcja');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $inspektor = $id !== null ? pobierzInspektora($id) : null;

    if (in_array($akcja, ['zapisz', 'przelacz'], true) && $inspektor === null) {
        bladStrony(404, 'Nie znaleziono inspektora.');
    }

    if ($akcja === 'dodaj' || $akcja === 'zapisz') {
        $dane = [
            'email' => mb_strtolower(pole('email')),
            'numer' => strtoupper(pole('numer')),
            'instytucja' => pole('instytucja'),
            'opis' => pole('opis'),
        ];
        // tylko istniejące usługi, reszta z żądania jest ignorowana
        $zFormularza = is_array($_POST['uslugi'] ?? null) ? $_POST['uslugi'] : [];
        $wybraneUslugi = array_values(array_intersect($idUslug, array_map('intval', $zFormularza)));

        $bledy = walidujInspektora($dane);
        $uzytkownik = null;

        if ($akcja === 'dodaj') {
            // inspektorem zostaje istniejące konto - najpierw trzeba się zarejestrować
            $zapytanie = db()->prepare('SELECT id, first_name, last_name, is_active FROM users WHERE email = :email');
            $zapytanie->execute(['email' => $dane['email']]);
            $uzytkownik = $zapytanie->fetch();

            if (!$uzytkownik) {
                $bledy['email'] = 'Nie ma konta z takim adresem. Użytkownik musi się najpierw zarejestrować.';
            } elseif ((int) $uzytkownik['is_active'] !== 1) {
                $bledy['email'] = 'To konto jest zablokowane.';
            } else {
                $juz = db()->prepare('SELECT 1 FROM inspectors WHERE user_id = :id');
                $juz->execute(['id' => $uzytkownik['id']]);
                if ($juz->fetchColumn()) {
                    $bledy['email'] = 'To konto ma już profil inspektora.';
                }
            }
        }

        if (!$bledy) {
            $pdo = db();
            $pdo->beginTransaction();

            try {
                if ($akcja === 'dodaj') {
                    $pdo->prepare(
                        'INSERT INTO inspectors (user_id, employee_number, institution, description)
                         VALUES (:user, :numer, :instytucja, :opis)'
                    )->execute([
                        'user' => $uzytkownik['id'],
                        'numer' => $dane['numer'],
                        'instytucja' => $dane['instytucja'],
                        'opis' => $dane['opis'] !== '' ? $dane['opis'] : null,
                    ]);
                    $id = (int) $pdo->lastInsertId();

                    $pdo->prepare(
                        'INSERT IGNORE INTO user_roles (user_id, role_id) SELECT :user, id FROM roles WHERE code = :rola'
                    )->execute(['user' => $uzytkownik['id'], 'rola' => ROLA_INSPEKTOR]);
                } else {
                    $pdo->prepare(
                        'UPDATE inspectors SET employee_number = :numer, institution = :instytucja, description = :opis
                         WHERE id = :id'
                    )->execute([
                        'numer' => $dane['numer'],
                        'instytucja' => $dane['instytucja'],
                        'opis' => $dane['opis'] !== '' ? $dane['opis'] : null,
                        'id' => $id,
                    ]);
                }

                // relacja N:M - odznaczenie usługi tylko wyłącza przypisanie,
                // bo stare rezerwacje wskazują na parę (inspektor, usługa)
                $pdo->prepare('UPDATE inspector_services SET is_active = 0 WHERE inspector_id = :id')
                    ->execute(['id' => $id]);
                $przypisz = $pdo->prepare(
                    'INSERT INTO inspector_services (inspector_id, service_id, is_active) VALUES (:inspektor, :usluga, 1)
                     ON DUPLICATE KEY UPDATE is_active = 1'
                );
                foreach ($wybraneUslugi as $uslugaId) {
                    $przypisz->execute(['inspektor' => $id, 'usluga' => $uslugaId]);
                }

                $nazwisko = $akcja === 'dodaj'
                    ? $uzytkownik['first_name'] . ' ' . $uzytkownik['last_name']
                    : $inspektor['first_name'] . ' ' . $inspektor['last_name'];
                zapiszAudyt($akcja === 'dodaj' ? 'inspector.create' : 'inspector.update', 'inspectors', $id,
                    ($akcja === 'dodaj' ? 'Nowy inspektor: ' : 'Edycja inspektora: ') . $nazwisko
                    . ', usługi: ' . (count($wybraneUslugi) ?: 'brak'));

                $pdo->commit();
                komunikat('sukces', $akcja === 'dodaj' ? 'Dodano inspektora ' . $nazwisko . '.' : 'Zapisano zmiany.');
                przekieruj('admin/inspektorzy.php');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                if ($ex->getCode() !== '23000') {
                    throw $ex;
                }
                $bledy['numer'] = 'Ten numer służbowy jest już zajęty.';
            }
        }

        if ($akcja === 'zapisz') {
            $edytowany = $inspektor;
        }
    } elseif ($akcja === 'przelacz') {
        db()->prepare('UPDATE inspectors SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
        $wlaczony = (int) $inspektor['is_active'] === 0;
        $nazwisko = $inspektor['first_name'] . ' ' . $inspektor['last_name'];
        zapiszAudyt('inspector.toggle', 'inspectors', $id, ($wlaczony ? 'Aktywacja' : 'Dezaktywacja') . ' inspektora: ' . $nazwisko);
        komunikat('sukces', $nazwisko . ($wlaczony ? ' znowu przyjmuje wizyty.' : ' nie przyjmuje już nowych wizyt.'));
        przekieruj('admin/inspektorzy.php');
    } else {
        bladStrony(400, 'Nieznana operacja.');
    }
}

$inspektorzy = db()->query(
    'SELECT i.id, i.employee_number, i.institution, i.is_active, u.first_name, u.last_name, u.email,
            GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR \', \') AS uslugi
     FROM inspectors i
     JOIN users u ON u.id = i.user_id
     LEFT JOIN inspector_services isv ON isv.inspector_id = i.id AND isv.is_active = 1
     LEFT JOIN services s ON s.id = isv.service_id
     GROUP BY i.id
     ORDER BY u.last_name, u.first_name'
)->fetchAll();

$tytul = 'Inspektorzy';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Inspektorzy</h1>
<p class="text-mobiTextMuted mb-6">Osoby prowadzące konsultacje i usługi, które wykonują.</p>

<?= menuAdmina('inspektorzy.php') ?>

<section class="bg-white border border-gray-200 rounded-3xl p-5 sm:p-6 shadow-sm mb-6" aria-labelledby="form-tytul">
    <h2 id="form-tytul" class="text-lg font-bold text-gray-900 mb-1">
        <?= $edytowany ? 'Edycja: ' . e($edytowany['first_name'] . ' ' . $edytowany['last_name']) : 'Nowy inspektor' ?>
    </h2>
    <p class="text-sm text-mobiTextMuted mb-4">
        <?= $edytowany ? e($edytowany['email']) : 'Nadaj rolę inspektora istniejącemu kontu (użytkownik zakłada je przez rejestrację).' ?>
    </p>

    <form method="post" action="<?= e(url('admin/inspektorzy.php' . ($edytowany ? '?edytuj=' . $edytowany['id'] : ''))) ?>" class="space-y-5">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="<?= $edytowany ? 'zapisz' : 'dodaj' ?>">
        <?php if ($edytowany): ?>
            <input type="hidden" name="id" value="<?= (int) $edytowany['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <?php if (!$edytowany): ?>
                <?= poleFormularza('email', 'E-mail konta', $dane['email'], $bledy, 'email', 'required maxlength="254"') ?>
            <?php endif; ?>
            <?= poleFormularza('numer', 'Numer służbowy', $dane['numer'], $bledy, 'text', 'required maxlength="30" placeholder="np. PIP-004"') ?>
            <?= poleFormularza('instytucja', 'Jednostka', $dane['instytucja'], $bledy, 'text', 'required maxlength="150"') ?>
        </div>
        <div>
            <label for="pole-opis" class="block text-sm font-semibold mb-2">Opis (opcjonalnie)</label>
            <textarea id="pole-opis" name="opis" rows="2" maxlength="1000" class="pole"><?= e($dane['opis']) ?></textarea>
            <?php if (isset($bledy['opis'])): ?><p class="blad-pola"><?= e($bledy['opis']) ?></p><?php endif; ?>
        </div>

        <fieldset>
            <legend class="block text-sm font-semibold mb-2">Wykonywane usługi</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <?php foreach ($wszystkieUslugi as $u): ?>
                    <label class="flex items-start gap-3 text-sm border border-gray-200 rounded-xl px-3 py-2">
                        <input type="checkbox" name="uslugi[]" value="<?= (int) $u['id'] ?>" class="mt-1 accent-mobiRed"
                            <?= in_array((int) $u['id'], $wybraneUslugi, true) ? 'checked' : '' ?>>
                        <span>
                            <?= e($u['name']) ?>
                            <span class="block text-xs text-mobiTextMuted"><?= e($u['kategoria']) ?><?= (int) $u['is_active'] === 1 ? '' : ' · wyłączona' ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition">
                <?= $edytowany ? 'Zapisz zmiany' : 'Dodaj inspektora' ?>
            </button>
            <?php if ($edytowany): ?>
                <a href="<?= e(url('admin/inspektorzy.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold px-5 py-3 rounded-xl border border-gray-200 transition">Anuluj</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-labelledby="lista-tytul">
    <h2 id="lista-tytul" class="text-lg font-bold text-gray-900 p-5 sm:p-6 pb-0 sm:pb-0">Lista inspektorów</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm mt-4">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-5 py-3 min-w-[10rem]">Inspektor</th>
                    <th scope="col" class="px-5 py-3 hidden md:table-cell">Usługi</th>
                    <th scope="col" class="px-5 py-3">Status</th>
                    <th scope="col" class="px-5 py-3 text-right">Akcje</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($inspektorzy as $i): ?>
                <tr>
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-900"><?= e($i['first_name'] . ' ' . $i['last_name']) ?></p>
                        <p class="text-xs text-mobiTextMuted"><?= e($i['employee_number']) ?> · <?= e($i['institution']) ?></p>
                        <p class="text-xs text-mobiTextMuted md:hidden"><?= e($i['uslugi'] ?? 'brak usług') ?></p>
                    </td>
                    <td class="px-5 py-4 hidden md:table-cell text-xs"><?= e($i['uslugi'] ?? 'brak usług') ?></td>
                    <td class="px-5 py-4">
                        <?php if ((int) $i['is_active'] === 1): ?>
                            <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2 py-1 rounded-lg">Aktywny</span>
                        <?php else: ?>
                            <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-2 py-1 rounded-lg">Nieaktywny</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap justify-end gap-2">
                            <a href="<?= e(url('admin/inspektorzy.php?edytuj=' . $i['id'])) ?>" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300">Edytuj</a>
                            <form method="post" action="<?= e(url('admin/inspektorzy.php')) ?>"
                                <?= (int) $i['is_active'] === 1 ? 'data-potwierdz="Dezaktywować inspektora? Nie będzie przyjmował nowych wizyt."' : '' ?>>
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="przelacz">
                                <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                                <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300">
                                    <?= (int) $i['is_active'] === 1 ? 'Dezaktywuj' : 'Aktywuj' ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
