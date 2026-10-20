<?php
// Dołączany na początku każdej strony w public/.

declare(strict_types=1);

// Błędy idą do pliku, użytkownik nie widzi szczegółów technicznych.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/app.log');
error_reporting(E_ALL);

// Adres katalogu public, np. "/mSygnalista/public" albo "" gdy public jest głównym katalogiem serwera.
$publicDir = realpath(__DIR__ . '/../public');
$skrypt = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$wzgledna = str_starts_with($skrypt, $publicDir) ? substr($skrypt, strlen($publicDir)) : '';
$wzgledna = str_replace('\\', '/', $wzgledna);
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
define('BASE_URL', $wzgledna !== '' && str_ends_with($scriptName, $wzgledna)
    ? substr($scriptName, 0, -strlen($wzgledna))
    : rtrim(dirname($scriptName), '/'));
unset($publicDir, $skrypt, $wzgledna, $scriptName);

// Sesja: ciasteczko tylko dla HTTP, bez dostępu z JS, odrzucanie nieznanych identyfikatorów.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('msygnalista');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Nieobsłużony wyjątek (np. brak połączenia z bazą) - wpis do logu i zwykły komunikat.
set_exception_handler(function (Throwable $ex): void {
    error_log(get_class($ex) . ': ' . $ex->getMessage() . ' w ' . $ex->getFile() . ':' . $ex->getLine());

    if (headers_sent()) {
        echo '<p>Wystąpił błąd. Spróbuj ponownie później.</p>';
        return;
    }
    bladStrony(500, 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie później.');
});

sprawdzWaznoscSesji();
