<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/zgloszenia.php';

// Załączniki leżą poza katalogiem public - jedyna droga do nich prowadzi przez ten plik.
wymagajRoli(ROLA_INSPEKTOR, ROLA_ADMIN);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

$zapytanie = db()->prepare(
    'SELECT id, report_id, storage_key, original_name, mime_type, size_bytes FROM report_attachments WHERE id = :id'
);
$zapytanie->execute(['id' => $id]);
$plik = $zapytanie->fetch();

// brak pliku i brak dostępu dają ten sam błąd - nie zdradzamy, że załącznik istnieje
if (!$plik || zgloszenieDlaPracownika((int) $plik['report_id']) === null || !isset(ZALACZNIKI_TYPY[$plik['mime_type']])) {
    bladStrony(404, 'Nie znaleziono pliku.');
}

$katalog = realpath(ZALACZNIKI_KATALOG);
$sciezka = realpath(ZALACZNIKI_KATALOG . '/' . $plik['storage_key']);

// ochrona przed ../ w kluczu - plik musi leżeć w katalogu załączników
if ($sciezka === false || $katalog === false || !str_starts_with($sciezka, $katalog . DIRECTORY_SEPARATOR) || !is_file($sciezka)) {
    error_log('Brak pliku załącznika #' . $plik['id']);
    bladStrony(404, 'Nie znaleziono pliku.');
}

zapiszAudyt('report.attachment_download', 'report_attachments', (int) $plik['id'],
    'Pobranie załącznika do zgłoszenia #' . $plik['report_id']);

$nazwa = preg_replace('/[^\w.\-]+/u', '_', $plik['original_name']);

header('Content-Type: ' . $plik['mime_type']);
header('Content-Length: ' . filesize($sciezka));
header("Content-Disposition: attachment; filename=\"zalacznik-{$plik['id']}." . ZALACZNIKI_TYPY[$plik['mime_type']]
    . "\"; filename*=UTF-8''" . rawurlencode($nazwa));
header('Content-Security-Policy: sandbox');
header('Cache-Control: private, no-store');
readfile($sciezka);
