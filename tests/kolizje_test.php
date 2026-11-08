<?php
// Testy reguły nakładania się wizyt (bez bazy danych).
// Uruchomienie: php tests/kolizje_test.php

require __DIR__ . '/../includes/rezerwacje.php';

$bledy = 0;

function sprawdz(string $opis, bool $wynik, bool $oczekiwany): void
{
    global $bledy;
    $ok = $wynik === $oczekiwany;
    $bledy += $ok ? 0 : 1;
    echo ($ok ? '[OK]   ' : '[BŁĄD] ') . $opis . PHP_EOL;
}

// przykład z wytycznych: istniejąca wizyta 10:00-11:00
$istniejaca = [naMinuty('10:00'), naMinuty('11:00')];

foreach (['09:30-10:30', '10:00-11:00', '10:15-10:45', '10:30-11:30', '09:30-11:30'] as $termin) {
    [$od, $do] = explode('-', $termin);
    sprawdz("$termin koliduje z 10:00-11:00", nakladajaSie(naMinuty($od), naMinuty($do), ...$istniejaca), true);
}

foreach (['09:00-10:00', '11:00-12:00', '08:00-09:00', '11:30-12:00'] as $termin) {
    [$od, $do] = explode('-', $termin);
    sprawdz("$termin NIE koliduje z 10:00-11:00", nakladajaSie(naMinuty($od), naMinuty($do), ...$istniejaca), false);
}

// godziny pracy 8-16 z przerwą 12:00-12:30
$wynik = odejmijPrzedzialy([[naMinuty('08:00'), naMinuty('16:00')]], [[naMinuty('12:00'), naMinuty('12:30')]]);
sprawdz('przerwa dzieli dzień na 08:00-12:00 i 12:30-16:00', $wynik === [[480, 720], [750, 960]], true);

$wynik = odejmijPrzedzialy([[naMinuty('08:00'), naMinuty('16:00')]], [[0, 24 * 60]]);
sprawdz('urlop na cały dzień usuwa wszystkie godziny', $wynik === [], true);

$wynik = odejmijPrzedzialy([[naMinuty('08:00'), naMinuty('16:00')]], [[naMinuty('07:00'), naMinuty('09:00')]]);
sprawdz('nieobecność rano obcina początek do 09:00', $wynik === [[540, 960]], true);

sprawdz('naGodzine(570) = 09:30', naGodzine(570) === '09:30', true);

echo PHP_EOL . ($bledy === 0 ? 'Wszystkie testy przeszły.' : "Nieudane testy: $bledy") . PHP_EOL;
exit($bledy === 0 ? 0 : 1);
