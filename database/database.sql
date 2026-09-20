-- mSygnalista | Schemat bazy danych
-- Plik docelowy w repozytorium: /database/database.sql
-- Wymagania: MySQL >= 8.0.16 lub MariaDB >= 10.6, InnoDB, UTF-8.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
CREATE DATABASE msygnalista CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE msygnalista;

CREATE TABLE roles (
                     id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     code VARCHAR(40) NOT NULL UNIQUE,
                     name VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
                           id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                           code VARCHAR(80) NOT NULL UNIQUE,
                           description VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
                                role_id SMALLINT UNSIGNED NOT NULL,
                                permission_id SMALLINT UNSIGNED NOT NULL,
                                PRIMARY KEY (role_id, permission_id),
                                FOREIGN KEY (role_id) REFERENCES roles(id),
                                FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB;

CREATE TABLE users (
                     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     first_name VARCHAR(80) NOT NULL,
                     last_name VARCHAR(100) NOT NULL,
                     email VARCHAR(254) NOT NULL UNIQUE,
                     phone VARCHAR(25) NOT NULL,
                     password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                     is_active BOOLEAN NOT NULL DEFAULT TRUE,
                     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                     updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                     CONSTRAINT ck_user_names CHECK (CHAR_LENGTH(TRIM(first_name)) > 0 AND CHAR_LENGTH(TRIM(last_name)) > 0),
                     CONSTRAINT ck_user_contact CHECK (CHAR_LENGTH(TRIM(phone)) >= 7 AND email LIKE '%_@_%._%'),
                     CONSTRAINT ck_user_hash CHECK (CHAR_LENGTH(password_hash) >= 60),
                     CONSTRAINT ck_user_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE user_roles (
                          user_id BIGINT UNSIGNED NOT NULL,
                          role_id SMALLINT UNSIGNED NOT NULL,
                          PRIMARY KEY (user_id, role_id),
                          FOREIGN KEY (user_id) REFERENCES users(id),
                          FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE inspectors (
                          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                          user_id BIGINT UNSIGNED NOT NULL UNIQUE,
                          employee_number VARCHAR(30) NOT NULL UNIQUE,
                          institution VARCHAR(150) NOT NULL,
                          description TEXT,
                          is_active BOOLEAN NOT NULL DEFAULT TRUE,
                          FOREIGN KEY (user_id) REFERENCES users(id),
                          CONSTRAINT ck_inspector_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE service_categories (
                                  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                  name VARCHAR(120) NOT NULL UNIQUE,
                                  description TEXT,
                                  is_active BOOLEAN NOT NULL DEFAULT TRUE,
                                  CHECK (is_active IN (0,1)),
                                  CHECK (CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB;

CREATE TABLE report_categories (
                                 id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                 name VARCHAR(120) NOT NULL UNIQUE,
                                 description TEXT,
                                 is_active BOOLEAN NOT NULL DEFAULT TRUE,
                                 CHECK (is_active IN (0,1)),
                                 CHECK (CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB;

CREATE TABLE services (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        category_id SMALLINT UNSIGNED NOT NULL,
                        name VARCHAR(150) NOT NULL,
                        description TEXT NOT NULL,
                        duration_minutes SMALLINT UNSIGNED NOT NULL,
                        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        is_active BOOLEAN NOT NULL DEFAULT TRUE,
                        UNIQUE (category_id, name),
                        FOREIGN KEY (category_id) REFERENCES service_categories(id),
                        CONSTRAINT ck_service_duration CHECK (duration_minutes BETWEEN 5 AND 480),
                        CONSTRAINT ck_service_price CHECK (price >= 0),
                        CONSTRAINT ck_service_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE inspector_services (
                                  inspector_id BIGINT UNSIGNED NOT NULL,
                                  service_id INT UNSIGNED NOT NULL,
                                  is_active BOOLEAN NOT NULL DEFAULT TRUE,
                                  PRIMARY KEY (inspector_id, service_id),
                                  FOREIGN KEY (inspector_id) REFERENCES inspectors(id),
                                  FOREIGN KEY (service_id) REFERENCES services(id),
                                  CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE work_schedules (
                              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                              inspector_id BIGINT UNSIGNED NOT NULL,
                              weekday TINYINT UNSIGNED NOT NULL,
                              valid_from DATE NOT NULL,
                              valid_until DATE NOT NULL,
                              start_time TIME NOT NULL,
                              end_time TIME NOT NULL,
                              UNIQUE (inspector_id, weekday, valid_from, start_time),
                              FOREIGN KEY (inspector_id) REFERENCES inspectors(id),
                              CONSTRAINT ck_schedule_weekday CHECK (weekday BETWEEN 1 AND 7),
                              CONSTRAINT ck_schedule_dates CHECK (valid_until >= valid_from),
                              CONSTRAINT ck_schedule_times CHECK (start_time >= '00:00:00' AND start_time < end_time AND end_time <= '23:59:59')
) ENGINE=InnoDB;

CREATE TABLE schedule_breaks (
                               id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                               schedule_id BIGINT UNSIGNED NOT NULL,
                               start_time TIME NOT NULL,
                               end_time TIME NOT NULL,
                               UNIQUE (schedule_id, start_time),
                               FOREIGN KEY (schedule_id) REFERENCES work_schedules(id),
                               CHECK (start_time >= '00:00:00' AND start_time < end_time AND end_time <= '23:59:59')
) ENGINE=InnoDB;

CREATE TABLE availability_overrides (
                                      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                      inspector_id BIGINT UNSIGNED NOT NULL,
                                      work_date DATE NOT NULL,
                                      start_time TIME NOT NULL,
                                      end_time TIME NOT NULL,
                                      reason VARCHAR(255) NOT NULL,
                                      UNIQUE (inspector_id, work_date, start_time),
                                      FOREIGN KEY (inspector_id) REFERENCES inspectors(id),
                                      CHECK (start_time >= '00:00:00' AND start_time < end_time AND end_time <= '23:59:59')
) ENGINE=InnoDB;

CREATE TABLE inspector_absences (
                                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                  inspector_id BIGINT UNSIGNED NOT NULL,
                                  starts_at DATETIME NOT NULL,
                                  ends_at DATETIME NOT NULL,
                                  absence_type VARCHAR(20) NOT NULL,
                                  note VARCHAR(255),
                                  created_by BIGINT UNSIGNED NOT NULL,
                                  FOREIGN KEY (inspector_id) REFERENCES inspectors(id),
                                  FOREIGN KEY (created_by) REFERENCES users(id),
                                  KEY idx_absence_window (inspector_id, starts_at, ends_at),
                                  CHECK (ends_at > starts_at),
                                  CHECK (absence_type IN ('vacation','sick_leave','training','other'))
) ENGINE=InnoDB;

CREATE TABLE holidays (
                        holiday_date DATE PRIMARY KEY,
                        name VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE reservation_statuses (
                                    code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
                                    name VARCHAR(80) NOT NULL UNIQUE,
                                    CHECK (code IN ('pending','confirmed','completed','cancelled'))
) ENGINE=InnoDB;

CREATE TABLE report_statuses (
                               code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
                               name VARCHAR(80) NOT NULL UNIQUE,
                               CHECK (code IN ('new','in_review','resolved','rejected'))
) ENGINE=InnoDB;

CREATE TABLE reservations (
                            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            client_id BIGINT UNSIGNED NOT NULL,
                            inspector_id BIGINT UNSIGNED NOT NULL,
                            service_id INT UNSIGNED NOT NULL,
                            starts_at DATETIME NOT NULL,
                            ends_at DATETIME NOT NULL,
                            price_at_booking DECIMAL(10,2) NOT NULL,
                            status_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                            comment VARCHAR(1000),
                            changed_by BIGINT UNSIGNED NOT NULL,
                            change_reason VARCHAR(255) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (client_id) REFERENCES users(id),
                            FOREIGN KEY (inspector_id, service_id) REFERENCES inspector_services(inspector_id, service_id),
                            FOREIGN KEY (status_code) REFERENCES reservation_statuses(code),
                            FOREIGN KEY (changed_by) REFERENCES users(id),
                            KEY idx_reservation_calendar (inspector_id, starts_at, ends_at),
                            KEY idx_reservation_client (client_id, starts_at),
                            KEY idx_reservation_status_date (status_code, starts_at),
                            CONSTRAINT ck_reservation_period CHECK (ends_at > starts_at AND DATE(starts_at) = DATE(ends_at)),
 CONSTRAINT ck_reservation_price CHECK (price_at_booking >= 0),
 CONSTRAINT ck_reservation_reason CHECK (CHAR_LENGTH(TRIM(change_reason)) > 0)
) ENGINE=InnoDB;

CREATE TABLE reports (
                       id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                       category_id SMALLINT UNSIGNED NOT NULL,
                       assigned_inspector_id BIGINT UNSIGNED NULL,
                       token_hash BINARY(32) NOT NULL UNIQUE,
                       title VARCHAR(180) NOT NULL,
                       description TEXT NOT NULL,
                       incident_at DATETIME NULL,
                       incident_location VARCHAR(255) NULL,
                       status_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'new',
                       public_message VARCHAR(1000) NULL,
                       changed_by BIGINT UNSIGNED NULL,
                       change_reason VARCHAR(255) NOT NULL DEFAULT 'Przyjęcie anonimowego zgłoszenia',
                       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                       updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       FOREIGN KEY (category_id) REFERENCES report_categories(id),
                       FOREIGN KEY (assigned_inspector_id) REFERENCES inspectors(id),
                       FOREIGN KEY (status_code) REFERENCES report_statuses(code),
                       FOREIGN KEY (changed_by) REFERENCES users(id),
                       KEY idx_report_queue (assigned_inspector_id, status_code, created_at),
                       KEY idx_report_status_date (status_code, created_at),
                       CHECK (CHAR_LENGTH(TRIM(title)) > 0 AND CHAR_LENGTH(TRIM(description)) >= 20),
                       CHECK (CHAR_LENGTH(TRIM(change_reason)) > 0)
) ENGINE=InnoDB;

CREATE TABLE report_attachments (
                                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                  report_id BIGINT UNSIGNED NOT NULL,
                                  storage_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
                                  original_name VARCHAR(255) NOT NULL,
                                  mime_type VARCHAR(80) NOT NULL,
                                  size_bytes BIGINT UNSIGNED NOT NULL,
                                  sha256 BINARY(32) NOT NULL,
                                  scan_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                                  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                  FOREIGN KEY (report_id) REFERENCES reports(id),
                                  CHECK (size_bytes BETWEEN 1 AND 20971520),
                                  CHECK (mime_type IN ('application/pdf','image/jpeg','image/png','video/mp4')),
                                  CHECK (scan_status IN ('pending','clean','blocked'))
) ENGINE=InnoDB;

CREATE TABLE reservation_status_history (
                                          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                          reservation_id BIGINT UNSIGNED NOT NULL,
                                          old_status_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
                                          new_status_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                                          changed_by BIGINT UNSIGNED NULL,
                                          reason VARCHAR(255) NOT NULL,
                                          changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                          FOREIGN KEY (reservation_id) REFERENCES reservations(id),
                                          FOREIGN KEY (old_status_code) REFERENCES reservation_statuses(code),
                                          FOREIGN KEY (new_status_code) REFERENCES reservation_statuses(code),
                                          FOREIGN KEY (changed_by) REFERENCES users(id),
                                          KEY idx_reservation_history (reservation_id, changed_at, id),
                                          CHECK (old_status_code IS NULL OR old_status_code <> new_status_code)
) ENGINE=InnoDB;

CREATE TABLE report_status_history (
                                     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                     report_id BIGINT UNSIGNED NOT NULL,
                                     old_status_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
                                     new_status_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                                     changed_by BIGINT UNSIGNED NULL,
                                     reason VARCHAR(255) NOT NULL,
                                     changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                     FOREIGN KEY (report_id) REFERENCES reports(id),
                                     FOREIGN KEY (old_status_code) REFERENCES report_statuses(code),
                                     FOREIGN KEY (new_status_code) REFERENCES report_statuses(code),
                                     FOREIGN KEY (changed_by) REFERENCES users(id),
                                     KEY idx_report_history (report_id, changed_at, id),
                                     CHECK (old_status_code IS NULL OR old_status_code <> new_status_code)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
                          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                          actor_id BIGINT UNSIGNED NOT NULL,
                          action_code VARCHAR(80) NOT NULL,
                          entity_type VARCHAR(60) NOT NULL,
                          entity_id BIGINT UNSIGNED NOT NULL,
                          description VARCHAR(1000) NOT NULL,
                          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          FOREIGN KEY (actor_id) REFERENCES users(id),
                          KEY idx_audit_actor_date (actor_id, created_at),
                          KEY idx_audit_entity (entity_type, entity_id, created_at)
) ENGINE=InnoDB;

DELIMITER $$

CREATE TRIGGER trg_reservation_insert_history
  AFTER INSERT ON reservations FOR EACH ROW
BEGIN
  INSERT INTO reservation_status_history
  (reservation_id, old_status_code, new_status_code, changed_by, reason)
  VALUES (NEW.id, NULL, NEW.status_code, NEW.changed_by, NEW.change_reason);
  END$$

  CREATE TRIGGER trg_reservation_update_history
    AFTER UPDATE ON reservations FOR EACH ROW
  BEGIN
    IF OLD.status_code <> NEW.status_code THEN
    INSERT INTO reservation_status_history
      (reservation_id, old_status_code, new_status_code, changed_by, reason)
    VALUES (NEW.id, OLD.status_code, NEW.status_code, NEW.changed_by, NEW.change_reason);
  END IF;
  END$$

  CREATE TRIGGER trg_report_insert_history
    AFTER INSERT ON reports FOR EACH ROW
  BEGIN
    INSERT INTO report_status_history
    (report_id, old_status_code, new_status_code, changed_by, reason)
    VALUES (NEW.id, NULL, NEW.status_code, NEW.changed_by, NEW.change_reason);
    END$$

    CREATE TRIGGER trg_report_update_history
      AFTER UPDATE ON reports FOR EACH ROW
    BEGIN
      IF OLD.status_code <> NEW.status_code THEN
    INSERT INTO report_status_history
      (report_id, old_status_code, new_status_code, changed_by, reason)
    VALUES (NEW.id, OLD.status_code, NEW.status_code, NEW.changed_by, NEW.change_reason);
    END IF;
    END$$

    CREATE TRIGGER trg_reservation_status_history_no_update
      BEFORE UPDATE ON reservation_status_history FOR EACH ROW
    BEGIN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historia i audyt sa tylko do dopisywania';
END$$

      CREATE TRIGGER trg_reservation_status_history_no_delete
        BEFORE DELETE ON reservation_status_history FOR EACH ROW
      BEGIN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historia i audyt sa tylko do dopisywania';
END$$

        CREATE TRIGGER trg_report_status_history_no_update
          BEFORE UPDATE ON report_status_history FOR EACH ROW
        BEGIN
          SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historia i audyt sa tylko do dopisywania';
END$$

          CREATE TRIGGER trg_report_status_history_no_delete
            BEFORE DELETE ON report_status_history FOR EACH ROW
          BEGIN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historia i audyt sa tylko do dopisywania';
END$$

            CREATE TRIGGER trg_audit_logs_no_update
              BEFORE UPDATE ON audit_logs FOR EACH ROW
            BEGIN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historia i audyt sa tylko do dopisywania';
END$$

              CREATE TRIGGER trg_audit_logs_no_delete
                BEFORE DELETE ON audit_logs FOR EACH ROW
              BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historia i audyt sa tylko do dopisywania';
END$$

                DELIMITER ;

                START TRANSACTION;

                INSERT INTO roles (id,code,name) VALUES
                                                   (1,'citizen','Obywatel'),(2,'inspector','Inspektor'),(3,'admin','Administrator');
                INSERT INTO permissions (id,code,description) VALUES
                                                                (1,'reservation.create','Rezerwowanie konsultacji dla siebie'),
                                                                (2,'reservation.read_own','Odczyt własnych rezerwacji'),
                                                                (3,'reservation.cancel_own','Anulowanie własnych przyszłych rezerwacji'),
                                                                (4,'reservation.manage_assigned','Obsługa wizyt przypisanych do inspektora'),
                                                                (5,'report.manage_assigned','Obsługa przypisanych zgłoszeń'),
                                                                (6,'catalog.manage','Zarządzanie kategoriami i usługami'),
                                                                (7,'users.manage','Zarządzanie użytkownikami i rolami'),
                                                                (8,'schedule.manage','Zarządzanie dostępnością i urlopami'),
                                                                (9,'reservation.manage_all','Zarządzanie wszystkimi rezerwacjami'),
                                                                (10,'report.manage_all','Zarządzanie i przydzielanie zgłoszeń'),
                                                                (11,'audit.read','Odczyt audytu');
                INSERT INTO role_permissions VALUES (1,1),(1,2),(1,3),(2,4),(2,5);
                INSERT INTO role_permissions SELECT 3,id FROM permissions;
                INSERT INTO users (id,first_name,last_name,email,phone,password_hash,is_active) VALUES

                                                                                                  (1,'Alicja','Administratorska','admin@example.test','+48000000001','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',1),
                                                                                                  (2,'Anna','Inspektorska','inspektor.pip@example.test','+48000000002','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',1),
                                                                                                  (3,'Piotr','Drogowy','inspektor.drogi@example.test','+48000000003','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',1),
                                                                                                  (4,'Ewa','Kontrolna','inspektor.instytucje@example.test','+48000000004','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',1),
                                                                                                  (5,'Jan','Testowy','klient.jan@example.test','+48000000005','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',1),
                                                                                                  (6,'Maria','Przykładowa','klient.maria@example.test','+48000000006','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',1),
                                                                                                  (7,'Adam','Demonstracyjny','klient.adam@example.test','+48000000007','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',1),
                                                                                                  (8,'Ola','Nieaktywna','klient.nieaktywny@example.test','+48000000008','$2y$10$U.vI48CxvqTFjsEny.GCTOUqIPkGk5dx0Dtz0XaB0D/wGm6CC2HTO',0);

                INSERT INTO user_roles VALUES (1,3),(2,2),(3,2),(4,2),(5,1),(6,1),(7,1),(8,1);
                INSERT INTO inspectors (id,user_id,employee_number,institution,description) VALUES
                                                                                              (1,2,'DEMO-PIP-001','Jednostka demonstracyjna PIP','Prawo pracy i bezpieczeństwo zatrudnienia.'),
                                                                                              (2,3,'DEMO-DR-002','Jednostka demonstracyjna ruchu drogowego','Zgłoszenia drogowe.'),
                                                                                              (3,4,'DEMO-IN-003','Jednostka demonstracyjna kontroli','Nieprawidłowości instytucjonalne.');
                INSERT INTO service_categories (id,name,description) VALUES
                                                                       (1,'Prawo pracy','Konsultacje pracownicze'),(2,'Ruch drogowy','Konsultacje drogowe'),
                                                                       (3,'Instytucje publiczne','Konsultacje dotyczące instytucji');
                INSERT INTO report_categories (id,name,description) VALUES
                                                                      (1,'Naruszenie praw pracowniczych','Wynagrodzenie, czas pracy, BHP'),
                                                                      (2,'Wykroczenie drogowe','Niebezpieczne zachowania drogowe'),
                                                                      (3,'Nieprawidłowości instytucjonalne','Nieprawidłowości w działaniu instytucji');
                INSERT INTO services (id,category_id,name,description,duration_minutes,price,is_active) VALUES
                                                                                                          (1,1,'Podstawy prawa pracy','Omówienie praw i obowiązków pracownika.',30,0,1),
                                                                                                          (2,1,'Konsultacja BHP','Omówienie zagrożeń w miejscu pracy.',60,0,1),
                                                                                                          (3,2,'Zgłoszenie drogowe - konsultacja','Pomoc w opisaniu zdarzenia.',30,0,1),
                                                                                                          (4,3,'Procedura zgłoszenia','Omówienie trybu rozpatrywania sprawy.',45,0,1),
                                                                                                          (5,1,'Dokumenty pracownicze','Omówienie dokumentów zatrudnienia.',45,0,1),
                                                                                                          (6,3,'Konsultacja archiwalna','Przykład wycofanej usługi.',30,0,0);
                INSERT INTO inspector_services VALUES
                                                 (1,1,1),(1,2,1),(1,5,1),(2,3,1),(3,1,1),(3,4,1),(3,6,0);

                INSERT INTO work_schedules (inspector_id,weekday,valid_from,valid_until,start_time,end_time) VALUES
                                                                                                               (1,1,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (1,2,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (1,3,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (1,4,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (1,5,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (2,1,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (2,2,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (2,3,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (2,4,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (2,5,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (3,1,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (3,2,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (3,3,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (3,4,'2026-01-01','2027-12-31','08:00:00','16:00:00'),
                                                                                                               (3,5,'2026-01-01','2027-12-31','08:00:00','16:00:00');

                INSERT INTO schedule_breaks (schedule_id,start_time,end_time)
                SELECT id,'12:00:00','12:30:00' FROM work_schedules;
                INSERT INTO availability_overrides (inspector_id,work_date,start_time,end_time,reason) VALUES
                                                                                                         (1,'2026-10-02','10:00','12:00','Szkolenie rano - skrócony dyżur'),
                                                                                                         (1,'2026-10-02','13:00','15:00','Druga część dyżuru po przerwie'),
                                                                                                         (2,'2026-10-03','09:00','12:00','Dodatkowy dyżur w sobotę');
                INSERT INTO inspector_absences (inspector_id,starts_at,ends_at,absence_type,note,created_by) VALUES
                                                                                                               (1,'2026-10-05 00:00','2026-10-10 00:00','vacation','Urlop demonstracyjny',1),
                                                                                                               (2,'2026-10-01 08:00','2026-10-01 10:00','training','Szkolenie',1),
                                                                                                               (3,'2026-10-06 00:00','2026-10-07 00:00','other','Dzień wolny',1);
                INSERT INTO holidays VALUES ('2026-11-11','Święto Niepodległości'),
                                            ('2026-12-24','Wigilia'),('2026-12-25','Boże Narodzenie'),('2026-12-26','Drugi dzień Bożego Narodzenia');
                INSERT INTO reservation_statuses VALUES ('pending','Oczekująca'),('confirmed','Potwierdzona'),
                                                        ('completed','Zrealizowana'),('cancelled','Anulowana');
                INSERT INTO report_statuses VALUES ('new','Nowe'),('in_review','W trakcie analizy'),
                                                   ('resolved','Rozpatrzone'),('rejected','Odrzucone');
                INSERT INTO reservations (id,client_id,inspector_id,service_id,starts_at,ends_at,price_at_booking,changed_by,change_reason) VALUES
                                                                                                                                              (1,5,1,1,'2026-09-21 09:00','2026-09-21 09:30',0,5,'Rezerwacja klienta'),
                                                                                                                                              (2,6,1,2,'2026-09-21 10:00','2026-09-21 11:00',0,6,'Rezerwacja klienta'),
                                                                                                                                              (3,7,1,1,'2026-09-21 11:00','2026-09-21 11:30',0,7,'Wizyta dokładnie po poprzedniej'),
                                                                                                                                              (4,5,2,3,'2026-09-22 09:00','2026-09-22 09:30',0,5,'Rezerwacja klienta'),
                                                                                                                                              (5,6,3,4,'2026-09-22 10:00','2026-09-22 10:45',0,6,'Rezerwacja klienta'),
                                                                                                                                              (6,7,3,1,'2026-09-18 09:00','2026-09-18 09:30',0,7,'Import wizyty historycznej'),
                                                                                                                                              (7,5,1,5,'2026-10-02 10:00','2026-10-02 10:45',0,5,'Termin z grafiku wyjątkowego'),
                                                                                                                                              (8,6,2,3,'2026-10-03 09:00','2026-10-03 09:30',0,6,'Dodatkowy dyżur sobotni');
                UPDATE reservations SET status_code='confirmed',changed_by=2,change_reason='Potwierdzenie inspektora' WHERE id IN (1,2,7);
                UPDATE reservations SET status_code='confirmed',changed_by=4,change_reason='Potwierdzenie wizyty historycznej' WHERE id=6;
                UPDATE reservations SET status_code='completed',changed_by=4,change_reason='Konsultacja została zakończona' WHERE id=6;
                UPDATE reservations SET status_code='cancelled',changed_by=5,change_reason='Klient zrezygnował' WHERE id=4;
                INSERT INTO reports (id,category_id,token_hash,title,description,incident_at,incident_location) VALUES
                                                                                                                  (1,1,UNHEX(SHA2('demo-msygnalista-01',256)),'Przykład: zaległe wynagrodzenie','Fikcyjny opis niewypłaconego wynagrodzenia do testów systemu.','2026-09-10 10:00','Miejscowość demonstracyjna'),
                                                                                                                  (2,2,UNHEX(SHA2('demo-msygnalista-02',256)),'Przykład: niebezpieczny manewr','Fikcyjny opis niebezpiecznego zdarzenia drogowego do testów.','2026-09-12 15:00','Ulica Testowa'),
                                                                                                                  (3,3,UNHEX(SHA2('demo-msygnalista-03',256)),'Przykład: procedura urzędowa','Fikcyjny opis problemu z procedurą w instytucji publicznej.',NULL,NULL),
                                                                                                                  (4,1,UNHEX(SHA2('demo-msygnalista-04',256)),'Przykład: niewłaściwa kategoria','Fikcyjna sprawa pozostająca poza zakresem działania jednostki.',NULL,NULL);
                UPDATE reports SET assigned_inspector_id=1,status_code='in_review',changed_by=2,change_reason='Rozpoczęcie analizy',public_message='Sprawa jest analizowana.' WHERE id=1;
                UPDATE reports SET assigned_inspector_id=3,status_code='in_review',changed_by=4,change_reason='Przyjęcie do analizy' WHERE id=3;
                UPDATE reports SET status_code='resolved',changed_by=4,change_reason='Zakończenie analizy',public_message='Rozpatrywanie sprawy zakończono.' WHERE id=3;
                UPDATE reports SET assigned_inspector_id=1,status_code='rejected',changed_by=2,change_reason='Poza właściwością jednostki',public_message='Sprawa poza zakresem obsługi tej jednostki.' WHERE id=4;
                INSERT INTO report_attachments (report_id,storage_key,original_name,mime_type,size_bytes,sha256) VALUES
                                                                                                                   (1,'demo/evidence-01.pdf','dokument-demo.pdf','application/pdf',1024,UNHEX(SHA2('fictional-file-01',256))),
                                                                                                                   (2,'demo/evidence-02.jpg','zdjecie-demo.jpg','image/jpeg',2048,UNHEX(SHA2('fictional-file-02',256)));
                INSERT INTO audit_logs (actor_id,action_code,entity_type,entity_id,description) VALUES
                                                                                                  (1,'inspector.create','inspectors',1,'Utworzenie profilu inspektora demonstracyjnego'),
                                                                                                  (1,'absence.create','inspector_absences',1,'Dodanie urlopu inspektora'),
                                                                                                  (1,'schedule.override','availability_overrides',1,'Dodanie wyjątkowego dyżuru'),
                                                                                                  (2,'report.status_change','reports',1,'Zmiana statusu sprawy na in_review'),
                                                                                                  (4,'reservation.status_change','reservations',6,'Zakończenie konsultacji');
                COMMIT;

                SET SESSION sql_mode = @previous_sql_mode;
