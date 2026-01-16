DROP DATABASE IF EXISTS biblioteca_db;
CREATE DATABASE biblioteca_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblioteca_db;


-- ===========================
-- CREAZIONE TABELLE
-- ===========================


CREATE TABLE autori
(
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nome                 VARCHAR(100) NOT NULL,
    cognome              VARCHAR(100) NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT INDEX idx_autore (nome, cognome)
);

CREATE TABLE lingue
(
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nome                 VARCHAR(60) NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE generi
(
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nome                 VARCHAR(60) NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE ruoli
(
    id_ruolo             INT AUTO_INCREMENT PRIMARY KEY,
    priorita             TINYINT UNSIGNED NOT NULL UNIQUE COMMENT '0=Admin, 1=Bibliotecario, 2=Docente, 3=Studente',
    nome                 VARCHAR(15)      NOT NULL,
    durata_prestito      TINYINT          NULL COMMENT 'Giorni',
    limite_prestiti      TINYINT          NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE badge
(
    id_badge    INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(50) UNIQUE NOT NULL,
    descrizione TEXT,
    icona_url   TEXT
);

CREATE TABLE rfid
(
    id_rfid              INT AUTO_INCREMENT PRIMARY KEY,
    rfid                 VARCHAR(128) UNIQUE      NOT NULL,
    tipo                 ENUM ('UTENTE', 'LIBRO') NOT NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE libri
(
    id_libro             INT AUTO_INCREMENT PRIMARY KEY,
    titolo               VARCHAR(255) NOT NULL,
    descrizione          TEXT,
    isbn                 VARCHAR(17) UNIQUE,
    anno_uscita          DATETIME,
    editore              VARCHAR(100),
    lingua_id            INT,
    lingua_originale_id  INT,
    numero_pagine        INT,
    immagine_copertina   VARCHAR(500) DEFAULT NULL,
    valore_copertina     DECIMAL(7, 2),
    rating               FLOAT        DEFAULT 0,
    copertina_url        TEXT,
    cancellato           TINYINT      DEFAULT 0,
    data_creazione       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    ultimo_aggiornamento TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lingua_id) REFERENCES lingue (id),
    FOREIGN KEY (lingua_originale_id) REFERENCES lingue (id),
    FULLTEXT INDEX idx_titolo (titolo),
    FULLTEXT INDEX idx_editore (editore)
);

CREATE TABLE utenti
(
    id_utente               INT AUTO_INCREMENT PRIMARY KEY,
    cf                      VARCHAR(20) UNIQUE NOT NULL,
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
    FOREIGN KEY (id_rfid) REFERENCES rfid (id_rfid) ON DELETE SET NULL,
    FULLTEXT INDEX idx_utente (cf, nome, cognome, email)
);

CREATE TABLE libri_autori
(
    id_autore            INT,
    id_libro             INT,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_autore, id_libro),
    FOREIGN KEY (id_autore) REFERENCES autori (id) ON DELETE CASCADE,
    FOREIGN KEY (id_libro) REFERENCES libri (id_libro) ON DELETE CASCADE
);

CREATE TABLE libri_generi
(
    id_genere            INT,
    id_libro             INT,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_genere, id_libro),
    FOREIGN KEY (id_genere) REFERENCES generi (id) ON DELETE CASCADE,
    FOREIGN KEY (id_libro) REFERENCES libri (id_libro) ON DELETE CASCADE
);

CREATE TABLE utenti_ruoli
(
    id_utente           INT,
    id_ruolo            INT,
    prestiti_tot        INT          DEFAULT 0,
    streak_restituzioni INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id_utente, id_ruolo),
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente) ON DELETE CASCADE,
    FOREIGN KEY (id_ruolo) REFERENCES ruoli (id_ruolo)
);

CREATE TABLE utenti_badge
(
    id_utente          INT,
    id_badge           INT,
    data_conseguimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_utente, id_badge),
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente) ON DELETE CASCADE,
    FOREIGN KEY (id_badge) REFERENCES badge (id_badge) ON DELETE CASCADE
);

CREATE TABLE inventari
(
    id_inventario        INT AUTO_INCREMENT PRIMARY KEY,
    id_libro             INT NOT NULL,
    id_rfid              INT UNIQUE,
    stato                ENUM ('DISPONIBILE', 'IN_PRESTITO', 'NON_IN_PRESTITO', 'SMARRITO', 'FUORI_CATALOGO') DEFAULT 'DISPONIBILE',
    condizione           ENUM ('BUONO', 'DANNEGGIATO', 'SMARRITO')                                            DEFAULT 'BUONO',
    condizione_originale ENUM ('BUONO', 'DANNEGGIATO', 'SMARRITO')                                            DEFAULT 'BUONO',
    collocazione         VARCHAR(20),
    ultimo_aggiornamento TIMESTAMP                                                                            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_libro) REFERENCES libri (id_libro),
    FOREIGN KEY (id_rfid) REFERENCES rfid (id_rfid)
);

CREATE TABLE prestiti
(
    id_prestito          INT AUTO_INCREMENT PRIMARY KEY,
    id_inventario        INT       NOT NULL,
    id_utente            INT       NOT NULL,
    data_prestito        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scadenza_prestito    TIMESTAMP NULL,
    data_restituzione    TIMESTAMP NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_inventario) REFERENCES inventari (id_inventario),
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente)
);

CREATE TABLE prenotazioni
(
    id_prenotazione      INT AUTO_INCREMENT PRIMARY KEY,
    id_utente            INT       NOT NULL,
    id_libro             INT       NOT NULL, -- Prenoto il Titolo
    copia_libro          INT       NULL,     -- FK inventari: popolato solo quando la copia viene assegnata
    data_richiesta       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_disponibilita   TIMESTAMP NULL,
    scadenza_ritiro      TIMESTAMP NULL,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente),
    FOREIGN KEY (id_libro) REFERENCES libri (id_libro),
    FOREIGN KEY (copia_libro) REFERENCES inventari (id_inventario)
);

CREATE TABLE multe
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
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente)
);

CREATE TABLE recensioni
(
    id_recensione  INT AUTO_INCREMENT PRIMARY KEY,
    id_libro       INT NOT NULL,
    id_utente      INT NOT NULL,
    voto           INT CHECK (voto BETWEEN 1 AND 5),
    descrizione    TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_update    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_libro) REFERENCES libri (id_libro),
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente),
    CONSTRAINT unique_user_book UNIQUE (id_utente, id_libro)
);

CREATE TABLE logs_audit
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    id_utente  INT                                                                                                                       NULL,
    azione     ENUM ('LOGIN_FALLITO', 'LOGIN_SUCCESS', 'CREAZIONE_UTENTE', 'MODIFICA_PRESTITO', 'CANCELLAZIONE_LIBRO', 'VERIFICA_EMAIL') NOT NULL,
    dettagli   TEXT,
    ip_address INT UNSIGNED COMMENT 'IPv4 convertito con INET_ATON',
    ipv6       VARBINARY(16) COMMENT 'IPv6 convertito con INET6_ATON',
    timestamp  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente) ON DELETE SET NULL
);

CREATE TABLE notifiche_web
(
    id_notifica      INT AUTO_INCREMENT PRIMARY KEY,
    id_utente        INT                                           NOT NULL,
    tipo             ENUM ('INFO', 'WARNING', 'DANGER', 'SUCCESS') NOT NULL,
    titolo           VARCHAR(100)                                  NOT NULL,
    messaggio        TEXT                                          NOT NULL,
    link_azione      VARCHAR(255), -- Rimane per creare link diretti nella pagina Archivio e nell'email
    letto            BOOLEAN                                                    DEFAULT FALSE,
    stato_email      ENUM ('NON_RICHIESTA', 'DA_INVIARE', 'INVIATA', 'FALLITA') DEFAULT 'DA_INVIARE',
    data_creazione   TIMESTAMP                                                  DEFAULT CURRENT_TIMESTAMP,
    data_invio_email TIMESTAMP                                     NULL,
    FOREIGN KEY (id_utente) REFERENCES utenti (id_utente) ON DELETE CASCADE
);

-- TABELLE DI CACHE (ANALYTICS)
CREATE TABLE IF NOT EXISTS cache_stats_libri
(
    id_libro           INT PRIMARY KEY,
    prestiti_totali    INT            DEFAULT 0,
    prestiti_30_giorni INT            DEFAULT 0,
    prestiti_7_giorni  INT            DEFAULT 0,
    rating_medio       FLOAT          DEFAULT 0,
    score_trending     DECIMAL(10, 4) DEFAULT 0,
    data_aggiornamento TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_libro) REFERENCES libri (id_libro) ON DELETE CASCADE,
    INDEX idx_trending (score_trending DESC),
    INDEX idx_top_mese (prestiti_30_giorni DESC),
    INDEX idx_top_all (prestiti_totali DESC)
);

CREATE TABLE IF NOT EXISTS cache_correlazioni
(
    id_libro_principale INT,
    id_libro_correlato  INT,
    peso                INT       DEFAULT 1,
    data_aggiornamento  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_libro_principale, id_libro_correlato),
    FOREIGN KEY (id_libro_principale) REFERENCES libri (id_libro) ON DELETE CASCADE,
    FOREIGN KEY (id_libro_correlato) REFERENCES libri (id_libro) ON DELETE CASCADE,
    INDEX idx_peso (peso DESC)
);


-- ===========================
-- STORED PROCEDURES
-- ===========================


DELIMITER //

DROP PROCEDURE IF EXISTS CercaLibri //
CREATE PROCEDURE CercaLibri(
    IN p_query VARCHAR(255),
    IN p_original VARCHAR(255),
    IN p_solo_disponibili BOOLEAN,
    IN p_anno_min INT,
    IN p_anno_max INT,
    IN p_rating_min FLOAT,
    IN p_condizione VARCHAR(20),
    IN p_genere_id INT,
    IN p_sort_by VARCHAR(20),
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    DECLARE v_search_query VARCHAR(255) DEFAULT '';
    IF p_query IS NOT NULL AND p_query != '' THEN
        SET v_search_query = p_query;
    END IF;

    SELECT final_results.*, COUNT(*) OVER () as totale
    FROM (SELECT l.id_libro,
                 l.titolo,
                 l.immagine_copertina,
                 l.isbn,
                 l.anno_uscita,
                 l.editore,
                 l.ultimo_aggiornamento,
                 l.rating,
                 GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi,
                 (SELECT COUNT(*)
                  FROM inventari
                  WHERE id_libro = l.id_libro
                    AND stato != 'SMARRITO')                                          AS copie_totali,
                 (SELECT COUNT(*)
                  FROM inventari
                  WHERE id_libro = l.id_libro
                    AND stato = 'DISPONIBILE')                                        AS copie_disponibili,
                 (SELECT COUNT(*)
                  FROM prestiti p
                           JOIN inventari i ON p.id_inventario = i.id_inventario
                  WHERE i.id_libro = l.id_libro)                                      AS popolarita,
                 (SELECT COUNT(*)
                  FROM inventari
                  WHERE id_libro = l.id_libro
                    AND condizione = p_condizione)                                    AS has_condition,
                 (SELECT COUNT(*)
                  FROM libri_generi
                  WHERE id_libro = l.id_libro
                    AND id_genere = p_genere_id)                                      AS has_genre,
                 IF(v_search_query != '', (MATCH(l.titolo) AGAINST(v_search_query IN BOOLEAN MODE) * 2),
                    0)                                                                AS rilevanza,
                 l.rating                                                             AS rating_medio
          FROM libri l
                   LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                   LEFT JOIN autori a ON la.id_autore = a.id
          WHERE l.cancellato = 0
            AND (p_original = ''
              OR (v_search_query != '' AND MATCH(l.titolo) AGAINST(v_search_query IN BOOLEAN MODE))
              OR (v_search_query != '' AND MATCH(a.nome, a.cognome) AGAINST(v_search_query IN BOOLEAN MODE))
              OR (v_search_query != '' AND MATCH(l.editore) AGAINST(v_search_query IN BOOLEAN MODE))
              OR (v_search_query != '' AND l.editore LIKE CONCAT('%', p_original, '%'))
              OR l.isbn = p_original)
          GROUP BY l.id_libro, l.ultimo_aggiornamento) AS final_results
    WHERE (p_solo_disponibili = FALSE OR copie_disponibili > 0)
      AND (p_anno_min IS NULL OR YEAR(anno_uscita) >= p_anno_min)
      AND (p_anno_max IS NULL OR YEAR(anno_uscita) <= p_anno_max)
      AND (p_rating_min IS NULL OR rating >= p_rating_min)
      AND (p_condizione IS NULL OR p_condizione = '' OR has_condition > 0)
      AND (p_genere_id IS NULL OR p_genere_id = '' OR has_genre > 0)
    ORDER BY CASE
                 WHEN p_sort_by = 'alpha' THEN titolo
                 WHEN p_sort_by = 'date_asc' THEN anno_uscita
                 END,
             CASE
                 WHEN p_sort_by = 'date_desc' THEN anno_uscita
                 WHEN p_sort_by = 'rating' THEN rating
                 WHEN p_sort_by = 'popularity' THEN popolarita
                 WHEN p_sort_by = 'relevance' AND p_original != '' THEN rilevanza
                 END DESC,
             ultimo_aggiornamento DESC
    LIMIT p_limit OFFSET p_offset;
END //


DROP PROCEDURE IF EXISTS Job_AggiornaStatsLibri //
CREATE PROCEDURE Job_AggiornaStatsLibri()
BEGIN
    INSERT INTO cache_stats_libri (id_libro, prestiti_totali, prestiti_30_giorni, prestiti_7_giorni, rating_medio,
                                   score_trending)
    SELECT l.id_libro,
           COALESCE(stats.tot, 0)                                                                    AS prestiti_totali,
           COALESCE(stats.mese, 0)                                                                   AS prestiti_30_giorni,
           COALESCE(stats.settimana, 0)                                                              AS prestiti_7_giorni,
           l.rating,
           (COALESCE(stats.settimana, 0) * 5.0) + (COALESCE(stats.mese, 0) * 2.0) + (l.rating * 0.5) AS score_trending
    FROM libri l
             LEFT JOIN (SELECT i.id_libro,
                               COUNT(*)            AS tot,
                               SUM(CASE
                                       WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1
                                       ELSE 0 END) AS mese,
                               SUM(CASE
                                       WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1
                                       ELSE 0 END) AS settimana
                        FROM prestiti p
                                 JOIN inventari i ON p.id_inventario = i.id_inventario
                        GROUP BY i.id_libro) stats ON l.id_libro = stats.id_libro
    WHERE l.cancellato = 0
    ON DUPLICATE KEY UPDATE prestiti_totali    = VALUES(prestiti_totali),
                            prestiti_30_giorni = VALUES(prestiti_30_giorni),
                            prestiti_7_giorni  = VALUES(prestiti_7_giorni),
                            rating_medio       = VALUES(rating_medio),
                            score_trending     = VALUES(score_trending),
                            data_aggiornamento = NOW();

    DELETE FROM cache_stats_libri WHERE id_libro NOT IN (SELECT id_libro FROM libri);
END //


DROP PROCEDURE IF EXISTS Job_AggiornaCorrelazioni //
CREATE PROCEDURE Job_AggiornaCorrelazioni()
BEGIN
    INSERT INTO cache_correlazioni (id_libro_principale, id_libro_correlato, peso)
    SELECT t1.id_libro                  AS libro_A,
           t2.id_libro                  AS libro_B,
           COUNT(DISTINCT p1.id_utente) AS peso_calcolato
    FROM prestiti p1
             JOIN inventari i1 ON p1.id_inventario = i1.id_inventario
             JOIN prestiti p2 ON p1.id_utente = p2.id_utente
             JOIN inventari i2 ON p2.id_inventario = i2.id_inventario
             JOIN (SELECT id_inventario, id_libro FROM inventari) t1 ON p1.id_inventario = t1.id_inventario
             JOIN (SELECT id_inventario, id_libro FROM inventari) t2 ON p2.id_inventario = t2.id_inventario
    WHERE t1.id_libro != t2.id_libro
    GROUP BY t1.id_libro, t2.id_libro
    HAVING peso_calcolato > 0
    ON DUPLICATE KEY UPDATE peso               = VALUES(peso),
                            data_aggiornamento = NOW();
END //

DROP PROCEDURE IF EXISTS GetHomepageData //
CREATE PROCEDURE GetHomepageData(
    IN p_sezione VARCHAR(20),
    IN p_limit INT
)
BEGIN
    IF p_sezione = 'NOVITA' THEN
        SELECT l.id_libro,
               l.titolo,
               l.immagine_copertina,
               l.anno_uscita,
               GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
        FROM libri l
                 LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                 LEFT JOIN autori a ON la.id_autore = a.id
        WHERE l.cancellato = 0
        -- FIX: Aggiunte tutte le colonne SELECT e ORDER BY al GROUP BY
        GROUP BY l.id_libro, l.titolo, l.immagine_copertina, l.anno_uscita, l.data_creazione
        ORDER BY l.data_creazione DESC
        LIMIT p_limit;

    ELSEIF p_sezione = 'TRENDING' THEN
        SELECT l.id_libro,
               l.titolo,
               l.immagine_copertina,
               c.score_trending,
               GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
        FROM cache_stats_libri c
                 JOIN libri l ON c.id_libro = l.id_libro
                 LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                 LEFT JOIN autori a ON la.id_autore = a.id
        WHERE l.cancellato = 0
          AND c.score_trending > 0
        GROUP BY l.id_libro, l.titolo, l.immagine_copertina, c.score_trending
        ORDER BY c.score_trending DESC
        LIMIT p_limit;

    ELSEIF p_sezione = 'TOP_MESE' THEN
        SELECT l.id_libro,
               l.titolo,
               l.immagine_copertina,
               c.prestiti_30_giorni                                                 as prestiti,
               GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
        FROM cache_stats_libri c
                 JOIN libri l ON c.id_libro = l.id_libro
                 LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                 LEFT JOIN autori a ON la.id_autore = a.id
        WHERE l.cancellato = 0
          AND c.prestiti_30_giorni > 0
        GROUP BY l.id_libro, l.titolo, l.immagine_copertina, c.prestiti_30_giorni
        ORDER BY c.prestiti_30_giorni DESC
        LIMIT p_limit;
    END IF;
END //


DROP PROCEDURE IF EXISTS GetRaccomandazioniPersonali //
CREATE PROCEDURE GetRaccomandazioniPersonali(
    IN p_id_utente INT,
    IN p_limit INT
)
BEGIN
    SELECT DISTINCT l.id_libro,
                    l.titolo,
                    l.immagine_copertina,
                    c.score_trending,
                    g.nome as genere_suggerito
    FROM libri l
             JOIN cache_stats_libri c ON l.id_libro = c.id_libro
             JOIN libri_generi lg ON l.id_libro = lg.id_libro
             JOIN generi g ON lg.id_genere = g.id
    WHERE lg.id_genere IN (SELECT id_genere
                           FROM (SELECT lg2.id_genere, COUNT(*) as cnt
                                 FROM prestiti p
                                          JOIN inventari i ON p.id_inventario = i.id_inventario
                                          JOIN libri_generi lg2 ON i.id_libro = lg2.id_libro
                                 WHERE p.id_utente = p_id_utente
                                 GROUP BY lg2.id_genere
                                 ORDER BY cnt DESC
                                 LIMIT 3) as generi_preferiti)
      AND l.id_libro NOT IN (SELECT DISTINCT i.id_libro
                             FROM prestiti p
                                      JOIN inventari i ON p.id_inventario = i.id_inventario
                             WHERE p.id_utente = p_id_utente)
    ORDER BY c.score_trending DESC
    LIMIT p_limit;
END //


DROP PROCEDURE IF EXISTS GetLibriSimili //
CREATE PROCEDURE GetLibriSimili(IN p_id_libro INT, IN p_limit INT)
BEGIN
    (SELECT l.id_libro, l.titolo, l.immagine_copertina, 'UTENTI' as tipo_correlazione, cc.peso as score
     FROM cache_correlazioni cc
              JOIN libri l ON cc.id_libro_correlato = l.id_libro
     WHERE cc.id_libro_principale = p_id_libro
     ORDER BY cc.peso DESC
     LIMIT p_limit)
    UNION
    DISTINCT
    (SELECT l.id_libro, l.titolo, l.immagine_copertina, 'AUTORE' as tipo_correlazione, 1 as score
     FROM libri l
              JOIN libri_autori la ON l.id_libro = la.id_libro
     WHERE la.id_autore IN (SELECT id_autore FROM libri_autori WHERE id_libro = p_id_libro)
       AND l.id_libro != p_id_libro
     LIMIT p_limit)
    ORDER BY score DESC
    LIMIT p_limit;
END //

DELIMITER ;


-- ===========================
-- DATI DI ESEMPIO (SEED DATA)
-- ===========================

-- LINGUE
INSERT INTO lingue (nome) VALUES
('Italiano'),
('Inglese'),
('Francese'),
('Spagnolo'),
('Tedesco');

-- GENERI
INSERT INTO generi (nome) VALUES
('Narrativa'),
('Fantascienza'),
('Saggio'),
('Storico'),
('Fantasy'),
('Giallo'),
('Poesia'),
('Autobiografia');

-- AUTORI
INSERT INTO autori (nome, cognome) VALUES
('George', 'Orwell'),
('Italo', 'Calvino'),
('J.R.R.', 'Tolkien'),
('Umberto', 'Eco'),
('Isaac', 'Asimov'),
('Donna', 'Tartt'),
('Stephen', 'King');

-- RUOLI
INSERT INTO ruoli (priorita, nome, durata_prestito, limite_prestiti) VALUES
(0, 'Admin', 45, 10),
(1, 'Bibliotecario', 45, 10),
(2, 'Docente', 30, 5),
(3, 'Studente', 15, 3);

-- BADGE
INSERT INTO badge (nome, descrizione, icona_url) VALUES
('Lettore Accanito', 'Oltre 10 libri letti', 'reader.png'),
('Puntuale', 'Mai in ritardo', 'time.png'),
('Maratoneta', '5 libri in un mese', 'marathon.png'),
('Collezionista', '50 libri letti', 'collection.png');

-- RFID
INSERT INTO rfid (rfid, tipo) VALUES
('RFID-UTENTE-001', 'UTENTE'),
('RFID-UTENTE-002', 'UTENTE'),
('RFID-UTENTE-003', 'UTENTE'),
('RFID-LIBRO-001', 'LIBRO'),
('RFID-LIBRO-002', 'LIBRO'),
('RFID-LIBRO-003', 'LIBRO'),
('RFID-LIBRO-004', 'LIBRO'),
('RFID-LIBRO-005', 'LIBRO');

-- UTENTI - PASSWORD: "password"
INSERT INTO utenti (cf, nome, cognome, email, password, data_nascita, sesso, comune_nascita, email_verificata, consenso_privacy, id_rfid) VALUES
('RSSMRA91T04H501A', 'Mario', 'Rossi', 'mario@demo.it', '$2y$12$b5XJ2w.ymez20Zp4SLYTS.WgrODnAUuASMqHo4fyXZcnVb2XjUsEq%', '2004-03-12', 'M', 'Milano', 1, 1, 1),
('VRDLGI93L25F205B', 'Giulia', 'Verdi', 'giulia@demo.it', '$2y$12$b5XJ2w.ymez20Zp4SLYTS.WgrODnAUuASMqHo4fyXZcnVb2XjUsEq%', '2003-07-25', 'F', 'Roma', 1, 1, 2),
('BNCLNZ95K03L219C', 'Lorenzo', 'Bianchi', 'lorenzo@demo.it', '$2y$12$b5XJ2w.ymez20Zp4SLYTS.WgrODnAUuASMqHo4fyXZcnVb2XjUsEq%', '2005-11-03', 'M', 'Torino', 1, 1, 3);

-- UTENTI ↔ RUOLI
INSERT INTO utenti_ruoli (id_utente, id_ruolo, prestiti_tot, streak_restituzioni) VALUES
(1, 3, 5, 2),
(2, 3, 8, 4),
(3, 1, 12, 6);

-- UTENTI ↔ BADGE
INSERT INTO utenti_badge (id_utente, id_badge) VALUES
(1, 1),
(2, 1),
(2, 2),
(3, 3);

-- LIBRI
INSERT INTO libri (titolo, descrizione, isbn, anno_uscita, editore, lingua_id, lingua_originale_id, numero_pagine, valore_copertina, rating) VALUES
('1984', 'Distopia politica affascinante', '9780451524935', '1949-06-08', 'Secker & Warburg', 2, 2, 328, 12.90, 4.8),
('Il barone rampante', 'Romanzo filosofico di grande profondità', '9788807900123', '1957-01-01', 'Einaudi', 1, 1, 256, 10.50, 4.6),
('Il Signore degli Anelli', 'Fantasy epico masterpiece', '9780261102385', '1954-01-01', 'Allen & Unwin', 2, 2, 1200, 35.00, 4.9),
('Il nome della rosa', 'Giallo storico complesso', '9788845245660', '1980-01-01', 'Bompiani', 1, 1, 512, 14.90, 4.7),
('Fondazione', 'Fantascienza classica affascinante', '9788804618236', '1951-06-01', 'Gnome Press', 2, 2, 255, 11.90, 4.5),
('La piccola principessa', 'Narrativa classica per ragazzi', '9788804618243', '1905-01-01', 'Scribner', 2, 2, 400, 9.99, 4.4),
('Orgoglio e pregiudizio', 'Romanzo classico senza tempo', '9780141187761', '1813-01-28', 'Murray', 2, 2, 432, 8.99, 4.8);

-- LIBRI ↔ AUTORI
INSERT INTO libri_autori (id_autore, id_libro) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5);

-- LIBRI ↔ GENERI
INSERT INTO libri_generi (id_genere, id_libro) VALUES
(2, 1),
(1, 2),
(5, 3),
(6, 4),
(2, 5),
(1, 6),
(1, 7);

-- INVENTARI
INSERT INTO inventari (id_libro, id_rfid, collocazione, stato) VALUES
(1, 4, 'A1-01', 'DISPONIBILE'),
(1, 5, 'A1-02', 'IN_PRESTITO'),
(2, 6, 'B2-01', 'IN_PRESTITO'),
(3, 7, 'C3-05', 'DISPONIBILE'),
(4, 8, 'D4-02', 'DISPONIBILE'),
(5, NULL, 'E5-03', 'DISPONIBILE'),
(6, NULL, 'F6-01', 'DISPONIBILE');

-- PRESTITI
INSERT INTO prestiti (id_inventario, id_utente, data_prestito, scadenza_prestito) VALUES
(2, 1, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY)),
(3, 2, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY));

-- PRENOTAZIONI
INSERT INTO prenotazioni (id_utente, id_libro, data_richiesta) VALUES
(1, 3, NOW()),
(2, 4, NOW()),
(3, 1, NOW());

-- RECENSIONI
INSERT INTO recensioni (id_libro, id_utente, voto, descrizione) VALUES
(1, 1, 5, 'Capolavoro di distopia, ancora attuale'),
(3, 2, 5, 'Epico e indimenticabile'),
(5, 3, 4, 'Ottima fantascienza classica');

-- LOG AUDIT
INSERT INTO logs_audit (id_utente, azione, dettagli) VALUES
(1, 'LOGIN_SUCCESS', 'Login effettuato'),
(2, 'CREAZIONE_UTENTE', 'Nuovo utente registrato'),
(3, 'LOGIN_SUCCESS', 'Login effettuato');

-- ==========================================
-- 5. EVENTI SCHEDULATI
-- ==========================================
SET GLOBAL event_scheduler = ON;

DELIMITER //

CREATE EVENT IF NOT EXISTS evt_refresh_stats
    ON SCHEDULE EVERY 1 HOUR
    DO CALL Job_AggiornaStatsLibri();

CREATE EVENT IF NOT EXISTS evt_refresh_correlations
    ON SCHEDULE EVERY 1 DAY
        STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY + INTERVAL 3 HOUR)
    DO CALL Job_AggiornaCorrelazioni();

DELIMITER ;