[**Entra nel sito**](http://localhost/StackMasters/setup/install.php)

Installazione e Prima Configurazione

Questo progetto include uno script di installazione automatica che configura il database, crea l'utente amministratore e genera il file di configurazione necessario.
1. Requisiti Preliminari

Assicurati che il tuo ambiente disponga di:

    PHP >= 8.0

    MySQL o MariaDB

    Server Web (Apache/Nginx)

2. Setup dei File

Clona il repository o estrai i file nella root del tuo server web (es. htdocs/ o /var/www/html/).
Bash

git clone https://github.com/tuo-username/stackmasters.git

Permessi di Scrittura: Affinché l'installer funzioni correttamente, assicurati che il server web abbia i permessi di scrittura sulla cartella principale (per generare il file .env) e sulle seguenti directory:

    /public/assets/docs (per i PDF generati)

    /logs

3. Procedura Guidata (Web Installer)

Non è necessario configurare manualmente il database. Apri il tuo browser e naviga verso il file di installazione:

http://localhost/StackMasters/public/install.php

(Sostituisci localhost/StackMasters con il percorso effettivo del tuo server)

Segui i passaggi a schermo:

    Check Requisiti: Il sistema verificherà la versione di PHP e le estensioni.

    Database & Admin: Inserisci le credenziali del database (Host, User, Password, Nome DB) e crea il primo account Amministratore.

    Completamento: Il sistema importerà le tabelle e genererà il file .env.
