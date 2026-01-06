<?php
/**
 * registra-prestito.php - ELABORAZIONE SEQUENZIALE FINALE
 * Percorso: dashboard/librarian/registra-prestito.php
 */

// 1. ABILITAZIONE ERRORI PER IL MONITORAGGIO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<body style='font-family: sans-serif; background: #f4f6f9; padding: 20px;'>";
echo "<div style='max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);'>";
echo "<h1 style='color: #bf2121; border-bottom: 2px solid #eee; padding-bottom: 10px;'>🚀 Elaborazione Circolazione</h1>";

// 2. Inclusione dipendenze (Sincronizzate con il tuo schema)
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ottaviodipisa\StackMasters\Models\Loan;
use Ottaviodipisa\StackMasters\Helpers\RicevutaPrestitoPDF;

// 3. Recupero Dati dal Form
$userCode = $_POST['user_barcode'] ?? '';
$bookIds  = $_POST['book_ids'] ?? [];

echo "<p><strong>Analisi input:</strong> Utente [$userCode] - Volumi scansionati [" . count($bookIds) . "]</p>";

if (empty($userCode) || empty($bookIds)) {
    die("<div style='color:red; padding:15px; background:#fff5f5; border:1px solid red;'>🛑 ERRORE: Nessun dato ricevuto. Assicurati di aver scansionato sia l'utente che almeno un libro.</div>");
}

try {
    // 4. Connessione DB via Singleton
    $db = Database::getInstance()->getConnection();
    $loanModel = new Loan();

    // 5. Ricerca Dati Utente (necessari per l'intestazione della ricevuta)
    echo "🔍 Identificazione utente in corso... ";
    $stmtU = $db->prepare("SELECT id_utente, nome, cognome, email FROM utenti WHERE cf = :cf OR id_utente = :id LIMIT 1");
    $stmtU->execute(['cf' => $userCode, 'id' => $userCode]);
    $utente = $stmtU->fetch(PDO::FETCH_ASSOC);

    if (!$utente) {
        die("<span style='color:red;'>FALLITO. Utente non trovato nel database.</span>");
    }
    echo "<span style='color:green; font-weight:bold;'>OK ({$utente['nome']} {$utente['cognome']})</span><br><br>";

    // 6. Ciclo di Registrazione Prestiti
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
    echo "<strong>📦 Registrazione volumi:</strong><br>";
    $successi = [];
    $errori = [];

    foreach ($bookIds as $idInventario) {
        echo "— Copia #$idInventario: ";
        try {
            // Chiamata al metodo del modello (gestisce la transazione internamente)
            $res = $loanModel->registraPrestito((int)$utente['id_utente'], (int)$idInventario);

            // Recupero Titolo del libro (Tabella: libri join inventari)
            $stmtL = $db->prepare("
                SELECT l.titolo 
                FROM libri l 
                JOIN inventari i ON l.id_libro = i.id_libro 
                WHERE i.id_inventario = ?
            ");
            $stmtL->execute([$idInventario]);
            $infoLibro = $stmtL->fetch(PDO::FETCH_ASSOC);

            $successi[] = [
                'id_inventario' => $idInventario,
                'titolo' => $infoLibro['titolo'] ?? 'Titolo non disponibile',
                'scadenza' => $res['data_scadenza']
            ];
            echo "<span style='color:green;'>REGISTRATO (Scadenza: " . date('d/m/Y', strtotime($res['data_scadenza'])) . ")</span><br>";
        } catch (Exception $e) {
            $errori[] = $e->getMessage();
            echo "<span style='color:red;'>ERRORE: " . $e->getMessage() . "</span><br>";
        }
    }
    echo "</div>";

    // 7. Controllo Risultati Finali
    if (empty($successi)) {
        echo "<div style='color:red; font-weight:bold;'>Operazione annullata: nessun prestito è stato registrato correttamente.</div>";
        echo "<br><a href='new_loan.php' style='text-decoration:none; color:#666;'>&larr; Torna alla scansione</a>";
        exit;
    }

    // 8. Generazione Ricevuta PDF
    echo "🖨️ Generazione ricevuta PDF... ";
    $datiRicevuta = [
        'utente' => $utente,
        'libri' => $successi,
        'data_operazione' => date('d/m/Y H:i')
    ];

    $pdfFileName = RicevutaPrestitoPDF::genera($datiRicevuta);

    if ($pdfFileName) {
        echo "<span style='color:green; font-weight:bold;'>COMPLETATA</span><br><br>";

        // BOX DI SUCCESSO FINALE
        echo "<div style='text-align:center; padding:30px; border:3px solid #28a745; background:#eef9f1; border-radius:15px;'>";
        echo "<h2 style='color:#155724; margin-top:0;'>✅ Prestito Concluso!</h2>";
        echo "<p style='margin-bottom:20px;'>Tutti i dati sono stati salvati. Puoi scaricare la ricevuta qui sotto.</p>";
        echo "<a href='../../public/assets/docs/$pdfFileName' target='_blank' style='display:inline-block; padding:15px 35px; background:#bf2121; color:white; text-decoration:none; border-radius:8px; font-weight:bold; font-size:18px; box-shadow: 0 4px 12px rgba(191, 33, 33, 0.3);'>📥 SCARICA PDF RICEVUTA</a>";
        echo "<br><br><a href='new_loan.php' style='color:#666; font-size:14px; font-weight:bold;'>&larr; Effettua una nuova scansione</a>";
        echo "</div>";
    } else {
        echo "<div style='color:orange; padding:15px; border:1px solid orange;'>⚠️ ATTENZIONE: I prestiti sono registrati, ma la generazione del PDF è fallita. Verifica la cartella <code>public/assets/docs/</code>.</div>";
    }

} catch (Exception $e) {
    echo "<div style='padding:20px; background:#fff5f5; border:2px solid #feb2b2; color:#c53030; border-radius:10px; margin-top:20px;'>";
    echo "<strong>ERRORE CRITICO DI SISTEMA:</strong><br>" . $e->getMessage();
    echo "</div>";
}

echo "</div></body>";


require_once __DIR__ . '/../../src/Views/layout/footer.php';
