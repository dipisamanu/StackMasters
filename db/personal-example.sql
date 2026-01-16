/*
==============================================================================
CREAZIONE RAPIDA NUOVO UTENTE
==============================================================================

PASSO 0: PER GENERARE LA PASSWORD

    php -r "echo password_hash('tua_password_segreta', PASSWORD_BCRYPT);"

Copia la stringa che esce (inizia solitamente con $2y$...) e incollala nella
query sotto al posto di 'INSERISCI_QUI_HASH_PASSWORD'.
==============================================================================
*/

-- ---------------------------------------------------------------------------
-- SEZIONE 1: TEMPLATE DI INSERIMENTO (COPIA, INCOLLA E COMPILA QUESTO BLOCCO)
-- ---------------------------------------------------------------------------

-- 1.1 Inserimento Dati Anagrafici Utente
INSERT INTO utenti (
    cf, nome, cognome, email, password,
    sesso, data_nascita, comune_nascita,
    email_verificata, consenso_privacy
)
VALUES (
           'CODICE_FISCALE',          -- Es: 'RSSMRA80A01H501Z'
           'NOME',                    -- Es: 'Mario'
           'COGNOME',                 -- Es: 'Rossi'
           'EMAIL@BIBLIOTECA.IT',     -- Email univoca
           'INSERISCI_QUI_HASH_PASSWORD', -- Incolla qui l'hash generato col comando PHP
           'M',                       -- M o F
           'YYYY-MM-DD',              -- Es: '1990-12-25'
           'COMUNE_NASCITA',          -- Es: 'Milano'
           1,                         -- Email Verificata (1=Sì, 0=No)
           1                          -- Consenso Privacy (1=Sì, 0=No)
       );

-- 1.2 Assegnazione Ruolo (ATTENZIONE: Eseguire subito dopo l'insert sopra)
-- Nota: LAST_INSERT_ID() prende l'ID dell'utente appena creato.
-- Modifica 'Admin' con 'User' o altri ruoli se necessario.
INSERT INTO utenti_ruoli (id_utente, id_ruolo)
VALUES (
           LAST_INSERT_ID(),
           (SELECT id_ruolo FROM ruoli WHERE nome = 'Admin' LIMIT 1)
       );


-- ---------------------------------------------------------------------------
-- SEZIONE 2: ESEMPIO PRECOMPILATO (PASSWORD DA IMPOSTARE)
-- ---------------------------------------------------------------------------

-- Esempio di creazione utente Admin completo
INSERT INTO utenti (
    cf, nome, cognome, email, password,
    sesso, data_nascita, comune_nascita,
    email_verificata, consenso_privacy
)
VALUES (
           'SPGLBR80A01H501Z',
           'Alberto',
           'Spiaggia',
           'alberto@biblioteca.it',
           '$2y$10$abcdefghilmno...', -- Esempio di hash (USA IL COMANDO PHP DA TERMINALE)
           'M',
           '1980-01-01',
           'Roma',
           1,
           1
       );

-- Assegna ruolo Admin
INSERT INTO utenti_ruoli (id_utente, id_ruolo)
VALUES (
           LAST_INSERT_ID(),
           (SELECT id_ruolo FROM ruoli WHERE nome = 'Admin' LIMIT 1)
       );


INSERT INTO utenti (
    cf, nome, cognome, email, password,
    sesso, data_nascita, comune_nascita,
    email_verificata, consenso_privacy
)
VALUES (
           'SPGLBR80A01H501Z',
           'Alberto',
           'Spiaggia',
           'alberto@biblioteca.it',
           '$2y$10$df0EJ9fSVcJ8rK8RdLIlhOL1lhEn0SFmbCGvbwKD.I1PTWdnInbWO',
           'M',
           '1980-01-01',
           'Roma',
           1,
           1
       );

INSERT INTO utenti_ruoli (id_utente, id_ruolo)
VALUES (
           LAST_INSERT_ID(),
           (SELECT id_ruolo FROM ruoli WHERE nome = 'Admin' LIMIT 1)
    );