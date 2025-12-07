-- 0. INIZIALIZZAZIONE
DROP DATABASE IF EXISTS biblioteca_db;
CREATE DATABASE biblioteca_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblioteca_db;

-- 1. TABELLE DI LOOKUP E CONFIGURAZIONE

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
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABELLE PRINCIPALI (LIBRI E UTENTI)

CREATE TABLE Libri
(
    id_libro             INT AUTO_INCREMENT PRIMARY KEY,
    titolo               VARCHAR(100) NOT NULL,
    descrizione          TEXT,
    isbn                 VARCHAR(17) UNIQUE, -- Supporta trattini e ISBN-10/13
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

-- 3. RELAZIONI MOLTI A MOLTI

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

-- 4. INVENTARIO E OPERAZIONI

CREATE TABLE Inventari -- Opzionale: Inventario è collettivo, ma Inventari è il plurale tecnico
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