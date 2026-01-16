<?php
/**
 * test-addebito-manuale.php - Test per l'Aggiunta di Addebiti Manuali con Commento
 * Percorso: public/debug/test-addebito-manuale.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

// Inclusione delle dipendenze necessarie
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/Models/Fine.php';

use Ottaviodipisa\StackMasters\Models\Fine;

// Stile per un output leggibile
echo <<<HTML
<style>
    body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; padding: 2rem; }
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
    .container { max-width: 900px; margin: auto; background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
    h1 { font-size: 1.5rem; font-weight: 900; color: #7c3aed; border-bottom: 3px solid #c4b5fd; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
    .test-case { border: 1px solid #e2e8f0; border-radius: 0.75rem; margin-bottom: 1.5rem; overflow: hidden; }
    .test-header { padding: 1rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .test-title { font-weight: 700; color: #1e293b; }
    .test-body { padding: 1.5rem; font-size: 0.9rem; line-height: 1.6; }
    .result { padding: 0.5rem 1rem; border-radius: 99px; font-weight: 700; font-size: 0.8rem; display: inline-block; }
    .result.pass { background-color: #dcfce7; color: #166534; }
    .result.fail { background-color: #fee2e2; color: #991b1b; }
    code { background: #e0e7ff; color: #4338ca; padding: 2px 5px; border-radius: 4px; font-weight: 600; }
</style>
<div class="container">
    <h1><i class="fas fa-pencil-alt"></i> Test Suite: Addebito Manuale</h1>
HTML;

$db = null;

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // --- 1. SETUP DATI DI TEST ---
    $testUserId = 999997;
    $db->exec("INSERT INTO utenti (id_utente, cf, nome, cognome, email, password, consenso_privacy) VALUES ($testUserId, 'UTENTETESTAD', 'Anna', 'Addebito', 'addebito@test.com', 'test', 1)");

    echo '<div class="test-case"><div class="test-header"><p class="test-title">Test: Inserimento addebito con commento</p></div><div class="test-body">';

    // --- 2. ESECUZIONE LOGICA ---
    $fineModel = new Fine();
    $testAmount = 15.00;
    $testReason = 'DANNI';
    $testComment = 'Copertina strappata e pagine bagnate.';

    $fineModel->addManualCharge($testUserId, $testAmount, $testReason, $testComment);

    // --- 3. VERIFICA RISULTATO ---
    $stmt = $db->prepare("SELECT * FROM multe WHERE id_utente = ? ORDER BY id_multa DESC LIMIT 1");
    $stmt->execute([$testUserId]);
    $insertedFine = $stmt->fetch();

    if ($insertedFine && $insertedFine['commento'] === $testComment) {
        echo '<p><span class="result pass">PASS</span> Il commento dell\'addebito è stato salvato correttamente nel database.</p>';
        echo '<p><b>Commento salvato:</b> <code>' . htmlspecialchars($insertedFine['commento']) . '</code></p>';
    } else {
        echo '<p><span class="result fail">FAIL</span> Il commento non è stato salvato o non corrisponde.</p>';
        echo '<p><b>Atteso:</b> <code>' . htmlspecialchars($testComment) . '</code></p>';
        echo '<p><b>Trovato:</b> <code>' . htmlspecialchars($insertedFine['commento'] ?? 'NULL') . '</code></p>';
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
        echo "<p style='text-align:center; color: #16a34a; font-weight: bold;'>Transazione annullata. Il database è stato ripristinato.</p>";
    }
}

echo "</div>";
