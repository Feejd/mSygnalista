// Pomocnicze rzeczy w formularzach. Walidacja i tak jest po stronie PHP.
document.addEventListener('DOMContentLoaded', function () {
    // pokaż / ukryj hasło
    document.querySelectorAll('[data-pokaz-haslo]').forEach(function (przycisk) {
        var pole = document.getElementById(przycisk.dataset.pokazHaslo);
        if (!pole) return;

        przycisk.addEventListener('click', function () {
            var ukryte = pole.type === 'password';
            pole.type = ukryte ? 'text' : 'password';
            przycisk.setAttribute('aria-pressed', ukryte ? 'true' : 'false');
            przycisk.querySelector('i').className = ukryte ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    });

    // sprawdzenie czy hasła są takie same jeszcze przed wysłaniem
    document.querySelectorAll('[data-powtorz]').forEach(function (powtorzenie) {
        var haslo = document.getElementById(powtorzenie.dataset.powtorz);
        if (!haslo) return;

        function sprawdz() {
            powtorzenie.setCustomValidity(
                powtorzenie.value !== '' && powtorzenie.value !== haslo.value ? 'Hasła nie są takie same.' : ''
            );
        }

        haslo.addEventListener('input', sprawdz);
        powtorzenie.addEventListener('input', sprawdz);
    });
});
