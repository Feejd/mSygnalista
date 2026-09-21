<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mSygnalista - zgłoszenia i konsultacje</title>
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

<?php include 'header.php'; ?>

<main class="max-w-4xl mx-auto px-4 py-6 w-full flex-grow">

    <div class="bg-white border border-gray-200/80 rounded-3xl p-6 mb-6 shadow-sm relative overflow-hidden">
        <div class="absolute right-0 top-0 w-32 h-32 bg-red-50 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">Spełniaj z nami obowiązek obywatelski jeszcze prościej niż kiedykolwiek.</h2>
            <div class="flex flex-wrap gap-3">
                <a href="zgloszenie.php" class="bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-2xl shadow-md shadow-red-600/20 transition flex items-center space-x-2 text-sm">
                    <i aria-hidden="true" class="fa-solid fa-triangle-exclamation"></i>
                    <span>Nowe zgłoszenie</span>
                </a>
                <a href="rezerwacja.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold px-5 py-3 rounded-2xl border border-gray-200 transition flex items-center space-x-2 text-sm">
                    <i aria-hidden="true" class="fa-solid fa-calendar-days text-mobiRed"></i>
                    <span>Rezerwuj wizytę</span>
                </a>
            </div>
        </div>
    </div>

    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4 px-1">Usługi i zgłoszenia</h3>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">

        <a href="zgloszenie.php" class="bg-mobiCard hover:border-gray-300 border border-gray-200/80 p-5 rounded-3xl transition duration-200 group flex flex-col justify-between shadow-sm">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-red-50 text-mobiRed flex items-center justify-center mb-4 group-hover:scale-105 transition duration-200">
                    <i aria-hidden="true" class="fa-solid fa-file-shield text-xl"></i>
                </div>
                <h4 class="text-base font-bold text-gray-900 mb-1">Zgłoś nieprawidłowość</h4>
                <p class="text-mobiTextMuted text-xs sm:text-sm">Opisz naruszenie i dodaj załączniki bez zakładania konta.</p>
            </div>
            <div class="mt-4 flex items-center text-mobiRed text-xs font-bold group-hover:translate-x-1 transition duration-200">
                <span>Wypełnij formularz</span>
                <i aria-hidden="true" class="fa-solid fa-arrow-right ml-2"></i>
            </div>
        </a>

        <a href="uslugi.php" class="bg-mobiCard hover:border-gray-300 border border-gray-200/80 p-5 rounded-3xl transition duration-200 group flex flex-col justify-between shadow-sm">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 group-hover:scale-105 transition duration-200">
                    <i aria-hidden="true" class="fa-solid fa-calendar-check text-xl"></i>
                </div>
                <h4 class="text-base font-bold text-gray-900 mb-1">Konsultacje</h4>
                <p class="text-mobiTextMuted text-xs sm:text-sm">Wybór usługi, inspektora i dostępnego terminu konsultacji.</p>
            </div>
            <div class="mt-4 flex items-center text-blue-600 text-xs font-bold group-hover:translate-x-1 transition duration-200">
                <span>Zobacz ofertę</span>
                <i aria-hidden="true" class="fa-solid fa-arrow-right ml-2"></i>
            </div>
        </a>

        <a href="rezerwacja.php" class="bg-mobiCard hover:border-gray-300 border border-gray-200/80 p-5 rounded-3xl transition duration-200 group flex flex-col justify-between shadow-sm">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 group-hover:scale-105 transition duration-200">
                    <i aria-hidden="true" class="fa-solid fa-calendar-check text-xl"></i>
                </div>
                <h4 class="text-base font-bold text-gray-900 mb-1">Rezerwacja konsultacji</h4>
                <p class="text-mobiTextMuted text-xs sm:text-sm">Wybór usługi, inspektora i dostępnego terminu konsultacji.</p>
            </div>
            <div class="mt-4 flex items-center text-blue-600 text-xs font-bold group-hover:translate-x-1 transition duration-200">
                <span>Wybierz termin</span>
                <i aria-hidden="true" class="fa-solid fa-arrow-right ml-2"></i>
            </div>
        </a>

        <a href="status.php" class="bg-mobiCard hover:border-gray-300 border border-gray-200/80 p-5 rounded-3xl transition duration-200 group flex flex-col justify-between shadow-sm">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4 group-hover:scale-105 transition duration-200">
                    <i aria-hidden="true" class="fa-solid fa-magnifying-glass text-xl"></i>
                </div>
                <h4 class="text-base font-bold text-gray-900 mb-1">Sprawdź status sprawy</h4>
                <p class="text-mobiTextMuted text-xs sm:text-sm">Sprawdzanie etapu rozpatrywania zgłoszenia za pomocą unikalnego tokenu.</p>
            </div>
            <div class="mt-4 flex items-center text-amber-600 text-xs font-bold group-hover:translate-x-1 transition duration-200">
                <span>Sprawdź status</span>
                <i aria-hidden="true" class="fa-solid fa-arrow-right ml-2"></i>
            </div>
        </a>

        <a href="logowanie.php" class="bg-mobiCard hover:border-gray-300 border border-gray-200/80 p-5 rounded-3xl transition duration-200 group flex flex-col justify-between shadow-sm">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4 group-hover:scale-105 transition duration-200">
                    <i aria-hidden="true" class="fa-solid fa-user-tie text-xl"></i>
                </div>
                <h4 class="text-base font-bold text-gray-900 mb-1">Strefa pracownika</h4>
                <p class="text-mobiTextMuted text-xs sm:text-sm">Panel do obsługi zgłoszeń, rezerwacji i harmonogramu pracy.</p>
            </div>
            <div class="mt-4 flex items-center text-emerald-600 text-xs font-bold group-hover:translate-x-1 transition duration-200">
                <span>Zaloguj się do panelu</span>
                <i aria-hidden="true" class="fa-solid fa-arrow-right ml-2"></i>
            </div>
        </a>

    </div>

</main>

<?php include 'footer.php'; ?>

</body>
</html>
