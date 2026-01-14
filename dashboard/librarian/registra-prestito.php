<?php
/**
 * registra-prestito.php - ELABORAZIONE SEQUENZIALE FINALE
 * Percorso: dashboard/librarian/registra-prestito.php
 */

// 1. ABILITAZIONE ERRORI PER IL MONITORAGGIO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CSS Inline per un'interfaccia professionale
echo "
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; line-height: 1.5; padding: 40px 20px; }
    .container { max-width: 850px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden; }
    .header { background: #bf2121; padding: 30px; color: white; }
    .header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em; }
    .content { padding: 30px; }
    .section-title { font-size: 0.875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
    .log-entry { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.85rem; padding: 10px 15px; border-radius: 6px; margin-bottom: 8px; background: #f1f5f9; border-left: 4px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; }
    .log-entry.success { border-left-color: #10b981; background: #ecfdf5; color: #065f46; }
    .log-entry.error { border-left-color: #ef4444; background: #fef2f2; color: #991b1b; }
    .badge { padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem; white-space: nowrap; margin-left: 10px; }
    .badge-success { background: #10b981; color: white; }
    .badge-error { background: #ef4444; color: white; }
    .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
    .btn-download { display: inline-flex; align-items: center; justify-content: center; background: #bf2121; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 1rem; transition: background 0.2s; box-shadow: 0 10px 15px -3px rgba(191, 33, 33, 0.3); }
    .btn-download:hover { background: #9b1b1b; }
    .footer-nav { margin-top: 25px; text-align: center; }
    .footer-nav a { color: #64748b; text-decoration: none; font-size: 0.875rem; font-weight: 500; }
    .footer-nav a:hover { color: #bf2121; text-decoration: underline; }
</style>
";

echo "<div class='container'>";
echo "<div class='header'><h1>Gestione Circolazione</h1></div>";
echo "<div class='content'>";

// 2. Inclusione dipendenze
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ottaviodipisa\StackMasters\Models\Loan;
use Ottaviodipisa\StackMasters\Helpers\RicevutaPrestitoPDF;

// 3. Recupero Dati dal Form
$userCode = $_POST['user_barcode'] ?? '';
$bookIds  = $_POST['book_ids'] ?? [];

echo "<div class='summary-card'>";
echo "<div class='section-title'>Parametri della sessione</div>";
echo "<p style='margin: 0; font-size: 0.95rem;'>Identificativo Utente: <strong>" . htmlspecialchars($userCode) . "</strong></p>";
echo "<p style='margin: 5px 0 0 0; font-size: 0.95rem;'>Volumi in elaborazione: <strong>" . count($bookIds) . "</strong></p>";
echo "</div>";

if (empty($userCode) || empty($bookIds)) {
    die("<div class='log-entry error'>Interruzione: Dati insufficienti per procedere con la registrazione.</div></div></div>");
}

try {
    // 4. Connessione DB via Singleton
    $db = Database::getInstance()->getConnection();
    $loanModel = new Loan();

    // 5. Ricerca Dati Utente
    echo "<div class='section-title'>Identificazione Soggetto</div>";
    $stmtU = $db->prepare("SELECT id_utente, nome, cognome, email, cf FROM utenti WHERE cf = :cf OR id_utente = :id LIMIT 1");
    $stmtU->execute(['cf' => $userCode, 'id' => $userCode]);
    $utente = $stmtU->fetch(PDO::FETCH_ASSOC);

    if (!$utente) {
        die("<div class='log-entry error'>Anomalia: Utente non registrato nel sistema. Verifica fallita.</div></div></div>");
    }
    echo "<div class='log-entry success'>Anagrafica verificata: <span>" . strtoupper($utente['cognome']) . " " . strtoupper($utente['nome']) . "</span> <span class='badge badge-success'>VALIDO</span></div>";

    // 6. Ciclo di Registrazione Prestiti
    echo "<br><div class='section-title'>Registro Operazioni Volumi</div>";
    $successi = [];
    $errori = [];

    foreach ($bookIds as $idInventario) {
        try {
            // Chiamata al metodo del modello (dove vengono generati gli errori specifici)
            $res = $loanModel->registraPrestito((int)$utente['id_utente'], (int)$idInventario);

            // Recupero Titolo del libro per il log
            $stmtL = $db->prepare("
                SELECT l.titolo 
                FROM libri l 
                JOIN inventari i ON l.id_libro = i.id_libro 
                WHERE i.id_inventario = ?
            ");
            $stmtL->execute([$idInventario]);
            $infoLibro = $stmtL->fetch(PDO::FETCH_ASSOC);
            $titoloTroncato = (strlen($infoLibro['titolo']) > 45) ? substr($infoLibro['titolo'], 0, 42) . "..." : $infoLibro['titolo'];

            $successi[] = [
                'id_inventario' => $idInventario,
                'titolo' => $infoLibro['titolo'] ?? 'Titolo non disponibile',
                'scadenza' => $res['data_scadenza']
            ];

            echo "<div class='log-entry success'>
                    <span>Copia #$idInventario - " . htmlspecialchars($titoloTroncato) . "</span>
                    <span class='badge badge-success'>REGISTRATO</span>
                  </div>";
        } catch (Exception $e) {
            $errori[] = $e->getMessage();
            // Mostriamo l'errore specifico (es. 'Limite prestiti raggiunto')
            echo "<div class='log-entry error'>
                    <span>Copia #$idInventario - " . htmlspecialchars($e->getMessage()) . "</span>
                    <span class='badge badge-error'>RIFIUTATO</span>
                  </div>";
        }
    }

    // 7. Controllo Risultati Finali
    if (empty($successi)) {
        echo "<br><div class='log-entry error' style='justify-content: center; font-weight: 700;'>ATTENZIONE: Nessuna operazione è stata finalizzata.</div>";
        echo "<div class='footer-nav'><a href='new_loan.php'>Torna alla registrazione</a></div>";
        echo "</div></div>";
        exit;
    }

    // 8. Generazione Ricevuta PDF
    echo "<br><div class='section-title'>Finalizzazione Documentale</div>";
    $datiRicevuta = [
        'utente' => $utente,
        'libri' => $successi,
        'data_operazione' => date('d/m/Y H:i')
    ];

    $pdfFileName = RicevutaPrestitoPDF::genera($datiRicevuta);

    if ($pdfFileName) {
        echo "<div class='log-entry success' style='justify-content: center;'>Ricevuta PDF archiviata correttamente.</div>";

        // BOX DI SUCCESSO FINALE
        echo "<div style='text-align:center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0;'>";
        echo "<h2 style='color: #0f172a; margin-bottom: 25px; font-weight: 700;'>Ciclo operativo terminato</h2>";
        echo "<a href='../../public/assets/docs/$pdfFileName' target='_blank' class='btn-download'>SCARICA RICEVUTA PDF</a>";
        echo "<div class='footer-nav'><a href='new_loan.php'>Registra un nuovo prestito</a></div>";
        echo "</div>";
    } else {
        echo "<div class='log-entry error'>Avviso: Registrazione completata, ma errore nella creazione del PDF.</div>";
        echo "<div class='footer-nav'><a href='new_loan.php'>Torna alla registrazione</a></div>";
    }

} catch (Exception $e) {
    echo "<div class='log-entry error' style='margin-top:20px;'>
            <strong>ERRORE CRITICO:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
    echo "<div class='footer-nav'><a href='new_loan.php'>Inizializza nuova sessione</a></div>";
}

echo "</div>"; // fine content
echo "</div>"; // fine container
echo "</body>";

require_once __DIR__ . '/../../src/Views/layout/footer.php';