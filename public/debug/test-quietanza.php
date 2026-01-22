<?php
/**
 * FILE: public/debug/test-quietanza.php
 * Script di test per verificare la generazione della quietanza PDF.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Helpers/RicevutaPagamentoPDF.php';

use Ottaviodipisa\StackMasters\Helpers\RicevutaPagamentoPDF;

try {
    echo "--- TEST GENERAZIONE QUIETANZA PDF (da /debug) ---\n\n";

    // Dati mock per il test
    $mockUser = [
        'id_utente' => 101,
        'nome'      => 'Mario',
        'cognome'   => 'Rossi',
        'email'     => 'mario.rossi@esempio.it'
    ];

    $totalSaldato = 12.50;

    $helper = new RicevutaPagamentoPDF();

    echo "Generazione in corso per l'utente: {$mockUser['nome']} {$mockUser['cognome']}...\n";

    // Esecuzione del metodo di generazione
    // L'helper salva internamente in public/assets/docs/
    $fileName = $helper->generateQuietanza($mockUser, $totalSaldato);

    // Verifica del risultato
    $fullPath = __DIR__ . "/../assets/docs/" . $fileName;

    if (file_exists($fullPath)) {
        echo "\n✅ TEST RIUSCITO!\n";
        echo "File generato: " . $fileName . "\n";
        echo "Percorso completo: " . realpath($fullPath) . "\n";
        echo "Dimensione file: " . filesize($fullPath) . " byte\n";
        echo "\nPuoi visualizzarlo qui: <a href='../assets/docs/$fileName' target='_blank'><i class='fas fa-file-pdf'></i> Apri PDF</a>";
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
