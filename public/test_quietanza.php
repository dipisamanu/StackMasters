<?php
/**
 * FILE: public/test_quietanza.php
 * Script di test per verificare la generazione della quietanza PDF.
 * Posizionato nella cartella public.
 */

// 1. Inclusione dell'Autoloader di Composer (risalendo dalla cartella public: ../)
require_once __DIR__ . '/../vendor/autoload.php';

// Inclusione manuale dell'Helper (risalendo dalla cartella public: ../)
require_once __DIR__ . '/../src/Helpers/RicevutaPagamentoPDF.php';

use Ottaviodipisa\StackMasters\Helpers\RicevutaPagamentoPDF;

try {
    echo "--- TEST GENERAZIONE QUIETANZA PDF ---\n";

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

    // 5. Verifica del risultato (dalla cartella public, cerchiamo in assets/docs/)
    $fullPath = __DIR__ . "/assets/docs/" . $fileName;

    if (file_exists($fullPath)) {
        echo "\n✅ TEST RIUSCITO!\n";
        echo "File generato: " . $fileName . "\n";
        echo "Percorso completo: " . $fullPath . "\n";
        echo "Dimensione file: " . filesize($fullPath) . " byte\n";
        echo "\nPuoi visualizzarlo qui: <a href='assets/docs/$fileName' target='_blank'>Apri PDF</a>";
    } else {
        echo "\n❌ ERRORE: Il file è stato dichiarato come generato ma non esiste nel percorso di verifica.\n";
        echo "Controllato in: " . $fullPath . "\n";
        echo "Verifica che la cartella 'assets/docs/' esista dentro 'public/'.\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERRORE CRITICO DURANTE IL TEST:\n";
    echo $e->getMessage() . "\n";
    echo "Dettagli: " . $e->getTraceAsString() . "\n";
}