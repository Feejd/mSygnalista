<?php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);

require_once __DIR__ . '/../includes/header.php';
header('Cache-Control: no-store');

function celPoLogowaniu(): string
{
    $id = $_SESSION['wybrana_usluga'] ?? null;
    return is_int($id) && in_array($id, [1, 2, 3, 4, 5], true)
        ? 'uslugi.php?umow=' . $id
        : 'uslugi.php';
}

if (isset($_SESSION['user_id']) && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]) !== false) {
    header('Location: ' . celPoLogowaniu());
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$blad = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $haslo = is_string($_POST['haslo'] ?? null) ? $_POST['haslo'] : '';
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        $blad = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $haslo === '') {
        $blad = 'Podaj poprawny adres e-mail i hasło.';
    } elseif (strlen($haslo) > 4096) {
        $blad = 'Nieprawidłowy e-mail lub hasło.';
    } else {
        try {
            $pdo = require __DIR__ . '/../includes/db.php';
            $zapytanie = $pdo->prepare(
                'SELECT id, first_name, password_hash, is_active FROM users WHERE email = :email LIMIT 1'
            );
            $zapytanie->execute(['email' => $email]);
            $uzytkownik = $zapytanie->fetch();

            // Ten sam komunikat dla błędnego hasła, braku konta i konta nieaktywnego.
            $hash = $uzytkownik['password_hash'] ?? '$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO';
            $poprawneHaslo = password_verify($haslo, $hash);

            if (!$uzytkownik || !$poprawneHaslo || (int) $uzytkownik['is_active'] !== 1) {
                $blad = 'Nieprawidłowy e-mail lub hasło.';
            } else {
                $role = $pdo->prepare(
                    'SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :id'
                );
                $role->execute(['id' => $uzytkownik['id']]);
                $kodyRol = $role->fetchAll(PDO::FETCH_COLUMN);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $uzytkownik['id'];
                $_SESSION['first_name'] = $uzytkownik['first_name'];
                $_SESSION['roles'] = $kodyRol;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: ' . celPoLogowaniu(), true, 303);
                exit;
            }
        } catch (PDOException $e) {
            http_response_code(503);
            $blad = 'Logowanie jest chwilowo niedostępne. Spróbuj ponownie później.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - mSygnalista</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        mobiRed: '#D32F2F',
                        mobiRedDark: '#B71C1C',
                        mobiBg: '#F3F4F6',
                        mobiCard: '#FFFFFF',
                        mobiTextMain: '#1F2937',
                        mobiTextMuted: '#6B7280',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        a:focus-visible {
            outline: 3px solid #174b91;
            outline-offset: 4px;
        }
    </style>
</head>
<body class="bg-mobiBg text-mobiTextMain font-sans antialiased min-h-screen flex flex-col">
<?php wyswietlHeader($zalogowany); ?>

<main class="w-full max-w-md mx-auto px-4 py-10 flex-grow">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-6">
        <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Strona główna
    </a>
    <section class="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm" aria-labelledby="tytul">
        <h1 id="tytul" class="text-2xl font-extrabold text-gray-900 mb-2">Zaloguj się</h1>
        <p class="text-sm text-mobiTextMuted mb-6">Wprowadź adres e-mail i hasło do swojego konta.</p>

        <?php if ($blad !== ''): ?>
            <p role="alert" class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-3 text-sm mb-5">
                <?= htmlspecialchars($blad, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="post" action="logowanie.php" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="email" class="block text-sm font-semibold mb-2">Adres e-mail</label>
                <input type="email" id="email" name="email" required maxlength="254" autocomplete="username"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-mobiRed">
            </div>
            <div>
                <label for="haslo" class="block text-sm font-semibold mb-2">Hasło</label>
                <input type="password" id="haslo" name="haslo" required maxlength="4096" autocomplete="current-password"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-mobiRed">
            </div>
            <button type="submit" class="w-full bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mobiRed">
                Zaloguj się
            </button>
        </form>
    </section>
</main>

<?php include '/../includes/footer.php'; ?>
</body>
</html>
