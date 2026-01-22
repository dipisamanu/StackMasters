<?php
/**
 * test-restituzione-danno.php - Test per la Restituzione con Danno e Generazione PDF
 * Percorso: public/debug/test-restituzione-danno.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

// Inclusione delle dipendenze necessarie (con percorsi corretti da /debug)
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/Models/Loan.php';
require_once __DIR__ . '/../../src/Helpers/RicevutaRestituzionePDF.php';

use Ottaviodipisa\StackMasters\Models\Loan;
use Ottaviodipisa\StackMasters\Helpers\RicevutaRestituzionePDF;

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
    <h1><i class="fas fa-hammer"></i> Test Suite: Restituzione con Danno</h1>
HTML;

$db = null;
$pdfFileToClean = null;

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // --- 1. SETUP DATI DI TEST ---
    $testUserId = 999995;
    $testBookId = 999995;
    $testInventoryId = 999995;
    $testValoreCopertina = 50.00;
    $expectedFine = round($testValoreCopertina * 0.50, 2); // 50% per DANNEGGIATO

    // Utente
    $db->exec("INSERT INTO utenti (id_utente, cf, nome, cognome, email, password, consenso_privacy) VALUES ($testUserId, 'TESTDANNO', 'Marco', 'Danneggiato', 'danno@test.com', 'test', 1)");
    // CORREZIONE: Aggiunto l'utente di test alla tabella utenti_ruoli
    $db->exec("INSERT INTO utenti_ruoli (id_utente, id_ruolo, prestiti_tot, streak_restituzioni) VALUES ($testUserId, 3, 0, 0)"); // Ruolo 3 = Studente

    // Libro con valore copertina
    $db->exec("INSERT INTO libri (id_libro, titolo, isbn, valore_copertina) VALUES ($testBookId, 'Libro Test Danno', '9781234567890', $testValoreCopertina)");
    // Inventario in condizione BUONO
    $db->exec("INSERT INTO inventari (id_inventario, id_libro, condizione) VALUES ($testInventoryId, $testBookId, 'BUONO')");
    // Prestito attivo
    $db->exec("INSERT INTO prestiti (id_inventario, id_utente, data_prestito, scadenza_prestito) VALUES ($testInventoryId, $testUserId, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))");

    echo '<div class="test-case"><div class="test-header"><p class="test-title">Test: Restituzione di libro danneggiato</p></div><div class="test-body">';

    // --- 2. ESECUZIONE LOGICA ---
    $loanModel = new Loan();
    $pdfHelper = new RicevutaRestituzionePDF();

    $condizioneRientro = 'DANNEGGIATO';
    $commentoDanno = 'Copertina strappata e pagine bagnate.';

    // Chiamata al metodo di restituzione
    $result = $loanModel->registraRestituzione($testInventoryId, $condizioneRientro, $commentoDanno);

    // --- 3. VERIFICA RISULTATI ---
    $allTestsPassed = true;

    // Verifica 1: Multa generata dal metodo
    echo '<div class="check">';
    if (isset($result['multa_totale']) && $result['multa_totale'] == $expectedFine) {
        echo '<span class="result pass">PASS</span> Multa generata dal metodo: <code>' . $result['multa_totale'] . '€</code> (atteso: <code>' . $expectedFine . '€</code>).';
    } else {
        echo '<span class="result fail">FAIL</span> Multa generata non corretta: <code>' . ($result['multa_totale'] ?? 'N/D') . '€</code> (atteso: <code>' . $expectedFine . '€</code>).';
        $allTestsPassed = false;
    }
    echo '</div>';

    // Verifica 2: Multa registrata nel DB
    $stmt = $db->prepare("SELECT * FROM multe WHERE id_utente = ? AND causa = 'DANNI' ORDER BY id_multa DESC LIMIT 1");
    $stmt->execute([$testUserId]);
    $dbFine = $stmt->fetch(PDO::FETCH_ASSOC);

    echo '<div class="check">';
    if ($dbFine && $dbFine['importo'] == $expectedFine && $dbFine['commento'] === "Stato: DANNEGGIATO (da BUONO). " . $commentoDanno) {
        echo '<span class="result pass">PASS</span> Multa per danni registrata correttamente nel database.';
    } else {
        echo '<span class="result fail">FAIL</span> Multa per danni non registrata o non corretta nel database.';
        $allTestsPassed = false;
    }
    echo '</div>';

    // Verifica 3: Generazione PDF
    // Recupera i dati necessari per il PDF
    $utenteData = $db->prepare("SELECT id_utente, nome, cognome, cf FROM utenti WHERE id_utente = ?");
    $utenteData->execute([$testUserId]);
    $utenteData = $utenteData->fetch(PDO::FETCH_ASSOC);

    $libroData = $db->prepare("SELECT titolo, isbn FROM libri WHERE id_libro = ?");
    $libroData->execute([$testBookId]);
    $libroData = $libroData->fetch(PDO::FETCH_ASSOC);
    
    $datiPDF = [
        'utente' => $utenteData,
        'libri' => [
            [
                'id_inventario' => $testInventoryId,
                'titolo' => $libroData['titolo'],
                'isbn' => $libroData['isbn'],
                'condizione' => $condizioneRientro,
                'multa' => $result['multa_totale'],
                'condizione_partenza' => 'BUONO'
            ]
        ],
        'data_operazione' => date('d/m/Y H:i')
    ];

    $pdfFileName = $pdfHelper->genera($datiPDF);
    $pdfFileToClean = __DIR__ . '/../assets/docs/' . $pdfFileName;

    echo '<div class="check">';
    if (file_exists($pdfFileToClean)) {
        echo '<span class="result pass">PASS</span> Il file PDF <code>' . htmlspecialchars($pdfFileName) . '</code> è stato generato.';
    } else {
        echo '<span class="result fail">FAIL</span> Il file PDF non è stato trovato.';
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
    // Lasciamo il PDF per ispezione manuale, non lo cancelliamo qui.
    // if ($pdfFileToClean && file_exists($pdfFileToClean)) {
    //     unlink($pdfFileToClean);
    //     echo "<p style='text-align:center; color: #16a34a; font-weight: bold;'>✅ File PDF di test cancellato.</p>";
    // }
}

echo "</div>";
?>