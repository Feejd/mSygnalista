<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$zalogowany = isset($_SESSION['user_id'])
    && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;

function wyswietlHeader(bool $zalogowany): void
{
?>
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
<?php
}

