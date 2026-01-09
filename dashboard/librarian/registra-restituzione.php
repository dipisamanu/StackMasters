<?php
/**
 * registra-restituzione.php - ELABORAZIONE SEQUENZIALE RIENTRI
 * Percorso: dashboard/librarian/registra-restituzione.php
 * Riceve i dati JSON dal form di returns.php
 */

// 1. ABILITAZIONE ERRORI PER DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<body style='font-family: sans-serif; background: #f4f6f9; padding: 20px;'>";
echo "<div style='max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);'>";
echo "<h1 style='color: #28a745; border-bottom: 2px solid #eee; padding-bottom: 10px;'>🔄 Elaborazione Rientro Volumi</h1>";

// 2. Inclusione dipendenze
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ottaviodipisa\StackMasters\Models\Loan;
use Ottaviodipisa\StackMasters\Helpers\RicevutaRestituzionePDF;

// 3. Recupero Dati (Dati codificati in JSON dall'interfaccia returns.php)
$returnsData = $_POST['returns'] ?? [];

echo "<p><strong>Analisi input:</strong> Volumi scansionati per il rientro [" . count($returnsData) . "]</p>";

if (empty($returnsData)) {
    die("<div style='color:red; padding:15px; background:#fff5f5; border:1px solid red;'>🛑 ERRORE: Nessun dato ricevuto. Torna all'interfaccia e scansiona almeno un libro.</div>");
}

try {
    // 4. Connessione DB via Singleton
    $db = Database::getInstance()->getConnection();
    $loanModel = new Loan();

    $successi = [];
    $utenteDatiPDF = null;

    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
    echo "<strong>📦 Elaborazione collaudo e rientro:</strong><br>";

    // 5. Ciclo di Registrazione Restituzioni
    foreach ($returnsData as $jsonData) {
        // Decodifichiamo l'oggetto JSON inviato dal carrello JS
        $item = json_decode($jsonData, true);
        $idInventario = (int)$item['id'];
        $condizione = $item['cond'];
        $commento = $item['note'];

        echo "— Copia #$idInventario ($condizione): ";

        try {
            /** * 5.1 Identificazione Utente prima di chiudere il prestito
             * Serve per l'intestazione della ricevuta PDF
             */
            $stmtU = $db->prepare("
                SELECT u.* FROM utenti u 
                JOIN prestiti p ON u.id_utente = p.id_utente 
                WHERE p.id_inventario = ? AND p.data_restituzione IS NULL
            ");
            $stmtU->execute([$idInventario]);
            $utente = $stmtU->fetch(PDO::FETCH_ASSOC);

            if (!$utente) {
                throw new Exception("Nessun prestito attivo trovato per questa copia.");
            }

            // Salviamo l'anagrafica utente per il PDF (assumiamo sia lo stesso per il blocco)
            $utenteDatiPDF = $utente;

            // 5.2 Esecuzione logica di business (Multe, Danni, Stato Inventario)
            $res = $loanModel->registraRestituzione($idInventario, $condizione, $commento);

            // 5.3 Recupero Titolo del libro per il PDF
            $stmtL = $db->prepare("
                SELECT l.titolo 
                FROM libri l 
                JOIN inventari i ON l.id_libro = i.id_libro 
                WHERE i.id_inventario = ?
            ");
            $stmtL->execute([$idInventario]);
            $titolo = $stmtL->fetchColumn();

            $successi[] = [
                'id_inventario' => $idInventario,
                'titolo' => $titolo ?: "Copia #$idInventario",
                'condizione' => $condizione,
                'multa' => $res['multa_generata'] ?? 0
            ];

            echo "<span style='color:green; font-weight:bold;'>RIENTRATO</span>";
            if (isset($res['multa_generata']) && $res['multa_generata'] > 0) {
                echo " <span style='color:red;'>(Penale: {$res['multa_generata']} €)</span>";
            }
            echo "<br>";

        } catch (Exception $e) {
            echo "<span style='color:red;'>ERRORE: " . $e->getMessage() . "</span><br>";
        }
    }
    echo "</div>";

    // 6. Controllo Risultati Finali
    if (empty($successi)) {
        echo "<div style='color:red; font-weight:bold;'>Operazione fallita: non è stato possibile chiudere alcun prestito.</div>";
        echo "<br><a href='returns.php' style='text-decoration:none; color:#666;'>&larr; Torna alla scansione</a>";
        exit;
    }

    // 7. Generazione Ricevuta PDF
    echo "🖨️ Generazione ricevuta di rientro... ";
    $datiPDF = [
        'utente' => $utenteDatiPDF,
        'libri' => $successi,
        'data_operazione' => date('d/m/Y H:i')
    ];

    $pdfFileName = RicevutaRestituzionePDF::genera($datiPDF);

    if ($pdfFileName) {
        echo "<span style='color:green; font-weight:bold;'>OK</span><br><br>";

        // BOX DI SUCCESSO FINALE
        echo "<div style='text-align:center; padding:30px; border:3px solid #28a745; background:#eef9f1; border-radius:15px;'>";
        echo "<h2 style='color:#155724; margin-top:0;'>✅ Restituzione Registrata!</h2>";
        echo "<p style='margin-bottom:20px;'>I volumi sono tornati in inventario e le pendenze sono state aggiornate.</p>";
        echo "<a href='../../public/assets/docs/$pdfFileName' target='_blank' style='display:inline-block; padding:15px 35px; background:#28a745; color:white; text-decoration:none; border-radius:8px; font-weight:bold; font-size:18px; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);'>📥 SCARICA RICEVUTA RIENTRO</a>";
        echo "<br><br><a href='returns.php' style='color:#666; font-size:14px; font-weight:bold;'>&larr; Effettua una nuova restituzione</a>";
        echo "</div>";
    } else {
        echo "<div style='color:orange; padding:15px; border:1px solid orange; border-radius:10px;'>⚠️ ATTENZIONE: Restituzione registrata nel DB, ma errore nella creazione del PDF. Controlla i permessi della cartella.</div>";
    }

} catch (Exception $e) {
    echo "<div style='padding:20px; background:#fff5f5; border:2px solid #feb2b2; color:#c53030; border-radius:10px; margin-top:20px;'>";
    echo "<strong>ERRORE CRITICO:</strong><br>" . $e->getMessage();
    echo "</div>";
}

echo "</div></body>";

require_once __DIR__ . '/../../src/Views/layout/footer.php';
