<?php
// Przed dołączeniem ustaw $tytul. Wymaga wcześniejszego init.php.
$tytul = isset($tytul) ? $tytul . ' - mSygnalista' : 'mSygnalista - zgłoszenia i konsultacje';
// gdy baza nie działa, strona błędu i tak ma się wyświetlić
try {
    $uzytkownik = aktualnyUzytkownik();
} catch (PDOException $ex) {
    $uzytkownik = null;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tytul) ?></title>
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
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="bg-mobiBg text-mobiTextMain font-sans antialiased min-h-screen flex flex-col">

<header class="bg-white border-b border-gray-200 sticky top-0 z-50 px-4 py-3 shadow-sm">
    <div class="max-w-4xl mx-auto flex justify-between items-center gap-3">
        <a href="<?= e(url('index.php')) ?>" class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-mobiRed flex items-center justify-center text-white shadow-md shadow-red-500/20">
                <i aria-hidden="true" class="fa-solid fa-shield-halved text-lg"></i>
            </div>
            <span class="text-base font-extrabold tracking-tight text-gray-900">mSygnalista</span>
        </a>

        <nav class="flex items-center gap-2" aria-label="Konto">
            <?php if ($uzytkownik === null): ?>
                <a href="<?= e(url('rejestracja.php')) ?>" class="hidden sm:inline-flex text-xs font-semibold text-gray-700 hover:text-mobiRed px-3 py-2">
                    Załóż konto
                </a>
                <a href="<?= e(url('logowanie.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold px-4 py-2 rounded-xl border border-gray-200 transition inline-flex items-center gap-2">
                    <i aria-hidden="true" class="fa-solid fa-user-circle text-mobiRed"></i>
                    Zaloguj się
                </a>
            <?php else: ?>
                <?php if (maRole(ROLA_ADMIN)): ?>
                    <a href="<?= e(url('admin/index.php')) ?>" class="text-xs font-semibold text-gray-700 hover:text-mobiRed px-2 py-2">
                        <i aria-hidden="true" class="fa-solid fa-gauge sm:mr-1"></i><span class="hidden sm:inline">Administracja</span>
                    </a>
                <?php endif; ?>
                <?php if (maRole(ROLA_INSPEKTOR)): ?>
                    <a href="<?= e(url('inspektor/index.php')) ?>" class="text-xs font-semibold text-gray-700 hover:text-mobiRed px-2 py-2">
                        <i aria-hidden="true" class="fa-solid fa-user-tie sm:mr-1"></i><span class="hidden sm:inline">Panel inspektora</span>
                    </a>
                <?php endif; ?>
                <a href="<?= e(url('konto/profil.php')) ?>" class="text-xs font-semibold text-gray-700 hover:text-mobiRed px-2 py-2">
                    <i aria-hidden="true" class="fa-solid fa-user sm:mr-1"></i><span class="hidden sm:inline"><?= e($uzytkownik['first_name']) ?></span>
                </a>
                <form method="post" action="<?= e(url('wylogowanie.php')) ?>">
                    <?= csrfPole() ?>
                    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold px-3 py-2 rounded-xl border border-gray-200 transition inline-flex items-center gap-2">
                        <i aria-hidden="true" class="fa-solid fa-right-from-bracket text-mobiRed"></i>
                        <span class="hidden sm:inline">Wyloguj</span>
                    </button>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-6 w-full flex-grow">
<?php foreach (pobierzKomunikaty() as $k): ?>
    <p role="<?= $k['typ'] === 'blad' ? 'alert' : 'status' ?>" class="komunikat komunikat-<?= e($k['typ']) ?> mb-5">
        <?= e($k['tresc']) ?>
    </p>
<?php endforeach; ?>
