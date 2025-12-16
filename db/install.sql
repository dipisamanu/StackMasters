DROP DATABASE IF EXISTS biblioteca_db;
CREATE DATABASE biblioteca_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblioteca_db;

CREATE TABLE Autori
(
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nome                 VARCHAR(100) NOT NULL,
    cognome              VARCHAR(100) NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Lingue
(
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nome                 VARCHAR(60) NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Generi
(
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nome                 VARCHAR(60) NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Ruoli
(
    id_ruolo             INT AUTO_INCREMENT PRIMARY KEY,
    priorita             TINYINT UNSIGNED NOT NULL UNIQUE COMMENT '0=Admin, 1=Bibliotecario, 2=Docente, 3=Studente',
    nome                 VARCHAR(15)      NOT NULL, -- Es. 'Admin', 'Studente'
    durata_prestito      TINYINT          NULL COMMENT 'Giorni',
    limite_prestiti      TINYINT          NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Badge
(
    id_badge    INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(50) UNIQUE NOT NULL,
    descrizione TEXT,
    icona_url   TEXT
);

CREATE TABLE RFID
(
    id_rfid              INT AUTO_INCREMENT PRIMARY KEY,
    rfid                 VARCHAR(128) UNIQUE      NOT NULL,
    tipo                 ENUM ('UTENTE', 'LIBRO') NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Libri
(
    id_libro             INT AUTO_INCREMENT PRIMARY KEY,
    titolo               VARCHAR(100) NOT NULL,
    descrizione          TEXT,
    isbn                 VARCHAR(17) UNIQUE,
    anno_uscita          DATETIME,
    editore              VARCHAR(100),
    lingua_id            INT,
    lingua_originale_id  INT,
    numero_pagine        INT,
    valore_copertina     DECIMAL(7, 2),
    rating               FLOAT     DEFAULT 0,
    copertina_url        TEXT,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lingua_id) REFERENCES Lingue (id),
    FOREIGN KEY (lingua_originale_id) REFERENCES Lingue (id)
);

CREATE TABLE Utenti
(
    id_utente               INT AUTO_INCREMENT PRIMARY KEY,
    cf                      CHAR(16) UNIQUE    NOT NULL,
    nome                    VARCHAR(100)       NOT NULL,
    cognome                 VARCHAR(100)       NOT NULL,
    email                   VARCHAR(255)       NOT NULL,
    password                VARCHAR(255)       NOT NULL,
    data_nascita            DATETIME,
    sesso                   ENUM ('M', 'F'),
    comune_nascita          VARCHAR(100),
    token                   VARCHAR(32),
    email_verificata        BOOLEAN                     DEFAULT FALSE,
    scadenza_verifica       TIMESTAMP          NULL,
    tentativi_login_falliti TINYINT                     DEFAULT 0,
    blocco_account_fino_al  TIMESTAMP          NULL,
    consenso_privacy        BOOLEAN            NOT NULL DEFAULT 0,
    notifiche_attive        BOOLEAN                     DEFAULT TRUE,
    quiet_hours_start       TIME,
    quiet_hours_end         TIME,
    livello_xp              INT UNSIGNED                DEFAULT 0,
    id_rfid                 INT UNIQUE,
    data_creazione          TIMESTAMP                   DEFAULT CURRENT_TIMESTAMP,
    ultimo_aggiornamento    TIMESTAMP                   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_rfid) REFERENCES RFID (id_rfid) ON DELETE SET NULL
);

CREATE TABLE Libri_Autori
(
    id_autore            INT,
    id_libro             INT,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_autore, id_libro),
    FOREIGN KEY (id_autore) REFERENCES Autori (id) ON DELETE CASCADE,
    FOREIGN KEY (id_libro) REFERENCES Libri (id_libro) ON DELETE CASCADE
);

CREATE TABLE Libri_Generi
(
    id_genere            INT,
    id_libro             INT,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_genere, id_libro),
    FOREIGN KEY (id_genere) REFERENCES Generi (id) ON DELETE CASCADE,
    FOREIGN KEY (id_libro) REFERENCES Libri (id_libro) ON DELETE CASCADE
);

CREATE TABLE Utenti_Ruoli
(
    id_utente           INT,
    id_ruolo            INT,
    prestiti_tot        INT          DEFAULT 0,
    streak_restituzioni INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id_utente, id_ruolo),
    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente) ON DELETE CASCADE,
    FOREIGN KEY (id_ruolo) REFERENCES Ruoli (id_ruolo)
);

CREATE TABLE Utenti_Badge
(
    id_utente          INT,
    id_badge           INT,
    data_conseguimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_utente, id_badge),
    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente) ON DELETE CASCADE,
    FOREIGN KEY (id_badge) REFERENCES Badge (id_badge) ON DELETE CASCADE
);

CREATE TABLE Inventari
(
    id_inventario        INT AUTO_INCREMENT PRIMARY KEY,
    id_libro             INT NOT NULL,
    id_rfid              INT UNIQUE,
    stato                ENUM ('DISPONIBILE','IN_PRESTITO','PRENOTATO','NON_IN_PRESTITO') DEFAULT 'DISPONIBILE',
    condizione           ENUM ('BUONO', 'DANNEGGIATO', 'PERSO')                           DEFAULT 'BUONO',
    collocazione         VARCHAR(20),
    ultimo_aggiornamento TIMESTAMP                                                        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_libro) REFERENCES Libri (id_libro),
    FOREIGN KEY (id_rfid) REFERENCES RFID (id_rfid)
);

CREATE TABLE Prestiti
(
    id_prestito          INT AUTO_INCREMENT PRIMARY KEY,
    id_inventario        INT       NOT NULL,
    id_utente            INT       NOT NULL,
    data_prestito        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scadenza_prestito    TIMESTAMP NULL,
    data_restituzione    TIMESTAMP NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_inventario) REFERENCES Inventari (id_inventario),
    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente)
);

CREATE TABLE Prenotazioni
(
    id_prenotazione      INT AUTO_INCREMENT PRIMARY KEY,
    id_utente            INT       NOT NULL,
    id_libro             INT       NOT NULL, -- Prenoto il Titolo
    copia_libro          INT       NULL,     -- FK Inventari: popolato solo quando la copia viene assegnata
    data_richiesta       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_disponibilita   TIMESTAMP NULL,
    scadenza_ritiro      TIMESTAMP NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente),
    FOREIGN KEY (id_libro) REFERENCES Libri (id_libro),
    FOREIGN KEY (copia_libro) REFERENCES Inventari (id_inventario)
);

CREATE TABLE Multe
(
    id_multa             INT AUTO_INCREMENT PRIMARY KEY,
    id_utente            INT                       NOT NULL,
    giorni               INT                       NULL,
    importo              DECIMAL(10, 2)            NOT NULL,
    causa                ENUM ('RITARDO', 'DANNI') NOT NULL,
    commento             TEXT,
    data_creazione       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_pagamento       TIMESTAMP                 NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente)
);

CREATE TABLE Recensioni
(
    id_recensione  INT AUTO_INCREMENT PRIMARY KEY,
    id_libro       INT NOT NULL,
    id_utente      INT NOT NULL,
    voto           INT CHECK (voto BETWEEN 1 AND 5),
    descrizione    TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_update    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_libro) REFERENCES Libri (id_libro),
    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente)
);

CREATE TABLE Logs_Audit
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    id_utente  INT                                                                                                     NULL,
    azione     ENUM ('LOGIN_FALLITO', 'LOGIN_SUCCESS', 'CREAZIONE_UTENTE', 'MODIFICA_PRESTITO', 'CANCELLAZIONE_LIBRO') NOT NULL,
    dettagli   TEXT,
    ip_address INT UNSIGNED COMMENT 'IPv4 convertito con INET_ATON',
    ipv6       VARBINARY(16) COMMENT 'IPv6 convertito con INET6_ATON',
    timestamp  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente) ON DELETE SET NULL
);

-- AGGIUNTA TABELLA STORICO NOTIFICHE --

-- TABELLA NOTIFICHE_WEB
CREATE TABLE Notifiche_Web
(
    id_notifica      INT AUTO_INCREMENT PRIMARY KEY,
    id_utente        INT NOT NULL,
    tipo             ENUM ('INFO', 'WARNING', 'DANGER', 'SUCCESS') NOT NULL,
    titolo           VARCHAR(100) NOT NULL,
    messaggio        TEXT NOT NULL,
    link_azione      VARCHAR(255), -- Rimane per creare link diretti nella pagina Archivio e nell'email

    -- Gestione Archivio Web
    letto            BOOLEAN DEFAULT FALSE,

    -- Gestione Email
    stato_email      ENUM ('NON_RICHIESTA', 'DA_INVIARE', 'INVIATA', 'FALLITA') DEFAULT 'DA_INVIARE',

    data_creazione   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_invio_email TIMESTAMP NULL,

    FOREIGN KEY (id_utente) REFERENCES Utenti (id_utente) ON DELETE CASCADE
);

-- =========================
-- DATI DI ESEMPIO ESTESI (CORRETTI)
-- =========================

-- LINGUE
INSERT INTO Lingue (nome) VALUES
                              ('Italiano'), ('Inglese'), ('Francese'), ('Spagnolo'), ('Tedesco');

-- GENERI
INSERT INTO Generi (nome) VALUES
                              ('Narrativa'), ('Fantascienza'), ('Saggio'), ('Storico'), ('Fantasy');

-- AUTORI
INSERT INTO Autori (nome, cognome) VALUES
                                       ('George', 'Orwell'), ('Italo', 'Calvino'), ('J.R.R.', 'Tolkien'), ('Umberto', 'Eco'), ('Isaac', 'Asimov');

-- RUOLI
INSERT INTO Ruoli (priorita, nome, durata_prestito, limite_prestiti) VALUES
                                                                         (0, 'Admin', NULL, NULL),
                                                                         (1, 'Bibliotecario', 30, 10),
                                                                         (2, 'Docente', 30, 5),
                                                                         (3, 'Studente', 15, 3);

-- BADGE
INSERT INTO Badge (nome, descrizione, icona_url) VALUES
                                                     ('Lettore Accanito', 'Oltre 10 libri letti', 'reader.png'),
                                                     ('Puntuale', 'Mai in ritardo', 'time.png'),
                                                     ('Maratoneta', '5 libri in un mese', 'marathon.png');

-- RFID
INSERT INTO RFID (rfid, tipo) VALUES
                                  ('RFID-UTENTE-001', 'UTENTE'), ('RFID-UTENTE-002', 'UTENTE'), ('RFID-UTENTE-003', 'UTENTE'),
                                  ('RFID-LIBRO-001', 'LIBRO'), ('RFID-LIBRO-002', 'LIBRO'), ('RFID-LIBRO-003', 'LIBRO'),
                                  ('RFID-LIBRO-004', 'LIBRO'), ('RFID-LIBRO-005', 'LIBRO');

-- UTENTI
INSERT INTO Utenti (cf, nome, cognome, email, password, data_nascita, sesso, consenso_privacy, id_rfid) VALUES
                                                                                                            ('RSSMRA01A01H501Z', 'Mario', 'Rossi', 'mario@demo.it', 'hash1', '2004-03-12', 'M', 1, 1),
                                                                                                            ('VRDLGI02B22F205X', 'Giulia', 'Verdi', 'giulia@demo.it', 'hash2', '2003-07-25', 'F', 1, 2),
                                                                                                            ('BNCLNZ03C10L219W', 'Lorenzo', 'Bianchi', 'lorenzo@demo.it', 'hash3', '2005-11-03', 'M', 1, 3);

-- UTENTI ↔ RUOLI
INSERT INTO Utenti_Ruoli (id_utente, id_ruolo, prestiti_tot, streak_restituzioni) VALUES
                                                                                      (1, 3, 5, 2), (2, 3, 8, 4), (3, 2, 12, 6);

-- UTENTI ↔ BADGE
INSERT INTO Utenti_Badge (id_utente, id_badge) VALUES
                                                   (1, 1), (2, 1), (2, 2), (3, 3);

-- LIBRI
INSERT INTO Libri (titolo, descrizione, isbn, anno_uscita, editore, lingua_id, lingua_originale_id, numero_pagine, valore_copertina, rating) VALUES
                                                                                                                                                 ('1984', 'Distopia politica', '9780451524935', '1949-01-01', 'Secker & Warburg', 2, 2, 328, 12.90, 4.8),
                                                                                                                                                 ('Il barone rampante', 'Romanzo filosofico', '9788807900123', '1957-01-01', 'Einaudi', 1, 1, 256, 10.50, 4.6),
                                                                                                                                                 ('Il Signore degli Anelli', 'Fantasy epico', '9780261102385', '1954-01-01', 'Allen & Unwin', 2, 2, 1200, 35.00, 4.9),
                                                                                                                                                 ('Il nome della rosa', 'Giallo storico', '9788845245660', '1980-01-01', 'Bompiani', 1, 1, 512, 14.90, 4.7),
                                                                                                                                                 ('Fondazione', 'Fantascienza classica', '9788804618236', '1951-01-01', 'Gnome Press', 2, 2, 255, 11.90, 4.5);

-- LIBRI ↔ AUTORI
INSERT INTO Libri_Autori (id_autore, id_libro) VALUES
                                                   (1, 1), (2, 2), (3, 3), (4, 4), (5, 5);

-- LIBRI ↔ GENERI
INSERT INTO Libri_Generi (id_genere, id_libro) VALUES
                                                   (2, 1), (1, 2), (5, 3), (4, 4), (2, 5);

-- INVENTARIO
-- Nota: Aggiornati gli stati per coerenza con i prestiti sotto
INSERT INTO Inventari (id_libro, id_rfid, collocazione, stato) VALUES
                                                                   (1, 4, 'A1-01', 'DISPONIBILE'),      -- ID Inventario 1
                                                                   (1, 5, 'A1-02', 'IN_PRESTITO'),      -- ID Inventario 2 (Prestato a Mario)
                                                                   (2, 6, 'B2-01', 'IN_PRESTITO'),      -- ID Inventario 3 (Prestato a Giulia)
                                                                   (3, 7, 'C3-05', 'DISPONIBILE'),      -- ID Inventario 4
                                                                   (4, 8, 'D4-02', 'NON_IN_PRESTITO');  -- ID Inventario 5

-- PRESTITI
-- Nota: Usiamo ID inventario 2 e 3 che esistono
INSERT INTO Prestiti (id_inventario, id_utente, scadenza_prestito) VALUES
                                                                       (2, 1, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 DAY)), -- Mario ha il libro 1 (copia 2)
                                                                       (3, 2, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY)); -- Giulia ha il libro 2 (copia 3)

-- PRENOTAZIONI
INSERT INTO Prenotazioni (id_utente, id_libro) VALUES
                                                   (1, 3), -- Mario prenota Signore degli Anelli
                                                   (2, 4), -- Giulia prenota Nome della Rosa
                                                   (3, 1); -- Lorenzo prenota 1984

-- MULTE
INSERT INTO Multe (id_utente, giorni, importo, causa, commento) VALUES
                                                                    (1, 2, 3.00, 'RITARDO', 'Consegna in ritardo'),
                                                                    (2, NULL, 8.50, 'DANNI', 'Copertina rovinata');

-- RECENSIONI
INSERT INTO Recensioni (id_libro, id_utente, voto, descrizione) VALUES
                                                                    (1, 1, 5, 'Attualissimo'),
                                                                    (3, 2, 5, 'Epico'),
                                                                    (5, 3, 4, 'Ottima fantascienza');

-- NOTIFICHE WEB
INSERT INTO Notifiche_Web (id_utente, tipo, titolo, messaggio, letto) VALUES
                                                                          (1, 'WARNING', 'Scadenza prestito', 'Il prestito scade tra 3 giorni', 0),
                                                                          (2, 'SUCCESS', 'Libro disponibile', 'Il libro prenotato è disponibile', 0),
                                                                          (3, 'INFO', 'Badge ottenuto', 'Hai ottenuto un nuovo badge', 1);

-- LOG AUDIT
INSERT INTO Logs_Audit (id_utente, azione, dettagli) VALUES
                                                         (1, 'LOGIN_SUCCESS', 'Login effettuato'),
                                                         (2, 'LOGIN_FALLITO', 'Password errata'),
                                                         (3, 'CREAZIONE_UTENTE', 'Nuovo utente registrato');
