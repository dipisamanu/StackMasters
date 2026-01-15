INSERT INTO utenti (cf, nome, cognome, email, password, sesso, data_nascita, comune_nascita, email_verificata,
                    consenso_privacy)
VALUES ('codice-fiscale',
        'nome',
        'cognome',
        'mail@biblioteca.it',
           -- da terminale fare php -r "echo password_hash('password', PASSWORD_BCRYPT);" per avere la password corretta da inserire
        'hash-password',
        'M',
        'YYYY-MM-DD',
        'comune-nascita',
        1,
        1);

-- 2. Assegna il ruolo ADMIN (recuperando l'ID appena creato) OCCHIO!!!!!!!!!!!
INSERT INTO utenti_ruoli (id_utente, id_ruolo)
VALUES (LAST_INSERT_ID(), (SELECT id_ruolo FROM ruoli WHERE nome = 'Admin' LIMIT 1));
-- Cambia ruolo in base alle necessità

-- COPIARE E MODIFICARE PER CREARE UN ALTRO UTENTE CON PERMESSI DIVERSI

-- UTENTE DI ESEMPIO

INSERT INTO utenti (cf, nome, cognome, email, password, sesso, data_nascita, comune_nascita, email_verificata,
                    consenso_privacy)
VALUES ('SPGLBR80A01H501Z',
        'Alberto',
        'Spiaggia',
        'alberto@biblioteca.it',
        -- da terminale fare php -r "echo password_hash('password', PASSWORD_BCRYPT);" per creare la password da inserire
        'INCOLLA_QUI_IL_TUO_HASH', --
        'M',
        '1980-01-01',
        'Roma',
        1,
        1);

-- Assegna ruolo Admin
INSERT INTO utenti_ruoli (id_utente, id_ruolo)
VALUES (LAST_INSERT_ID(),
        (SELECT id_ruolo FROM ruoli WHERE nome = 'Admin' LIMIT 1));