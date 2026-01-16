<?php
/**
 * test-quietanza-pagamento.php - Test per Pagamento Multe e Generazione Quietanza PDF
 * Percorso: public/debug/test-quietanza-pagamento.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

// Inclusione delle dipendenze necessarie (con percorsi corretti da /debug)
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/Models/Fine.php';
require_once __DIR__ . '/../../src/Helpers/RicevutaPagamentoPDF.php';

use Ottaviodipisa\StackMasters\Models\Fine;
use Ottaviodipisa\StackMasters\Helpers\RicevutaPagamentoPDF;

// Stile per un output leggibile
echo <<<HTML
<style>
    body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; padding: 2rem; }
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
    .container { max-width: 900px; margin: auto; background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
    h1 { font-size: 1.5rem; font-weight: 900; color: #059669; border-bottom: 3px solid #6ee7b7; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
    .test-case { border: 1px solid #e2e8f0; border-radius: 0.75rem; margin-bottom: 1.5rem; overflow: hidden; }
    .test-header { padding: 1rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .test-title { font-weight: 700; color: #1e293b; }
    .test-body { padding: 1.5rem; font-size: 0.9rem; line-height: 1.6; }
    .result { padding: 0.5rem 1rem; border-radius: 99px; font-weight: 700; font-size: 0.8rem; display: inline-block; margin-right: 10px; }
    .result.pass { background-color: #dcfce7; color: #166534; }
    .result.fail { background-color: #fee2e2; color: #991b1b; }
    .check { margin-bottom: 0.5rem; }
    .pdf-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #4f46e5; color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 600; }
</style>
<div class="container">
    <h1><i class="fas fa-receipt"></i> Test Suite: Pagamento e Quietanza</h1>
HTML;

$db = null;
$pdfFileToClean = null;

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // --- 1. SETUP DATI DI TEST ---
    $testUserId = 999998;
    $testFineAmount = 25.50;
    $db->exec("INSERT INTO utenti (id_utente, cf, nome, cognome, email, password, consenso_privacy) VALUES ($testUserId, 'UTENTETESTQ', 'Franco', 'Quietanza', 'quietanza@test.com', 'test', 1)");
    $db->exec("INSERT INTO multe (id_utente, importo, causa) VALUES ($testUserId, $testFineAmount, 'RITARDO')");

    echo '<div class="test-case"><div class="test-header"><p class="test-title">Test: Pagamento completo e generazione PDF</p></div><div class="test-body">';

    // --- 2. ESECUZIONE LOGICA (usando i metodi reali del modello) ---
    $fineModel = new Fine();
    $pdfHelper = new RicevutaPagamentoPDF();

    // Recupera i dati utente e il debito totale usando il metodo corretto
    $userData = $fineModel->getUserBalance($testUserId);
    $totalToPay = $userData['debito_totale'];

    // Processa il pagamento usando il metodo corretto
    $paymentResult = $fineModel->processPayment($testUserId, $totalToPay);

    // Genera la quietanza PDF
    $pdfFileName = $pdfHelper->generateQuietanza($userData, $totalToPay);
    $pdfFileToClean = __DIR__ . '/../assets/docs/' . $pdfFileName;

    // --- 3. VERIFICA RISULTATI ---
    $allTestsPassed = true;

    // Verifica 1: Il PDF è stato creato?
    echo '<div class="check">';
    if (file_exists($pdfFileToClean)) {
        echo '<span class="result pass">PASS</span> Il file PDF <code>' . htmlspecialchars($pdfFileName) . '</code> è stato generato correttamente.';
    } else {
        echo '<span class="result fail">FAIL</span> Il file PDF non è stato trovato nel percorso atteso.';
        $allTestsPassed = false;
    }
    echo '</div>';

    // Verifica 2: Le multe sono state saldate nel DB?
    $remainingBalance = $fineModel->getUserBalance($testUserId)['debito_totale'];
    
    echo '<div class="check">';
    if ($remainingBalance == 0) {
        echo '<span class="result pass">PASS</span> Le multe per l\'utente di test risultano saldate nel database (Debito residuo: 0€).';
    } else {
        echo '<span class="result fail">FAIL</span> Le multe non risultano saldate. Debito residuo: ' . $remainingBalance . '€.</span>';
        $allTestsPassed = false;
    }
    echo '</div>';
    
    // Verifica 3: L'importo da pagare era corretto?
    echo '<div class="check">';
    if ($totalToPay == $testFineAmount) {
        echo '<span class="result pass">PASS</span> L\'importo totale da saldare (<code>' . $totalToPay . '€</code>) è stato calcolato correttamente.';
    } else {
        echo '<span class="result fail">FAIL</span> L\'importo calcolato (<code>' . $totalToPay . '€</code>) non corrisponde a quello atteso (<code>' . $testFineAmount . '€</code>).';
        $allTestsPassed = false;
    }
    echo '</div>';

    if ($allTestsPassed && file_exists($pdfFileToClean)) {
        echo "<a href='../assets/docs/" . htmlspecialchars($pdfFileName) . "' target='_blank' class='pdf-link'>Visualizza PDF Generato</a>";
    }

    echo '</div></div>';

} catch (Exception $e) {
    echo "<div style='background: #fecaca; color: #991b1b; padding: 1.5rem; border-radius: 0.5rem;'>";
    echo "<b>ERRORE CRITICO NELLO SCRIPT DI TEST:</b> " . $e->getMessage();
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
} finally {
    // --- 4. PULIZIA ---
    if ($db && $db->inTransaction()) {
        $db->rollBack();
        echo "<p style='text-align:center; color: #16a34a; font-weight: bold;'>✅ Transazione annullata. Il database è stato ripristinato.</p>";
    }
    
    // CORREZIONE: La riga seguente è stata commentata per permettere la visualizzazione del PDF.
    // if ($pdfFileToClean && file_exists($pdfFileToClean)) {
    //     unlink($pdfFileToClean);
    //     echo "<p style='text-align:center; color: #16a34a; font-weight: bold;'>✅ File PDF di test cancellato.</p>";
    // }
}

echo "</div>";
