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

// Pole formularza z etykietą i komunikatem błędu pod spodem.
// Dla type="password" dokłada przycisk pokaż/ukryj i nie wypisuje wartości.
function poleFormularza(string $nazwa, string $etykieta, string $wartosc, array $bledy, string $typ = 'text', string $atrybuty = ''): string
{
    $id = 'pole-' . $nazwa;
    $blad = $bledy[$nazwa] ?? null;
    $html = '<div><label for="' . e($id) . '" class="block text-sm font-semibold mb-2">' . e($etykieta) . '</label>';
    $html .= '<div class="relative">';
    $html .= '<input type="' . e($typ) . '" id="' . e($id) . '" name="' . e($nazwa) . '" class="pole' . ($typ === 'password' ? ' pr-12' : '') . '" ' . $atrybuty;

    if ($typ !== 'password') {
        $html .= ' value="' . e($wartosc) . '"';
    }
    if ($blad !== null) {
        $html .= ' aria-invalid="true" aria-describedby="' . e($id) . '-blad"';
    }
    $html .= '>';

    if ($typ === 'password') {
        $html .= '<button type="button" data-pokaz-haslo="' . e($id) . '" aria-pressed="false" aria-label="Pokaż hasło"'
            . ' class="absolute inset-y-0 right-0 px-4 text-gray-500 hover:text-mobiRed"><i class="fa-solid fa-eye"></i></button>';
    }
    $html .= '</div>';

    if ($blad !== null) {
        $html .= '<p id="' . e($id) . '-blad" class="blad-pola">' . e($blad) . '</p>';
    }

    return $html . '</div>';
}

// --- Oferta ---

// Aktywne usługi z aktywnej kategorii, opcjonalnie tylko z jednej kategorii.
// liczba_inspektorow = ilu aktywnych inspektorów wykonuje usługę.
function aktywneUslugi(?int $kategoriaId = null): array
{
    $sql = 'SELECT s.id, s.name, s.description, s.duration_minutes, s.price,
                   c.id AS category_id, c.name AS category_name,
                   COUNT(i.id) AS liczba_inspektorow
            FROM services s
            JOIN service_categories c ON c.id = s.category_id AND c.is_active = 1
            LEFT JOIN inspector_services isv ON isv.service_id = s.id AND isv.is_active = 1
            LEFT JOIN inspectors i ON i.id = isv.inspector_id AND i.is_active = 1
            WHERE s.is_active = 1';
    $parametry = [];

    if ($kategoriaId !== null) {
        $sql .= ' AND c.id = :kategoria';
        $parametry['kategoria'] = $kategoriaId;
    }

    $zapytanie = db()->prepare($sql . ' GROUP BY s.id ORDER BY c.name, s.name');
    $zapytanie->execute($parametry);
    return $zapytanie->fetchAll();
}

function czyAktywnaUsluga(int $id): bool
{
    $zapytanie = db()->prepare(
        'SELECT 1 FROM services s JOIN service_categories c ON c.id = s.category_id
         WHERE s.id = :id AND s.is_active = 1 AND c.is_active = 1'
    );
    $zapytanie->execute(['id' => $id]);
    return $zapytanie->fetchColumn() !== false;
}

function cena(string $kwota): string
{
    return (float) $kwota == 0.0 ? 'Bezpłatna' : number_format((float) $kwota, 2, ',', ' ') . ' zł';
}

// --- Panel administratora ---

// Zakładki na górze każdej strony w admin/.
function menuAdmina(string $aktywna): string
{
    $zakladki = [
        'index.php' => ['Start', 'fa-gauge'],
        'kategorie.php' => ['Kategorie', 'fa-folder-tree'],
    ];

    $html = '<nav class="flex flex-wrap gap-2 mb-6" aria-label="Panel administratora">';
    foreach ($zakladki as $plik => [$nazwa, $ikona]) {
        $klasa = $plik === $aktywna
            ? 'bg-gray-900 text-white border-gray-900'
            : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300';
        $html .= '<a href="' . e(url('admin/' . $plik)) . '" class="text-xs font-semibold px-4 py-2 rounded-xl border transition inline-flex items-center gap-2 ' . $klasa . '"'
            . ($plik === $aktywna ? ' aria-current="page"' : '') . '>'
            . '<i aria-hidden="true" class="fa-solid ' . e($ikona) . '"></i>' . e($nazwa) . '</a>';
    }
    return $html . '</nav>';
}

// Wpis do audit_logs (tabela tylko do dopisywania - pilnują tego triggery).
function zapiszAudyt(string $akcja, string $encja, int $encjaId, string $opis): void
{
    db()->prepare(
        'INSERT INTO audit_logs (actor_id, action_code, entity_type, entity_id, description)
         VALUES (:kto, :akcja, :encja, :id, :opis)'
    )->execute([
        'kto' => aktualnyUzytkownik()['id'],
        'akcja' => $akcja,
        'encja' => $encja,
        'id' => $encjaId,
        'opis' => mb_substr($opis, 0, 1000),
    ]);
}
