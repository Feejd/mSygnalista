

# ![Logo](https://zapodaj.net/images/01b22954bf7b0.png)

**Spełniaj z nami obowiązek obywatelski jeszcze prościej niż kiedykolwiek.**




## Wersja online

**Link:** https://msygnalista.feejd.space

**Kod dostępu:** `msyg-0f844c7e`

Po wpisaniu kodu otwiera się normalna aplikacja; logowanie kontami testowymi z sekcji [Konta testowe](#konta-testowe). Zapasowy adres (gdyby sieć blokowała domenę): https://msygnalista.rat-api.workers.dev

Wersja online działa w Cloudflare Containers. Po 30 minutach bez ruchu kontener usypia, a baza wraca do danych z `database.sql` – konta założone w międzyczasie znikają. Pierwsze wejście po uśpieniu może trwać kilkanaście sekund.

## O projekcie

**mSygnalista** to nowoczesna, bezpieczna platforma webowa inspirowana oficjalnymi rozwiązaniami rządowymi. Jej głównym zadaniem jest ułatwienie obywatelom zgłaszania wykroczeń drogowych, naruszeń praw pracowniczych oraz innych nieprawidłowości, a także rezerwowania oficjalnych konsultacji z inspektorami państwowymi (m.in. z Państwową Inspekcją Pracy).


## Funkcjonalności

- **Anonimowe i bezpieczne zgłoszenia:** Możliwość zgłaszania naruszeń (drogowych, pracowniczych, instytucjonalnych) wraz z załącznikami i automatycznym generowaniem unikalnych tokenów dostępu.
- **Śledzenie statusu sprawy po tokenie:** System pozwala obywatelom na weryfikację etapu rozpatrywania zgłoszenia bez konieczności zakładania konta czy podawania danych osobowych.
- **Rezerwacja konsultacji online:** Zintegrowany kalendarz wizyt z automatyczną weryfikacją dostępności terminów oraz regułami konfliktów czasowych.
- **Dedykowany panel eksperta / PIP**: Strefa dla inspektorów i administratorów do zarządzania zgłoszeniami i harmonogramem spotkań.
- **Interfejs**: Czysty, jasny design w stylu aplikacji mObywatel oparty na nowoczesnym frameworku Tailwind CSS, w pełni responsywny i dostosowany do urządzeń mobilnych.


## Technologie

- **Frontend:** HTML5, Tailwind CSS, JavaScript, FontAwesome 6

- **Backend:** PHP

- **Baza danych:** MySQL / MariaDB

## Uruchomienie

1. Skopiuj projekt do katalogu serwera (np. `MAMP/htdocs/mSygnalista`).
2. Zaimportuj `database/database.sql` w phpMyAdmin albo poleceniem `mysql -u root -p < database/database.sql`. Skrypt sam tworzy bazę `msygnalista`.
3. Skopiuj `config/config.example.php` jako `config/config.php` i wpisz dane do bazy (MAMP: port `8889`, użytkownik i hasło `root`). Plik `config.php` jest w `.gitignore`, więc hasło nie trafia do repozytorium.
4. Otwórz w przeglądarce `http://localhost:8888/mSygnalista/public/` (port zależy od serwera).

Wymagane PHP 8.1 lub nowsze. Błędy aplikacji zapisują się w `storage/logs/app.log`.

### Testy automatyczne

Reguła nakładania się wizyt i liczenie godzin pracy (przykład 10:00–11:00 z wytycznych):

```bash
php tests/kolizje_test.php
```

### Wdrożenie na Cloudflare

Pliki w katalogu `deploy/`: `Dockerfile` buduje obraz z Apache, PHP i MariaDB, `start.sh` przy starcie importuje `database.sql` i tworzy `config.php` z losowym hasłem do bazy, a Worker (`src/index.ts`) sprawdza kod dostępu i przekazuje ruch do kontenera. Wymaga planu Workers Paid i Dockera.

```bash
cd deploy
npm install
npx wrangler deploy
```

## Konta testowe

Po zaimportowaniu `database/database.sql` dostępne są poniższe konta:

| Rola | Adres e-mail |
|---|---|
| Administrator | `admin@example.test` |
| Inspektor — prawo pracy | `inspektor.pip@example.test` |
| Inspektor — ruch drogowy | `inspektor.drogi@example.test` |
| Inspektor — instytucje | `inspektor.instytucje@example.test` |
| Obywatel | `klient.jan@example.test` |
| Obywatel | `klient.maria@example.test` |
| Obywatel | `klient.adam@example.test` |

**Hasło do wszystkich kont testowych:** `Demo!mSygnalista2026`

Logowanie odbywa się przez stronę `public/logowanie.php`.

Konto `klient.nieaktywny@example.test` jest nieaktywne i służy do sprawdzenia blokady logowania. Obowiązuje dla niego to samo hasło.

Dane służą wyłącznie do lokalnych testów projektu. Hasła w bazie są przechowywane jako skróty bcrypt.

## Użytkownicy i logowanie (etap 2)

| Rola | Po zalogowaniu | Dostęp |
|---|---|---|
| Obywatel (`citizen`) | `konto/profil.php` | własny profil, w kolejnym etapie rezerwacje |
| Inspektor (`inspector`) | `inspektor/index.php` | panel inspektora i własny profil |
| Administrator (`admin`) | `admin/index.php` | panel administratora, panel inspektora i własny profil |

- **Rejestracja** (`rejestracja.php`) – imię, nazwisko, e-mail, telefon, hasło i powtórzenie hasła. Serwer sprawdza wymagane pola, format e-maila i telefonu, unikalność e-maila oraz zgodność haseł. Nowe konto zawsze dostaje rolę obywatela.
- **Hasła** – `password_hash()` / `password_verify()`, min. 8 znaków z literą i cyfrą. Przy logowaniu stary hash jest automatycznie przeliczany, jeśli PHP używa mocniejszych ustawień (`password_needs_rehash()`).
- **Sesja** – po zalogowaniu `session_regenerate_id()`, ciasteczko `HttpOnly` + `SameSite=Lax`, tryb `use_strict_mode`. Wylogowanie po 30 minutach bez aktywności. Dane i role użytkownika są wczytywane z bazy przy każdym żądaniu, więc dezaktywacja konta działa od razu.
- **Kontrola dostępu** – każda chroniona strona wywołuje `wymagajLogowania()` albo `wymagajRoli()` z `includes/auth.php`. Wejście na `admin/` przez wpisanie adresu bez roli administratora kończy się błędem 403.
- **Profil** (`konto/profil.php`) – edycja imienia, nazwiska, telefonu i e-maila (zmiana e-maila wymaga podania hasła) oraz zmiana hasła po podaniu starego.
- **Bezpieczeństwo** – wszystkie zapytania przez PDO z prepared statements, tokeny CSRF w każdym formularzu (także przy wylogowaniu), `htmlspecialchars()` przy wypisywaniu danych, komunikaty błędów bez szczegółów technicznych.

### Funkcje dodatkowe

- limit prób logowania – po 5 błędnych próbach na jeden e-mail (lub 20 z jednego IP) logowanie jest blokowane na 15 minut (tabela `login_attempts`),
- tokeny CSRF,
- zmiana hasła po podaniu starego,
- automatyczne wylogowanie po bezczynności i okresowa zmiana identyfikatora sesji,
- ten sam komunikat przy złym haśle, braku konta i koncie nieaktywnym (nie da się sprawdzić, czy e-mail jest w bazie).

Scenariusze testowe: [`docs/testy.md`](docs/testy.md).

## Panel administratora (etap 3)

Wszystkie strony w `public/admin/` wymagają roli administratora (`wymagajRoli(ROLA_ADMIN)`), a każda operacja zapisująca ma token CSRF i wpis w logu.

| Moduł | Plik | Zakres |
|---|---|---|
| Start | `index.php` | liczniki z bazy, wizyty na dziś, wykres wizyt z 6 miesięcy |
| Kategorie | `kategorie.php` | dodawanie, edycja, ukrywanie, usuwanie pustych kategorii |
| Usługi | `uslugi.php` | CRUD, kategoria, cena, czas, aktywność, wyszukiwanie i filtr |
| Inspektorzy | `inspektorzy.php` | nadanie roli istniejącemu kontu, dane, aktywność, przypisanie wielu usług (N:M) |
| Harmonogram | `harmonogram.php` | godziny pracy, przerwy, urlopy i inne nieobecności |
| Rezerwacje | `rezerwacje.php` | lista, filtry (data, status, inspektor, klient), paginacja, szczegóły, zmiana statusu, anulowanie |
| Użytkownicy | `uzytkownicy.php` | lista, wyszukiwanie, filtr roli, blokowanie kont, nadawanie i odbieranie roli administratora (nie dla własnego konta) |
| Log | `audyt.php` | podgląd `audit_logs`: kto, kiedy i co zmienił |

Zależności są obsłużone bezpiecznie: kategorii z usługami i usługi z rezerwacjami nie da się usunąć, można je tylko ukryć lub wyłączyć. Odznaczenie usługi u inspektora wyłącza przypisanie zamiast je kasować, bo stare rezerwacje wskazują na parę inspektor + usługa.

Statusy rezerwacji zmieniają się tylko w dozwolonej kolejności (`PRZEJSCIA_STATUSOW` w `includes/functions.php`): oczekująca → potwierdzona → zrealizowana, a anulować można wizytę oczekującą lub potwierdzoną.

### Funkcje dodatkowe etapu 3

- historia zmian statusu każdej rezerwacji (zapisują ją triggery w bazie),
- log operacji administratora z filtrem po rodzaju operacji,
- urlopy, zwolnienia i szkolenia z ostrzeżeniem o rezerwacjach w tym czasie,
- eksport rezerwacji do CSV z aktualnymi filtrami, z ochroną przed formułami w Excelu,
- wykres wizyt na stronie startowej z tabelą jako alternatywą,
- wyszukiwanie rezerwacji po kliencie i paginacja.

## Rezerwacje (etap 4)

Proces zawsze idzie w kolejności: **usługa → inspektor → data → godzina → potwierdzenie** (`public/rezerwacja.php`). Każdy krok jest parametrem w adresie i serwer sprawdza go od nowa.

**Jak liczone są wolne godziny** (`includes/rezerwacje.php`, funkcja `wolneGodziny()`):

1. Godziny pracy inspektora z `work_schedules` dla dnia tygodnia (albo wyjątkowy dyżur z `availability_overrides`). W święta z tabeli `holidays` nie ma terminów.
2. Od godzin pracy odejmowane są przerwy (`schedule_breaks`) i nieobecności (`inspector_absences`).
3. Terminy zaczynają się co 15 minut, a cała wizyta (czas trwania usługi) musi zmieścić się w godzinach pracy.
4. Odrzucane są terminy, które kolidują z oczekującą lub potwierdzoną wizytą inspektora albo z inną wizytą tego samego klienta, a także terminy w przeszłości i ponad 60 dni naprzód.

**Reguła kolizji** (`nakladajaSie()`): dwie wizyty kolidują, gdy nowa zaczyna się przed końcem istniejącej i kończy po jej początku. Wizyta 10:00–11:00 blokuje 9:30–10:30, 10:00–11:00, 10:15–10:45, 10:30–11:30 i 9:30–11:30, a pozwala na terminy kończące się o 10:00 i zaczynające o 11:00. Sprawdza to `tests/kolizje_test.php`.

**Zapis** (`zarezerwuj()`): w transakcji z blokadą wiersza inspektora (`SELECT ... FOR UPDATE`) termin jest sprawdzany jeszcze raz tuż przed `INSERT`. Gdy dwie osoby klikną ten sam termin, druga czeka na pierwszą i dostaje komunikat, że termin jest zajęty. Zmiana godziny w formularzu albo w adresie niczego nie omija.

| Rola | Strona | Co może |
|---|---|---|
| Obywatel | `konto/rezerwacje.php` | nadchodzące oraz zakończone i anulowane wizyty, anulowanie własnej przyszłej wizyty |
| Inspektor | `inspektor/index.php`, `inspektor/rezerwacje.php` | wizyty na dziś, nadchodzące i historia, dane klienta, potwierdzenie, odwołanie, oznaczenie jako zrealizowana (dopiero po rozpoczęciu) |

Własność danych jest sprawdzana w samym zapytaniu (`WHERE client_id = ...` / `WHERE inspector_id = ...`), więc podmiana numeru wizyty w adresie daje 404.

### Funkcje dodatkowe etapu 4

- wolne godziny dla wybranej daty pobierane przez AJAX (bez JS strona działa normalnie),
- pasek 14 najbliższych dni z liczbą wolnych terminów,
- przerwy, urlopy, wyjątkowe dyżury i święta w liczeniu dostępności,
- blokada podwójnej rezerwacji klienta u różnych inspektorów,
- historia statusów widoczna dla inspektora i administratora,
- testy automatyczne reguły kolizji.

## Struktura projektu
*(Będzie się zmieniać wraz z postępem prac.)*
```bash
mSygnalista/
├── public/                         # Strony dostępne w przeglądarce
│   ├── index.php                   # Strona główna
│   ├── uslugi.php                  # Oferta konsultacji
│   ├── rezerwacja.php              # Formularz rezerwacji
│   ├── zgloszenie.php              # Anonimowe zgłoszenie
│   ├── status.php                  # Status zgłoszenia po tokenie
│   ├── logowanie.php
│   ├── rejestracja.php
│   ├── wylogowanie.php
│   │
│   ├── konto/
│   │   ├── profil.php
│   │   └── rezerwacje.php          # Własne wizyty i ich anulowanie
│   │
│   ├── inspektor/
│   │   ├── index.php               # Podsumowanie
│   │   ├── rezerwacje.php
│   │   ├── zgloszenia.php
│   │   └── zgloszenie.php          # Szczegóły i obsługa sprawy
│   │
│   ├── admin/
│   │   ├── index.php               # Statystyki
│   │   ├── uzytkownicy.php
│   │   ├── inspektorzy.php
│   │   ├── uslugi.php
│   │   ├── kategorie.php
│   │   ├── harmonogram.php         # Godziny, przerwy i urlopy
│   │   ├── rezerwacje.php
│   │   ├── zgloszenia.php
│   │   └── audyt.php
│   │
│   ├── pobierz-zalacznik.php       # Pobranie po sprawdzeniu dostępu
│   └── assets/
│       ├── css/style.css
│       ├── js/app.js
│       └── images/
│
├── includes/                       # Wspólny kod PHP
│   ├── init.php                    # Konfiguracja, sesja i wspólne pliki
│   ├── db.php                      # Połączenie PDO
│   ├── auth.php                    # Logowanie i kontrola dostępu
│   ├── functions.php               # Escape HTML, CSRF, komunikaty
│   ├── rezerwacje.php              # Dostępność i zapisywanie wizyt
│   ├── zgloszenia.php              # Tokeny, zgłoszenia i załączniki
│   ├── header.php                  # Początek HTML i nawigacja
│   └── footer.php                  # Stopka
│
├── config/
│   ├── config.php                  # Lokalne dane połączenia; poza Git
│   └── config.example.php          # Wzór konfiguracji bez haseł
│
├── database/
│   ├── database.sql
│   └── db_ms.pdf                   # Schemat bazy (ERD)
│
├── storage/
│   ├── zalaczniki/                 # Pliki poza publicznym katalogiem
│   └── logs/                       # Błędy aplikacji
│
├── docs/
│   └── testy.md                    # Lista scenariuszy do sprawdzenia
│
├── tests/
│   └── kolizje_test.php            # Testy kolizji terminów (php tests/kolizje_test.php)
│
├── deploy/                         # Wersja online (Cloudflare Containers)
│   ├── Dockerfile                  # Apache + PHP + MariaDB
│   ├── start.sh                    # Start bazy i import database.sql
│   ├── apache-msygnalista.conf
│   ├── wrangler.jsonc              # Konfiguracja Workera i domeny
│   ├── package.json
│   └── src/index.ts                # Kod dostępu i przekazanie ruchu do kontenera
│
├── .gitignore
└── README.md
```

