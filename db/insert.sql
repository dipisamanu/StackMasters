-- 1. INSERIMENTO DIPENDENZE (Lingua, Autore, Genere, Ruolo)
-- Inseriamo solo se non esistono già, per evitare duplicati in questo esempio usiamo valori semplici.

INSERT INTO Lingue (nome) VALUES ('Italiano');

INSERT INTO Autori (nome, cognome) VALUES ('George', 'Orwell');

INSERT INTO Generi (nome) VALUES ('Fantascienza Distopica');

INSERT INTO Ruoli (priorita, nome, durata_prestito, limite_prestiti)
VALUES (3, 'Studente', 30, 5);


-- 2. INSERIMENTO DEL LIBRO
-- Recuperiamo l'ID della lingua usando una SELECT sul nome appena inserito
INSERT INTO Libri (titolo, isbn, anno_uscita, editore, lingua_id, lingua_originale_id, numero_pagine, valore_copertina, descrizione)
VALUES (
           '1984',
           '9788804668237',
           '1949-06-08',
           'Mondadori',
           (SELECT id FROM Lingue WHERE nome = 'Italiano' LIMIT 1),
       (SELECT id FROM Lingue WHERE nome = 'Italiano' LIMIT 1),
    336,
    14.00,
    'Il Grande Fratello ti guarda.'
    );


-- 3. COLLEGARE LIBRO AD AUTORE E GENERE
-- Usiamo le SELECT per trovare gli ID basandoci su ISBN (che è unico) e Nomi
INSERT INTO Libri_Autori (id_autore, id_libro)
VALUES (
           (SELECT id FROM Autori WHERE cognome = 'Orwell' LIMIT 1),
       (SELECT id_libro FROM Libri WHERE isbn = '9788804668237' LIMIT 1)
    );

INSERT INTO Libri_Generi (id_genere, id_libro)
VALUES (
           (SELECT id FROM Generi WHERE nome = 'Fantascienza Distopica' LIMIT 1),
       (SELECT id_libro FROM Libri WHERE isbn = '9788804668237' LIMIT 1)
    );


-- 4. CREAZIONE COPIA FISICA (INVENTARIO)
INSERT INTO Inventari (id_libro, stato, condizione, collocazione)
VALUES (
           (SELECT id_libro FROM Libri WHERE isbn = '9788804668237' LIMIT 1),
    'DISPONIBILE',
    'BUONO',
    'SCAFFALE-B-04'
    );


-- 5. INSERIMENTO UTENTE
INSERT INTO Utenti (cf, nome, cognome, email, password, data_nascita, sesso, comune_nascita, consenso_privacy)
VALUES (
           'VRDLGI90A01H501K',
           'Luigi',
           'Verdi',
           'luigi.verdi@email.it',
           'password_super_segreta',
           '1990-01-01',
           'M',
           'Milano',
           1
       );


-- 6. ASSEGNAZIONE RUOLO UTENTE
INSERT INTO Utenti_Ruoli (id_utente, id_ruolo)
VALUES (
           (SELECT id_utente FROM Utenti WHERE cf = 'VRDLGI90A01H501K' LIMIT 1),
       (SELECT id_ruolo FROM Ruoli WHERE nome = 'Studente' LIMIT 1)
    );