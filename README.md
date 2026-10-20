

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

