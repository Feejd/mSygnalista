# Scenariusze testowe

Hasło do kont testowych: `Demo!mSygnalista2026` (szczegóły w README).

## Etap 2 – użytkownicy i logowanie

### Rejestracja

| # | Kroki | Oczekiwany wynik |
|---|---|---|
| R1 | Wyślij pusty formularz | Komunikat przy każdym polu, nic nie zapisuje się w bazie |
| R2 | E-mail `zly@`, telefon `12ab` | „Adres e-mail ma nieprawidłowy format”, błąd telefonu |
| R3 | Różne hasła w polach hasło / powtórz | „Hasła nie są takie same” |
| R4 | Hasło `abcdefgh` (bez cyfry) | Błąd o literze i cyfrze |
| R5 | E-mail `Klient.Jan@example.test` (istniejący, inna wielkość liter) | „Konto z tym adresem e-mail już istnieje” |
| R6 | Imię `<script>alert(1)</script>` | Błąd walidacji, w polu widać tekst, skrypt się nie wykonuje |
| R7 | Poprawne dane | Konto utworzone, automatyczne zalogowanie, przejście do profilu, w bazie hash `$2y$...` i rola `citizen` |

### Logowanie i sesja

| # | Kroki | Oczekiwany wynik |
|---|---|---|
| L1 | Zalogowanie jako `admin@example.test` | Przejście do `admin/index.php` |
| L2 | Zalogowanie jako `inspektor.pip@example.test` | Przejście do `inspektor/index.php` |
| L3 | Zalogowanie jako `klient.jan@example.test` | Przejście do `konto/profil.php` |
| L4 | Złe hasło / nieistniejący e-mail | Ten sam komunikat „Nieprawidłowy e-mail lub hasło” |
| L5 | `klient.nieaktywny@example.test` z poprawnym hasłem | Logowanie odrzucone |
| L6 | 5 razy złe hasło dla jednego e-maila, potem poprawne | „Zbyt wiele nieudanych prób…”, odblokowanie po 15 min |
| L7 | Kliknięcie „Wyloguj” | Powrót na stronę główną, `konto/profil.php` przekierowuje do logowania |
| L8 | Wejście na `wylogowanie.php` z paska adresu (GET) | Brak wylogowania – wymagany POST z tokenem |
| L9 | Dezaktywacja konta w bazie w trakcie sesji | Przy następnym kliknięciu użytkownik jest wylogowany |

### Uprawnienia (ręczne wpisanie adresu)

| # | Zalogowany jako | Adres | Oczekiwany wynik |
|---|---|---|---|
| U1 | niezalogowany | `admin/index.php`, `inspektor/index.php`, `konto/profil.php` | Przekierowanie do logowania |
| U2 | obywatel | `admin/index.php` | 403 „Nie masz uprawnień” |
| U3 | obywatel | `inspektor/index.php` | 403 |
| U4 | inspektor | `admin/index.php` | 403 |
| U5 | administrator | `inspektor/index.php` | Dostęp |

### Profil

| # | Kroki | Oczekiwany wynik |
|---|---|---|
| P1 | Zmiana telefonu na `+48 500 600 700` | Zapisane jako `+48500600700` |
| P2 | Zmiana e-maila bez podania hasła | Błąd „podaj poprawne obecne hasło” |
| P3 | Zmiana e-maila na `admin@example.test` | „Ten adres e-mail jest już używany” |
| P4 | Zmiana hasła ze złym obecnym hasłem | Błąd, hasło bez zmian |
| P5 | Nowe hasło takie samo jak stare | Błąd |
| P6 | Poprawna zmiana hasła, wylogowanie, logowanie nowym | Działa |
| P7 | Formularz wysłany bez `csrf_token` (np. z innej strony) | 403 |
