

# ![Logo](https://zapodaj.net/images/01b22954bf7b0.png)

**Spełniaj z nami obowiązek obywatelski jeszcze prościej niż kiedykolwiek.**




## O projekcie

**mSygnalista** to nowoczesna, bezpieczna platforma webowa inspirowana oficjalnymi rozwiązaniami rządowymi. Jej głównym zadaniem jest ułatwienie obywatelom zgłaszania wykroczeń drogowych, naruszeń praw pracowniczych oraz innych nieprawidłowości, a także rezerwowania oficjalnych konsultacji z inspektorami państwowymi (m.in. z Państwową Inspekcją Pracy).


## Funkcjonalności

- **Anonimowe i bezpieczne zgłoszenia:** Możliwość zgłaszania naruszeń (drogowych, pracowniczych, instytucjonalnych) wraz z załącznikami i automatycznym generowaniem unikalnych tokenów dostępu.
- **Śledzenie statusu sprawy po tokenie:** System pozwala obywatelom na weryfikację etapu rozpatrywania zgłoszenia bez konieczności zakładania konta czy podawania danych osobowych.
- **Rezerwacja konsultacji online:** Zintegrowany kalendarz wizyt z automatyczną weryfikacją dostępności terminów oraz regułami konfliktów czasowych.
- **Dedykowany panel eksperta / PIP**: Strefa dla inspektorów i administratorów do zarządzania zgłoszeniami i harmonogramem spotkań.
- **Interfejs**: Czysty, jasny design w stylu aplikacji mObywatel oparty na nowoczesnym frameworku Tailwind CSS, w pełni responsywny i dostosowany do urządzeń mobilnych.


## Technologie

- **Frontend:** HTML5, Tailwind CSS, FontAwesome 6

- **Backend:** PHP

- **Baza danych:** MySQL / MariaDB

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
│   └── erd.pdf
│
├── storage/
│   ├── zalaczniki/                 # Pliki poza publicznym katalogiem
│   └── logs/                       # Błędy aplikacji
│
├── docs/
│   └── testy.md                    # Lista scenariuszy do sprawdzenia
│
├── .gitignore
└── README.md
```

