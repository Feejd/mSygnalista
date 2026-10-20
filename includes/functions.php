<?php
// Bezpieczne wypisanie tekstu w HTML (ochrona przed XSS).
function e(?string $tekst): string
{
    return htmlspecialchars($tekst ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Adres względem katalogu public, działa też z podkatalogów (konto/, admin/).
function url(string $sciezka = ''): string
{
    return BASE_URL . '/' . ltrim($sciezka, '/');
}

function przekieruj(string $sciezka): never
{
    header('Location: ' . url($sciezka), true, 303);
    exit;
}

// Wartość z formularza jako tekst; tablice i brak pola dają pusty napis.
function pole(string $nazwa): string
{
    $wartosc = $_POST[$nazwa] ?? '';
    return is_string($wartosc) ? trim($wartosc) : '';
}

// --- CSRF ---

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfPole(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfPoprawny(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals(csrfToken(), $token);
}

// --- Komunikaty po przekierowaniu ---

function komunikat(string $typ, string $tresc): void
{
    $_SESSION['komunikaty'][] = ['typ' => $typ, 'tresc' => $tresc];
}

function pobierzKomunikaty(): array
{
    $lista = $_SESSION['komunikaty'] ?? [];
    unset($_SESSION['komunikaty']);
    return $lista;
}

// Strona błędu bez szczegółów technicznych.
function bladStrony(int $kod, string $tresc): never
{
    http_response_code($kod);
    $tytul = 'Błąd ' . $kod;
    require __DIR__ . '/header.php';
    ?>
    <section class="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm max-w-md mx-auto text-center">
        <div class="w-14 h-14 rounded-2xl bg-red-50 text-mobiRed flex items-center justify-center mx-auto mb-4">
            <i aria-hidden="true" class="fa-solid fa-lock text-2xl"></i>
        </div>
        <h1 class="text-xl font-extrabold text-gray-900 mb-2"><?= e($tytul) ?></h1>
        <p class="text-sm text-mobiTextMuted mb-6"><?= e($tresc) ?></p>
        <a href="<?= e(url('index.php')) ?>" class="inline-flex items-center gap-2 bg-mobiRed hover:bg-mobiRedDark text-white font-semibold px-5 py-3 rounded-xl text-sm transition">
            <i aria-hidden="true" class="fa-solid fa-house"></i> Strona główna
        </a>
    </section>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}
