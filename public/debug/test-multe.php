<?php
/**
 * File di test per lo script di calcolo multe.
 * Spostato in /public/debug/
 */

// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Imposta l'header per una visualizzazione pulita del testo
header('Content-Type: text/plain; charset=utf-8');

echo "--- Inizio Test Calcolo Multe Notturne (da /debug) ---\n\n";

// CORREZIONE PERCORSO E NOME FILE: Aggiunto un '../' e cambiato nome a multe-notturne.php
require_once __DIR__ . '/../../src/utils/multe-notturne.php';

// Usa la classe dal suo namespace
use Ottaviodipisa\StackMasters\utils\MulteNotturne\CalcolaMulteCron;

try {
    // Avvia il buffer di output per catturare l'eco (i log) dello script
    ob_start();

    // Crea un'istanza della classe e lancia il processo di calcolo
    $cron = new CalcolaMulteCron();
    $cron->esegui();

    // Recupera l'output catturato e lo pulisce
    $output = ob_get_clean();

    echo "--- Esecuzione Completata ---\n\n";
    echo "Output dello script (log in tempo reale):\n";
    echo "-------------------------------------\n";
    echo trim($output) ? $output : "Nessun output generato (probabilmente nessuna operazione da eseguire).\n";
    echo "-------------------------------------\n\n";

    // CORREZIONE PERCORSO: Aggiunto un '../' per il log
    $logDir = __DIR__ . '/../../src/logs/';
    $logFile = $logDir . 'cron_multe_' . date('Y-m-d') . '.log';

    if (file_exists($logFile)) {
        echo "Contenuto del file di log fisico ($logFile):\n";
        echo "-------------------------------------\n";
        echo file_get_contents($logFile);
        echo "-------------------------------------\n";
    } else {
        echo "Il file di log fisico non è stato trovato o non è stato ancora creato oggi.\n";
    }

} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo "!!! ERRORE CRITICO DURANTE L'ESECUZIONE !!!\n";
    echo "Messaggio: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Linea: " . $e->getLine() . ")\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n--- Fine Test ---";
