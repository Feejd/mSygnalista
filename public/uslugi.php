<?php
session_start();

$zalogowany = isset($_SESSION['user_id'])
    && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;

if (isset($_GET['umow'])) {
    $uslugaId = filter_var($_GET['umow'], FILTER_VALIDATE_INT);

    if (!in_array($uslugaId, [1, 2, 3, 4, 5], true)) {
        http_response_code(400);
        exit('Nieprawidłowa usługa.');
    }

    if (!$zalogowany) {
        $_SESSION['wybrana_usluga'] = $uslugaId;
        header('Location: logowanie.php');
        exit;
    }

    header('Location: rezerwacja.php?usluga=' . $uslugaId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultacje - mSygnalista</title>
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
<body class="bg-mobiBg text-mobiTextMain font-sans antialiased min-h-screen flex flex-col justify-between">

<header class="bg-white border-b border-gray-200 sticky top-0 z-50 px-4 py-3 shadow-sm">
    <div class="max-w-4xl mx-auto flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-mobiRed flex items-center justify-center text-white shadow-md shadow-red-500/20">
                <i aria-hidden="true" class="fa-solid fa-shield-halved text-lg"></i>
            </div>
            <div>
                <h1 class="text-base font-extrabold tracking-tight text-gray-900">mSygnalista</h1>
            </div>
        </div>
        <div>
            <?php if (!$zalogowany): ?>
                <a href="logowanie.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold px-4 py-2 rounded-xl border border-gray-200 transition inline-flex items-center gap-2">
                    <i aria-hidden="true" class="fa-solid fa-user-circle text-mobiRed"></i>
                    Zaloguj się
                </a>
            <?php else: ?>
                <span class="text-sm font-semibold text-emerald-700">Zalogowano</span>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-6 w-full flex-grow">
    <a href="index.php" class="inline-flex items-center gap-2 text-sm text-mobiRed font-semibold mb-6">
        <i aria-hidden="true" class="fa-solid fa-arrow-left"></i> Strona główna
    </a>

    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Konsultacje</h2>
    <p class="text-mobiTextMuted mb-6">Sprawdź zakres i czas trwania konsultacji.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <article class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm flex flex-col">
            <span class="text-xs font-semibold text-mobiRed mb-2">Prawo pracy</span>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Podstawy prawa pracy</h3>
            <p class="text-sm text-mobiTextMuted mb-5">Omówienie praw i obowiązków pracownika.</p>
            <dl class="flex justify-between gap-4 border-t border-gray-100 pt-4 mt-auto text-sm">
                <div>
                    <dt class="text-mobiTextMuted">Czas trwania</dt>
                    <dd class="font-semibold">30 minut</dd>
                </div>
                <div class="text-right">
                    <dt class="text-mobiTextMuted">Cena</dt>
                    <dd class="font-semibold">80,00 zł</dd>
                </div>
            </dl>
            <a href="uslugi.php?umow=1" aria-label="Umów wizytę: Podstawy prawa pracy" class="mt-5 bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-2xl transition inline-flex items-center justify-center gap-2 text-sm">
                <i aria-hidden="true" class="fa-solid fa-calendar-plus"></i>
                Umów wizytę
            </a>
        </article>

        <article class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm flex flex-col">
            <span class="text-xs font-semibold text-mobiRed mb-2">Prawo pracy</span>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Konsultacja BHP</h3>
            <p class="text-sm text-mobiTextMuted mb-5">Omówienie zagrożeń w miejscu pracy.</p>
            <dl class="flex justify-between gap-4 border-t border-gray-100 pt-4 mt-auto text-sm">
                <div>
                    <dt class="text-mobiTextMuted">Czas trwania</dt>
                    <dd class="font-semibold">60 minut</dd>
                </div>
                <div class="text-right">
                    <dt class="text-mobiTextMuted">Cena</dt>
                    <dd class="font-semibold">150,00 zł</dd>
                </div>
            </dl>
            <a href="uslugi.php?umow=2" aria-label="Umów wizytę: Konsultacja BHP" class="mt-5 bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-2xl transition inline-flex items-center justify-center gap-2 text-sm">
                <i aria-hidden="true" class="fa-solid fa-calendar-plus"></i>
                Umów wizytę
            </a>
        </article>

        <article class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm flex flex-col">
            <span class="text-xs font-semibold text-mobiRed mb-2">Ruch drogowy</span>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Zgłoszenie drogowe - konsultacja</h3>
            <p class="text-sm text-mobiTextMuted mb-5">Pomoc w opisaniu zdarzenia.</p>
            <dl class="flex justify-between gap-4 border-t border-gray-100 pt-4 mt-auto text-sm">
                <div>
                    <dt class="text-mobiTextMuted">Czas trwania</dt>
                    <dd class="font-semibold">30 minut</dd>
                </div>
                <div class="text-right">
                    <dt class="text-mobiTextMuted">Cena</dt>
                    <dd class="font-semibold">60,00 zł</dd>
                </div>
            </dl>
            <a href="uslugi.php?umow=3" aria-label="Umów wizytę: Zgłoszenie drogowe - konsultacja" class="mt-5 bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-2xl transition inline-flex items-center justify-center gap-2 text-sm">
                <i aria-hidden="true" class="fa-solid fa-calendar-plus"></i>
                Umów wizytę
            </a>
        </article>

        <article class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm flex flex-col">
            <span class="text-xs font-semibold text-mobiRed mb-2">Instytucje publiczne</span>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Procedura zgłoszenia</h3>
            <p class="text-sm text-mobiTextMuted mb-5">Omówienie trybu rozpatrywania sprawy.</p>
            <dl class="flex justify-between gap-4 border-t border-gray-100 pt-4 mt-auto text-sm">
                <div>
                    <dt class="text-mobiTextMuted">Czas trwania</dt>
                    <dd class="font-semibold">45 minut</dd>
                </div>
                <div class="text-right">
                    <dt class="text-mobiTextMuted">Cena</dt>
                    <dd class="font-semibold">90,00 zł</dd>
                </div>
            </dl>
            <a href="uslugi.php?umow=4" aria-label="Umów wizytę: Procedura zgłoszenia" class="mt-5 bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-2xl transition inline-flex items-center justify-center gap-2 text-sm">
                <i aria-hidden="true" class="fa-solid fa-calendar-plus"></i>
                Umów wizytę
            </a>
        </article>

        <article class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm flex flex-col">
            <span class="text-xs font-semibold text-mobiRed mb-2">Prawo pracy</span>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Dokumenty pracownicze</h3>
            <p class="text-sm text-mobiTextMuted mb-5">Omówienie dokumentów zatrudnienia.</p>
            <dl class="flex justify-between gap-4 border-t border-gray-100 pt-4 mt-auto text-sm">
                <div>
                    <dt class="text-mobiTextMuted">Czas trwania</dt>
                    <dd class="font-semibold">45 minut</dd>
                </div>
                <div class="text-right">
                    <dt class="text-mobiTextMuted">Cena</dt>
                    <dd class="font-semibold">120,00 zł</dd>
                </div>
            </dl>
            <a href="uslugi.php?umow=5" aria-label="Umów wizytę: Dokumenty pracownicze" class="mt-5 bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-2xl transition inline-flex items-center justify-center gap-2 text-sm">
                <i aria-hidden="true" class="fa-solid fa-calendar-plus"></i>
                Umów wizytę
            </a>
        </article>

    </div>
</main>

<footer class="bg-white border-t border-gray-200 py-6 px-4 text-center text-gray-400 text-xs">
    <div class="max-w-4xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-3">
        <p>© 2026 mSygnalista</p>
        <p>Powered by Dominik Pieńkowski</p>
    </div>
</footer>

</body>
</html>
