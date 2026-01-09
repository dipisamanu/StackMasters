#!/bin/bash

# Ottieni il percorso della cartella corrente (dove si trova questo script)
# e sale di un livello per trovare la root del progetto
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "Configurazione automatica per StackMasters..."
echo "Root del progetto rilevata: $PROJECT_ROOT"

# Percorso dell'eseguibile PHP (prova a trovarlo da solo)
PHP_BIN=$(which php)

if [ -z "$PHP_BIN" ]; then
    echo "ERRORE: PHP non trovato. Assicurati che PHP sia installato."
    exit 1
fi

echo "PHP trovato in: $PHP_BIN"

# Definizione dei Job
CRON_JOB_1="0 8 * * * $PHP_BIN $PROJECT_ROOT/scripts/cron_scadenze.php >> $PROJECT_ROOT/logs/cron_scadenze.log 2>&1"
CRON_JOB_2="*/5 * * * * $PHP_BIN $PROJECT_ROOT/scripts/cron_email.php >> $PROJECT_ROOT/logs/cron_email.log 2>&1"

# Backup del crontab attuale
crontab -l > mycron_backup 2>/dev/null

# Aggiungi i nuovi job se non esistono già
echo "Aggiungo i task..."

# Usa grep per vedere se il job esiste già, se no lo aggiunge
grep -qF "$PROJECT_ROOT/scripts/cron_scadenze.php" mycron_backup || echo "$CRON_JOB_1" >> mycron_backup
grep -qF "$PROJECT_ROOT/scripts/cron_email.php" mycron_backup || echo "$CRON_JOB_2" >> mycron_backup

# Installa il nuovo file crontab
crontab mycron_backup
rm mycron_backup

echo "✅ Fatto! I Cron Job sono stati installati."
echo "  - Controllo scadenze: Ogni giorno alle 08:00"
echo "  - Invio email: Ogni 5 minuti"