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

// potwierdzenie przed usunięciem / wyłączeniem (formularz z data-potwierdz="...")
document.addEventListener('submit', function (zdarzenie) {
    var tekst = zdarzenie.target.dataset && zdarzenie.target.dataset.potwierdz;
    if (tekst && !window.confirm(tekst)) {
        zdarzenie.preventDefault();
    }
});

// Rezerwacja: wolne godziny dla wybranej daty pobierane bez przeładowania (AJAX).
// Bez JS formularz działa normalnie, a serwer i tak sprawdza termin przed zapisem.
document.addEventListener('DOMContentLoaded', function () {
    var formularz = document.querySelector('form[data-terminy]');
    var pojemnik = document.getElementById('godziny');
    if (!formularz || !pojemnik || !window.fetch) return;

    var poleDaty = formularz.querySelector('input[name="data"]');

    function zaladuj(data) {
        var adres = new URL(formularz.dataset.terminy, window.location.href);
        adres.searchParams.set('data', data);
        pojemnik.innerHTML = '<p class="text-sm text-mobiTextMuted">Sprawdzam wolne terminy…</p>';

        fetch(adres, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (odp) {
                if (!odp.ok) throw new Error('HTTP ' + odp.status);
                return odp.json();
            })
            .then(function (wynik) {
                var link = new URL(adres);
                link.searchParams.delete('json');

                var naglowek = document.createElement('h3');
                naglowek.className = 'text-sm font-semibold mb-2';
                naglowek.textContent = 'Wolne godziny ' + data.split('-').reverse().join('.');
                pojemnik.replaceChildren(naglowek);

                if (!wynik.godziny.length) {
                    var brak = document.createElement('p');
                    brak.className = 'text-sm text-mobiTextMuted';
                    brak.textContent = 'W tym dniu nie ma wolnych terminów. Wybierz inny dzień.';
                    pojemnik.appendChild(brak);
                    return;
                }

                var lista = document.createElement('div');
                lista.className = 'flex flex-wrap gap-2';
                wynik.godziny.forEach(function (godzina) {
                    link.searchParams.set('godzina', godzina);
                    var a = document.createElement('a');
                    a.href = link.toString();
                    a.textContent = godzina;
                    a.className = 'px-4 py-2 rounded-xl border border-gray-200 hover:border-mobiRed hover:text-mobiRed font-semibold text-sm';
                    lista.appendChild(a);
                });
                pojemnik.appendChild(lista);

                // adres w pasku też się zmienia, żeby odświeżenie strony pokazało ten sam dzień
                var adresStrony = new URL(window.location.href);
                adresStrony.searchParams.set('data', data);
                adresStrony.searchParams.delete('godzina');
                history.replaceState(null, '', adresStrony);
            })
            .catch(function () {
                formularz.submit();
            });
    }

    formularz.addEventListener('submit', function (zdarzenie) {
        if (poleDaty.value) {
            zdarzenie.preventDefault();
            zaladuj(poleDaty.value);
        }
    });
    poleDaty.addEventListener('change', function () {
        if (poleDaty.value) zaladuj(poleDaty.value);
    });
});
