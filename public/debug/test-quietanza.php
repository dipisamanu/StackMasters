<?php
/**
 * FILE: public/debug/test-quietanza.php
 * Script di test per verificare la generazione della quietanza PDF.
 */

// 1. Inclusione dell'Autoloader di Composer
// CORREZIONE PERCORSO: Aggiunto un '../' per risalire dalla cartella /debug
require_once __DIR__ . '/../../vendor/autoload.php';

// Inclusione manuale dell'Helper
// CORREZIONE PERCORSO: Aggiunto un '../'
require_once __DIR__ . '/../../src/Helpers/RicevutaPagamentoPDF.php';

use Ottaviodipisa\StackMasters\Helpers\RicevutaPagamentoPDF;

try {
    echo "--- TEST GENERAZIONE QUIETANZA PDF (da /debug) ---\n\n";

    // 2. Definizione dati mock (finti) per il test
    $mockUser = [
        'id_utente' => 101,
        'nome'      => 'Mario',
        'cognome'   => 'Rossi',
        'email'     => 'mario.rossi@esempio.it'
    ];

    $totalSaldato = 12.50;

    // 3. Istanza dell'Helper
    $helper = new RicevutaPagamentoPDF();

    echo "Generazione in corso per l'utente: {$mockUser['nome']} {$mockUser['cognome']}...\n";

    // 4. Esecuzione del metodo di generazione
    // L'helper salva internamente in public/assets/docs/
    $fileName = $helper->generateQuietanza($mockUser, $totalSaldato);

    // 5. Verifica del risultato
    // CORREZIONE PERCORSO: Il percorso di salvataggio è relativo alla root del progetto,
    // quindi dobbiamo risalire da /debug a /public per trovare /assets
    $fullPath = __DIR__ . "/../assets/docs/" . $fileName;

    if (file_exists($fullPath)) {
        echo "\n✅ TEST RIUSCITO!\n";
        echo "File generato: " . $fileName . "\n";
        echo "Percorso completo: " . realpath($fullPath) . "\n";
        echo "Dimensione file: " . filesize($fullPath) . " byte\n";
        
        // CORREZIONE PERCORSO: Il link deve risalire di un livello per trovare la cartella assets
        echo "\nPuoi visualizzarlo qui: <a href='../assets/docs/$fileName' target='_blank'>Apri PDF</a>";
    } else {
        echo "\n❌ ERRORE: Il file è stato dichiarato come generato ma non esiste nel percorso di verifica.\n";
        echo "Controllato in: " . realpath(dirname($fullPath)) . "\n";
        echo "Verifica che la cartella 'assets/docs/' esista dentro 'public/'.\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERRORE CRITICO DURANTE IL TEST:\n";
    echo $e->getMessage() . "\n";
    echo "Dettagli: " . $e->getTraceAsString() . "\n";
}
