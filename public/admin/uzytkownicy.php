<?php
require_once __DIR__ . '/../../includes/init.php';
header('Cache-Control: no-store');

wymagajRoli(ROLA_ADMIN);

const UZYTKOWNIKOW_NA_STRONE = 20;
const NAZWY_ROL = [ROLA_OBYWATEL => 'Obywatel', ROLA_INSPEKTOR => 'Inspektor', ROLA_ADMIN => 'Administrator'];

$ja = (int) aktualnyUzytkownik()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfPoprawny()) {
        bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
    }

    $akcja = pole('akcja');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $powrot = is_string($_POST['powrot'] ?? null) && str_starts_with($_POST['powrot'], '?') ? $_POST['powrot'] : '';

    $zapytanie = db()->prepare('SELECT id, first_name, last_name, is_active FROM users WHERE id = :id');
    $zapytanie->execute(['id' => $id]);
    $kto = $zapytanie->fetch();

    if (!$kto) {
        bladStrony(404, 'Nie znaleziono użytkownika.');
    }

    $nazwa = $kto['first_name'] . ' ' . $kto['last_name'];

    // własnego konta nie można zablokować ani odebrać sobie admina - inaczej można zostać bez administratora
    if ($id === $ja) {
        komunikat('blad', 'Nie możesz zmieniać blokady ani ról własnego konta.');
    } elseif ($akcja === 'blokada') {
        db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
        $zablokowany = (int) $kto['is_active'] === 1;
        zapiszAudyt($zablokowany ? 'user.block' : 'user.unblock', 'users', $id,
            ($zablokowany ? 'Zablokowanie konta: ' : 'Odblokowanie konta: ') . $nazwa);
        komunikat('sukces', $zablokowany
            ? 'Zablokowano konto ' . $nazwa . '. Użytkownik zostanie wylogowany przy następnym kliknięciu.'
            : 'Odblokowano konto ' . $nazwa . '.');
    } elseif ($akcja === 'admin') {
        $maAdmina = db()->prepare(
            'SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id AND r.code = :rola'
        );
        $maAdmina->execute(['id' => $id, 'rola' => ROLA_ADMIN]);

        if ($maAdmina->fetchColumn()) {
            db()->prepare(
                'DELETE ur FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id AND r.code = :rola'
            )->execute(['id' => $id, 'rola' => ROLA_ADMIN]);
            zapiszAudyt('user.role_revoke', 'users', $id, 'Odebranie roli administratora: ' . $nazwa);
            komunikat('sukces', $nazwa . ' nie jest już administratorem.');
        } else {
            db()->prepare('INSERT INTO user_roles (user_id, role_id) SELECT :id, id FROM roles WHERE code = :rola')
                ->execute(['id' => $id, 'rola' => ROLA_ADMIN]);
            zapiszAudyt('user.role_grant', 'users', $id, 'Nadanie roli administratora: ' . $nazwa);
            komunikat('sukces', $nazwa . ' jest teraz administratorem.');
        }
    } else {
        bladStrony(400, 'Nieznana operacja.');
    }

    przekieruj('admin/uzytkownicy.php' . $powrot);
}

$szukaj = is_string($_GET['szukaj'] ?? null) ? trim($_GET['szukaj']) : '';
$rola = is_string($_GET['rola'] ?? null) && array_key_exists($_GET['rola'], NAZWY_ROL) ? $_GET['rola'] : '';
$tylkoZablokowani = ($_GET['zablokowani'] ?? '') === '1';

$warunki = [];
$parametry = [];

if ($szukaj !== '') {
    $wzor = '%' . addcslashes($szukaj, '%_\\') . '%';
    $warunki[] = '(CONCAT(u.first_name, \' \', u.last_name) LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)';
    $parametry += ['s1' => $wzor, 's2' => $wzor, 's3' => $wzor];
}
if ($rola !== '') {
    $warunki[] = 'EXISTS (SELECT 1 FROM user_roles ur2 JOIN roles r2 ON r2.id = ur2.role_id WHERE ur2.user_id = u.id AND r2.code = :rola)';
    $parametry['rola'] = $rola;
}
if ($tylkoZablokowani) {
    $warunki[] = 'u.is_active = 0';
}
$gdzie = $warunki ? 'WHERE ' . implode(' AND ', $warunki) : '';

$ile = db()->prepare("SELECT COUNT(*) FROM users u $gdzie");
$ile->execute($parametry);
$wszystkich = (int) $ile->fetchColumn();

$stron = max(1, (int) ceil($wszystkich / UZYTKOWNIKOW_NA_STRONE));
$strona = filter_var($_GET['strona'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$strona = min($strona, $stron);

$lista = db()->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.is_active, u.created_at,
            GROUP_CONCAT(r.code ORDER BY r.id) AS role,
            (SELECT COUNT(*) FROM reservations rz WHERE rz.client_id = u.id) AS rezerwacje
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     $gdzie
     GROUP BY u.id
     ORDER BY u.last_name, u.first_name, u.id
     LIMIT " . UZYTKOWNIKOW_NA_STRONE . ' OFFSET ' . (($strona - 1) * UZYTKOWNIKOW_NA_STRONE)
);
$lista->execute($parametry);
$uzytkownicy = $lista->fetchAll();

$filtry = array_filter(['szukaj' => $szukaj, 'rola' => $rola, 'zablokowani' => $tylkoZablokowani ? '1' : '']);
$zapytanieStrony = fn (int $s): string => '?' . http_build_query($filtry + ($s > 1 ? ['strona' => $s] : []));
$powrot = $zapytanieStrony($strona);

$tytul = 'Użytkownicy';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Użytkownicy</h1>
<p class="text-mobiTextMuted mb-6">Konta w systemie. Zablokowany użytkownik nie może się zalogować.</p>

<?= menuAdmina('uzytkownicy.php') ?>

<form method="get" action="<?= e(url('admin/uzytkownicy.php')) ?>" class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm mb-4 flex flex-wrap gap-3 items-end" role="search">
    <div class="flex-1 min-w-[12rem]">
        <label for="szukaj" class="block text-xs font-semibold mb-1">Szukaj</label>
        <input type="search" id="szukaj" name="szukaj" value="<?= e($szukaj) ?>" maxlength="100" placeholder="Imię, nazwisko, e-mail lub telefon" class="pole">
    </div>
    <div>
        <label for="rola" class="block text-xs font-semibold mb-1">Rola</label>
        <select id="rola" name="rola" class="pole">
            <option value="">Wszystkie</option>
            <?php foreach (NAZWY_ROL as $kod => $nazwa): ?>
                <option value="<?= e($kod) ?>" <?= $rola === $kod ? 'selected' : '' ?>><?= e($nazwa) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <label class="flex items-center gap-2 text-sm py-3">
        <input type="checkbox" name="zablokowani" value="1" class="accent-mobiRed" <?= $tylkoZablokowani ? 'checked' : '' ?>>
        Tylko zablokowane
    </label>
    <button type="submit" class="bg-gray-900 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-3 rounded-xl">Filtruj</button>
</form>

<section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-label="Lista użytkowników">
    <p class="px-5 pt-5 text-sm text-mobiTextMuted">Znaleziono: <strong class="text-gray-900"><?= $wszystkich ?></strong></p>
    <?php if (!$uzytkownicy): ?>
        <p class="p-5 text-sm text-mobiTextMuted">Brak użytkowników spełniających kryteria.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm mt-3">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-5 py-3 min-w-[11rem]">Użytkownik</th>
                    <th scope="col" class="px-5 py-3 hidden md:table-cell">Role</th>
                    <th scope="col" class="px-5 py-3 hidden sm:table-cell">Wizyty</th>
                    <th scope="col" class="px-5 py-3">Status</th>
                    <th scope="col" class="px-5 py-3 text-right">Akcje</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($uzytkownicy as $u): ?>
                <?php
                $role = $u['role'] ? explode(',', $u['role']) : [];
                $nazwyRol = implode(', ', array_map(fn ($r) => NAZWY_ROL[$r] ?? $r, $role));
                $toJa = (int) $u['id'] === $ja;
                ?>
                <tr>
                    <td class="px-5 py-4">
                        <p class="font-semibold text-gray-900"><?= e($u['first_name'] . ' ' . $u['last_name']) ?><?= $toJa ? ' <span class="text-xs font-normal text-mobiTextMuted">(Ty)</span>' : '' ?></p>
                        <p class="text-xs text-mobiTextMuted break-all"><?= e($u['email']) ?> · <?= e($u['phone']) ?></p>
                        <p class="text-xs text-mobiTextMuted md:hidden"><?= e($nazwyRol) ?></p>
                    </td>
                    <td class="px-5 py-4 hidden md:table-cell text-xs"><?= e($nazwyRol) ?></td>
                    <td class="px-5 py-4 hidden sm:table-cell"><?= (int) $u['rezerwacje'] ?></td>
                    <td class="px-5 py-4">
                        <?php if ((int) $u['is_active'] === 1): ?>
                            <span class="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2 py-1 rounded-lg">Aktywne</span>
                        <?php else: ?>
                            <span class="text-xs font-semibold bg-red-50 text-red-700 px-2 py-1 rounded-lg">Zablokowane</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <?php if (!$toJa): ?>
                        <div class="flex flex-wrap justify-end gap-2">
                            <form method="post" action="<?= e(url('admin/uzytkownicy.php')) ?>"
                                <?= (int) $u['is_active'] === 1 ? 'data-potwierdz="Zablokować konto ' . e($u['first_name'] . ' ' . $u['last_name']) . '?"' : '' ?>>
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="blokada">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <input type="hidden" name="powrot" value="<?= e($powrot) ?>">
                                <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border <?= (int) $u['is_active'] === 1 ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-gray-200 hover:border-gray-300' ?>">
                                    <?= (int) $u['is_active'] === 1 ? 'Zablokuj' : 'Odblokuj' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= e(url('admin/uzytkownicy.php')) ?>"
                                data-potwierdz="<?= in_array(ROLA_ADMIN, $role, true) ? 'Odebrać rolę administratora?' : 'Nadać rolę administratora? Ta osoba dostanie pełny dostęp do panelu.' ?>">
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="admin">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <input type="hidden" name="powrot" value="<?= e($powrot) ?>">
                                <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300 whitespace-nowrap">
                                    <?= in_array(ROLA_ADMIN, $role, true) ? 'Odbierz admina' : 'Nadaj admina' ?>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($stron > 1): ?>
        <nav class="flex flex-wrap items-center justify-between gap-3 p-5 border-t border-gray-100 text-sm" aria-label="Strony">
            <?php if ($strona > 1): ?>
                <a href="<?= e(url('admin/uzytkownicy.php' . $zapytanieStrony($strona - 1))) ?>" class="font-semibold text-mobiRed">← Poprzednia</a>
            <?php else: ?><span></span><?php endif; ?>
            <span class="text-mobiTextMuted">Strona <?= $strona ?> z <?= $stron ?></span>
            <?php if ($strona < $stron): ?>
                <a href="<?= e(url('admin/uzytkownicy.php' . $zapytanieStrony($strona + 1))) ?>" class="font-semibold text-mobiRed">Następna →</a>
            <?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
