# StackMasters - Library Management System

StackMasters è una piattaforma web completa per la gestione di biblioteche scolastiche e pubbliche. Progettata per ottimizzare i flussi di lavoro di bibliotecari e amministratori, offre agli studenti un'interfaccia moderna per la consultazione del catalogo e la gestione dei propri prestiti.

Il sistema integra funzionalità avanzate come la scansione di codici a barre, la generazione automatica di documenti PDF, il calcolo delle sanzioni e l'integrazione con API esterne per l'arricchimento del catalogo.

---

## Funzionalità Principali

### Gestione Catalogo & Inventario
*   **Ricerca Avanzata**: Filtri per titolo, autore, ISBN, genere e anno.
*   **Arricchimento Automatico**: Recupero automatico di copertine e metadati da Google Books e OpenLibrary tramite ISBN.
*   **Gestione Copie**: Tracciamento di ogni singola copia fisica con stato (Disponibile, In Prestito, Prenotato, Danneggiato, Smarrito).
*   **Etichette**: Generazione PDF di etichette con Barcode/QR Code per i libri.

### Prestiti e Restituzioni
*   **Scanner Mode**: Interfaccia ottimizzata per l'uso con lettori di codici a barre per check-out e check-in rapidi.
*   **Regole Personalizzabili**: Limiti di prestiti e durata configurabili per ruolo utente (Studenti, Docenti, Staff).
*   **Prenotazioni**: Sistema di coda intelligente. Se una copia non è disponibile, l'utente può prenotarsi; al rientro, il libro viene assegnato automaticamente al primo in lista.

### Sistema Finanziario (Multe)
*   **Calcolo Automatico**: Sanzioni per ritardo (con giorni di tolleranza) e per danni/smarrimento.
*   **Gestione Pagamenti**: Registrazione dei pagamenti e generazione di Quietanza PDF.
*   **Reportistica**: Storico delle transazioni e individuazione dei top debitori.

### Notifiche e Comunicazioni
*   **Canali Multipli**: Notifiche in-app (campanella) e via Email (SMTP).
*   **Automazione**: Cron job per inviare avvisi di scadenza imminente o prestiti scaduti.
*   **Template PDF**: Generazione automatica di ricevute di prestito e restituzione.

---

## Tecnologie Utilizzate

*   **Backend**: PHP 8.0+ (Struttura MVC Custom, PDO per database, Standard PSR-4).
*   **Frontend**: HTML5, JavaScript (Vanilla), Tailwind CSS.
*   **Database**: MySQL / MariaDB.
*   **Librerie Esterne**:
    *   `phpmailer/phpmailer`: Invio email transazionali.
    *   `tecnickcom/tcpdf`: Generazione documenti PDF.
    *   `vlucas/phpdotenv`: Gestione variabili d'ambiente.

---

## Installazione e Prima Configurazione

Questo progetto include uno script di installazione automatica che configura il database, crea l'utente amministratore e genera il file di configurazione necessario.

### Requisiti Preliminari
Assicurati che il tuo ambiente disponga di:
*   PHP >= 8.0
*   MySQL o MariaDB
*   Server Web (Apache/Nginx)
*   Composer (per installare le dipendenze)

### Setup dei File
Clona il repository o estrai i file nella root del tuo server web (es. `htdocs/` o `/var/www/html/`).

```bash
git clone https://github.com/tuo-username/stackmasters.git
cd stackmasters
```

Installa le dipendenze PHP tramite Composer:

```bash
composer install
```

**Permessi di Scrittura:**
Affinché l'installer funzioni correttamente, assicurati che il server web abbia i permessi di scrittura sulla cartella principale (per generare il file `.env`) e sulle directory di log e documenti.

Esempio su Linux/Mac:
```bash
chmod -R 775 public/assets/docs
chmod -R 775 logs
chown -R www-data:www-data .
```

### Procedura Guidata (Web Installer)
Non è necessario configurare manualmente il database. Apri il tuo browser e naviga verso il file di installazione:

`http://localhost/StackMasters/public/install.php`

*(Sostituisci localhost/StackMasters con il percorso effettivo del tuo server)*

Segui i passaggi a schermo:
1.  **Check Requisiti**: Il sistema verificherà la versione di PHP e le estensioni.
2.  **Database & Admin**: Inserisci le credenziali del database (Host, User, Password, Nome DB) e crea il primo account Amministratore.
3.  **Completamento**: Il sistema importerà le tabelle e genererà il file `.env`.

---

## Configurazione Post-Installazione

### Pulizia
Per motivi di sicurezza, una volta completata l'installazione, **elimina** il file `public/install.php` e la cartella `setup/`.

Da terminale:
```bash
rm public/install.php
rm -rf setup/
```

### Configurazione Email (SMTP)
Per abilitare l'invio delle email (recupero password, avvisi scadenza), modifica il file `.env` nella root del progetto:

```ini
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=tuo_indirizzo@example.com
SMTP_PASS=tua_password
SMTP_SECURE=tls
FROM_EMAIL=biblioteca@example.com
FROM_NAME="Biblioteca StackMasters"
```

### Automazione (Cron Jobs)
Per garantire il funzionamento delle notifiche di scadenza e il calcolo automatico delle multe, configura un'attività pianificata giornaliera (es. alle 08:00).

**Linux (Crontab):**
Apri il crontab con `crontab -e` e aggiungi:
```bash
0 8 * * * php /var/www/html/StackMasters/scripts/cron_scadenze.php >> /var/www/html/StackMasters/logs/cron.log 2>&1
```

**Windows (Task Scheduler):**
Crea un task che esegue `php.exe` con argomento:
`C:\xampp\htdocs\StackMasters\scripts\cron_scadenze.php`

---

## Guida ai Ruoli

### Studente
*   **Dashboard**: Visualizza prestiti attivi, scadenze imminenti e storico letture.
*   **Catalogo**: Cerca libri, visualizza disponibilità e prenota copie non disponibili.
*   **Profilo**: Gestione dati personali e cambio password.

### Bibliotecario
*   **Nuovo Prestito**: Scansiona tessera utente e barcode libro per registrare un prestito in pochi secondi.
*   **Restituzioni**: Gestisce il rientro dei libri, valuta le condizioni (Buono/Usurato/Danneggiato) e applica eventuali multe.
*   **Gestione Utenti**: Registra nuovi utenti, sblocca account sospesi.
*   **Inventario**: Aggiunge nuove copie, stampa etichette, modifica collocazioni.

### Amministratore
*   **KPI & Statistiche**: Dashboard con grafici sull'andamento dei prestiti e libri più popolari.
*   **Gestione Staff**: Crea e gestisce account per bibliotecari.
*   **Configurazione**: Imposta regole globali (giorni prestito, importo multe).

---

## Architettura del Sistema

Il progetto è strutturato seguendo un pattern MVC personalizzato.

### Struttura delle Directory
*   **src/**: Cuore dell'applicazione.
    *   `Controllers/`: Gestiscono la logica di flusso (`LoanController`, `FineController`).
    *   `Models/`: Interagiscono con il DB (`BookModel`, `UserModel`, `Loan`).
    *   `Services/`: Logica di business complessa (`LoanService`, `GoogleBooksService`).
    *   `Helpers/`: Utility per PDF e validazione (`RicevutaPrestitoPDF`, `IsbnValidator`).
*   **public/**: Entry point web. Contiene `index.php`, assets (JS/CSS) e file pubblici.
*   **dashboard/**: Viste protette divise per ruolo (`admin`, `librarian`, `student`).
*   **scripts/**: Script CLI per automazione (`cron_scadenze.php`).

### Database
Il sistema utilizza un database relazionale (MySQL) con tabelle principali per:
*   `utenti` e `ruoli` (RBAC).
*   `libri`, `inventari` (copie fisiche) e `autori`.
*   `prestiti` e `prenotazioni`.
*   `multe` e `transazioni`.

### Sicurezza
*   **Password**: Hashate con BCRYPT.
*   **Database**: Utilizzo esclusivo di Prepared Statements (PDO) per prevenire SQL Injection.
*   **Sessioni**: Gestione sicura delle sessioni e controllo accessi per ruolo.
*   **Brute Force**: Blocco temporaneo dell'account dopo 5 tentativi di login falliti.

---

## Troubleshooting

**Le email non arrivano?**
1.  Verifica le credenziali SMTP nel file `.env`.
2.  Controlla se il tuo provider blocca le connessioni SMTP non sicure (es. Gmail richiede App Password).
3.  Esegui lo script di debug da terminale:
    ```bash
    php scripts/debug_email.php
    ```

**Errore "Permesso negato" sui PDF?**
Assicurati che la cartella `public/assets/docs` sia scrivibile dall'utente del server web (es. `www-data` su Linux).

**Il Cron Job non funziona?**
Verifica di utilizzare il percorso assoluto corretto per l'eseguibile PHP e per lo script `cron_scadenze.php`. Controlla il file di log in `logs/cron.log`.
