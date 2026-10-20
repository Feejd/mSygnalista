<?php
require_once __DIR__ . '/../includes/init.php';

// Tylko POST z tokenem CSRF - obca strona nie wyloguje użytkownika linkiem.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    przekieruj('index.php');
}

if (!csrfPoprawny()) {
    bladStrony(403, 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
}

wylogujUzytkownika();
komunikat('sukces', 'Wylogowano. Do zobaczenia!');
przekieruj('index.php');
