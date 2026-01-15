<?php
/**
 * registra-restituzione.php - ELABORAZIONE SEQUENZIALE RIENTRI
 * Percorso: dashboard/librarian/registra-restituzione.php
 */

// 1. ABILITAZIONE ERRORI PER IL MONITORAGGIO TECNICO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CSS Inline per un'interfaccia professionale coordinata (Tema Rientro: Emerald)
echo "
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; line-height: 1.5; padding: 40px 20px; }
    .container { max-width: 850px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden; }
    
    /* Header coordinato: Emerald per la Restituzione */
    .header { background: #059669; padding: 30px; color: white; display: flex; align-items: center; justify-content: space-between; }
    .header h1 { margin: 0; font-size: 1.25rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
    .header .op-type { font-size: 0.75rem; background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-weight: 600; }
    
    .content { padding: 30px; }
    .section-title { font-size: 0.875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
    
    .log-entry { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.85rem; padding: 12px 15px; border-radius: 8px; margin-bottom: 10px; background: #ffffff; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .log-entry.success { border-left: 4px solid #10b981; background: #f0fdf4; }
    .log-entry.error { border-left: 4px solid #ef4444; background: #fffafb; }
    
    .badge { padding: 2px 10px; border-radius: 6px; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-error { background: #fee2e2; color: #991b1b; }
    .badge-fine { background: #fef3c7; color: #92400e; margin-left: 5px; }
    
    .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 30px; }
    .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .summary-item label { display: block; font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
    .summary-item span { font-weight: 600; color: #334155; }

    .btn-download { display: inline-flex; align-items: center; justify-content: center; background: #059669; color: white; padding: 16px 32px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.3); border: none; cursor: pointer; }
    .btn-download:hover { background: #047857; transform: translateY(-1px); box-shadow: 0 12px 20px -3px rgba(5, 150, 105, 0.4); }
    
    .footer-nav { margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; text-align: center; }
    .footer-nav a { color: #64748b; text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: color 0.2s; }
    .footer-nav a:hover { color: #059669; text-decoration: underline; }
</style>
";

echo "<div class='container'>";
echo "<div class='header'>
        <h1>Gestione Circolazione</h1>
       
      </div>";
echo "<div class='content'>";

// 2. INCLUSIONE DIPENDENZE
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ottaviodipisa\StackMasters\Models\Loan;
use Ottaviodipisa\StackMasters\Helpers\RicevutaRestituzionePDF;

// 3. RECUPERO DATI
$returnsData = $_POST['returns'] ?? [];

echo "<div class='summary-card'>";
echo "<div class='section-title'>Parametri di Ricezione</div>";
echo "<div class='summary-grid'>";
echo "<div class='summary-item'><label>Stato Sessione</label><span>Attiva</span></div>";
echo "<div class='summary-item'><label>Esemplari Dichiarati</label><span>" . count($returnsData) . " unità</span></div>";
echo "</div>";
echo "</div>";

if (empty($returnsData)) {
    die("<div class='log-entry error'><span>Errore: Nessun dato pervenuto dall'interfaccia operativa.</span> <span class='badge badge-error'>BLOCCATO</span></div></div></div>");
}

try {
    // 4. CONNESSIONE DB VIA SINGLETON
    $db = Database::getInstance()->getConnection();
    $loanModel = new Loan();

    $successi = [];
    $utenteDatiPDF = null;

    echo "<div class='section-title'>Registro Operazioni di Rientro</div>";

    // 5. CICLO DI REGISTRAZIONE RESTITUZIONI
    foreach ($returnsData as $jsonData) {
        $item = json_decode($jsonData, true);
        $idInventario = (int)$item['id'];
        $condizione = $item['cond'];
        $commento = $item['note'];

        try {
            // 5.1 Identificazione Utente associato al prestito attivo
            $stmtU = $db->prepare("
                SELECT u.* FROM utenti u 
                JOIN prestiti p ON u.id_utente = p.id_utente 
                WHERE p.id_inventario = ? AND p.data_restituzione IS NULL
            ");
            $stmtU->execute([$idInventario]);
            $utente = $stmtU->fetch(PDO::FETCH_ASSOC);

            if (!$utente) {
                throw new Exception("Prestito attivo non rilevato per la copia selezionata.");
            }

            // Salvataggio anagrafica per l'intestazione PDF
            $utenteDatiPDF = $utente;

            // 5.2 Esecuzione logica di business (Multe, Danni, Coda Prenotazioni)
            $res = $loanModel->registraRestituzione($idInventario, $condizione, $commento);

            // 5.3 Recupero Titolo del volume
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

            // Visualizzazione Log Successo
            echo "<div class='log-entry success'>
                    <span>Copia #$idInventario - " . ($titolo ? htmlspecialchars(substr($titolo, 0, 40)) . "..." : "Asset Identificato") . "</span>
                    <div style='display:flex; align-items:center;'>
                        " . ((isset($res['multa_generata']) && $res['multa_generata'] > 0) ? "<span class='badge badge-fine'>Sanzione: " . number_format($res['multa_generata'], 2) . " €</span>" : "") . "
                        <span class='badge badge-success'>RIENTRATO</span>
                    </div>
                  </div>";

        } catch (Exception $e) {
            echo "<div class='log-entry error'>
                    <span>Copia #$idInventario - Elaborazione interrotta</span>
                    <span class='badge badge-error'>" . htmlspecialchars($e->getMessage()) . "</span>
                  </div>";
        }
    }

    // 6. CONTROLLO RISULTATI FINALI
    if (empty($successi)) {
        echo "<br><div class='log-entry error' style='justify-content: center; font-weight: 700;'>ERRORE: Nessuna procedura di rientro è stata portata a termine.</div>";
        echo "<div class='footer-nav'><a href='returns.php'>Torna alla registrazione</a></div>";
        echo "</div></div>";
        exit;
    }

    // 7. GENERAZIONE RICEVUTA PDF
    echo "<br><div class='section-title'>Archiviazione Documentale</div>";
    $datiPDF = [
        'utente' => $utenteDatiPDF,
        'libri' => $successi,
        'data_operazione' => date('d/m/Y H:i')
    ];

    $pdfFileName = RicevutaRestituzionePDF::genera($datiPDF);

    if ($pdfFileName) {
        echo "<div class='log-entry success' style='justify-content: center; font-weight: 600;'>Documentazione di scarico archiviata correttamente.</div>";

        // BOX CONCLUSIVO
        echo "<div style='text-align:center; margin-top: 40px; padding-top: 30px; border-top: 1px dashed #e2e8f0;'>";
        echo "<h2 style='color: #1e293b; margin-bottom: 25px; font-weight: 700; font-size: 1.5rem;'>Ciclo Operativo Concluso</h2>";
        echo "<a href='../../public/assets/docs/$pdfFileName' target='_blank' class='btn-download'>SCARICA RICEVUTA RIENTRO PDF</a>";
        echo "<div class='footer-nav'><a href='returns.php'>Registra nuovi rientri</a></div>";
        echo "</div>";
    } else {
        echo "<div class='log-entry error'>Avviso: Transazioni registrate, ma generazione PDF fallita.</div>";
        echo "<div class='footer-nav'><a href='returns.php'>Torna alla lista</a></div>";
    }

} catch (Exception $e) {
    echo "<div class='log-entry error' style='margin-top:20px;'>
            <strong>ECCEZIONE DI SISTEMA:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
    echo "<div class='footer-nav'><a href='returns.php'>Inizializza nuova sessione</a></div>";
}

echo "</div>"; // fine content
echo "</div>"; // fine container
echo "</body>";

require_once __DIR__ . '/../../src/Views/layout/footer.php';