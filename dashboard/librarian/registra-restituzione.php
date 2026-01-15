<?php
/**
 * registra-restituzione.php - ELABORAZIONE SEQUENZIALE RIENTRI
 * Percorso: dashboard/librarian/registra-restituzione.php
 */

// 1. MONITORAGGIO ERRORI (Solo per sviluppo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Stili CSS Professionali coordinati con il sistema
echo "
<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Output Rientro Asset | StackMasters</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8fafc; 
            color: #1e293b; 
            margin: 0;
            padding: 40px 20px;
        }

        .main-container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); 
            overflow: hidden; 
            border: 1px solid #e2e8f0;
        }

        .header-banner { 
            background: #059669; 
            padding: 40px; 
            color: white; 
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-banner h1 { 
            font-size: 1.5rem; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: 0.05em;
            margin: 0;
        }

        .content-body { padding: 40px; }

        .session-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 35px;
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
        }

        .summary-item label { 
            display: block; 
            font-size: 0.7rem; 
            font-weight: 800; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        
        .summary-item span { 
            font-weight: 700; 
            color: #334155; 
            font-size: 1rem;
        }

        .log-row {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .log-row.success { border-left: 4px solid #10b981; }
        .log-row.error { border-left: 4px solid #ef4444; background: #fffafb; }

        .badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }

        .btn-download {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: #059669;
            color: white;
            padding: 18px 40px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.3);
            width: 100%;
            margin-top: 20px;
        }

        .btn-download:hover {
            background: #047857;
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 15px;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class='main-container'>
    <div class='header-banner'>
        <div>
            <h1>Processo Restituzione</h1>
            <p class='text-xs opacity-80 font-medium mt-1 uppercase tracking-widest'>Validazione Asset e Reintegro Inventario</p>
        </div>
        <i class='fas fa-file-import text-3xl opacity-30'></i>
    </div>

    <div class='content-body'>";

// 2. INCLUSIONE DIPENDENZE
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ottaviodipisa\StackMasters\Models\Loan;
use Ottaviodipisa\StackMasters\Helpers\RicevutaRestituzionePDF;

$returnsData = $_POST['returns'] ?? [];

// Riepilogo Parametri
echo "
    <div class='section-title'>Parametri di Ricezione</div>
    <div class='session-summary'>
        <div class='summary-item'>
            <label>Stato Protocollo</label>
            <span>Operativo</span>
        </div>
        <div class='summary-item'>
            <label>Esemplari Rilevati</label>
            <span>" . count($returnsData) . " Unità</span>
        </div>
    </div>";

if (empty($returnsData)) {
    die("<div class='p-6 bg-red-50 text-red-700 rounded-xl font-bold text-center border border-red-100 text-sm uppercase tracking-widest'>
            Interruzione: Nessun dato pervenuto dal carrello di rientro.
         </div></div></div></body></html>");
}

try {
    $db = Database::getInstance()->getConnection();
    $loanModel = new Loan();

    $successi = [];
    $utenteDatiPDF = null;

    echo "<div class='section-title'>Registro Operazioni Asset</div>";

    // 5. CICLO DI REGISTRAZIONE RESTITUZIONI
    foreach ($returnsData as $jsonData) {
        $item = json_decode($jsonData, true);
        $idInventario = (int)$item['id'];
        $condizione = $item['cond'];
        $commento = $item['note'];

        try {
            // 5.1 Identificazione Utente (prima della chiusura del prestito)
            $stmtU = $db->prepare("
                SELECT u.* FROM utenti u 
                JOIN prestiti p ON u.id_utente = p.id_utente 
                WHERE p.id_inventario = ? AND p.data_restituzione IS NULL
            ");
            $stmtU->execute([$idInventario]);
            $utente = $stmtU->fetch(PDO::FETCH_ASSOC);

            if (!$utente) {
                throw new Exception("Prestito attivo non rilevato.");
            }

            $utenteDatiPDF = $utente;

            // 5.2 Esecuzione Business Logic
            $res = $loanModel->registraRestituzione($idInventario, $condizione, $commento);

            // 5.3 Recupero Titolo del volume
            $stmtL = $db->prepare("SELECT l.titolo FROM libri l JOIN inventari i ON l.id_libro = i.id_libro WHERE i.id_inventario = ?");
            $stmtL->execute([$idInventario]);
            $titolo = $stmtL->fetchColumn();

            $successi[] = [
                'id_inventario' => $idInventario,
                'titolo' => $titolo ?: "Asset #$idInventario",
                'condizione' => $condizione,
                'multa' => $res['multa_generata'] ?? 0
            ];

            echo "
            <div class='log-row success'>
                <div class='flex flex-col'>
                    <span class='text-[10px] font-extrabold text-slate-400 uppercase tracking-tighter'>Copia #$idInventario</span>
                    <span class='font-bold text-slate-700 text-sm'>" . htmlspecialchars(substr($titolo, 0, 45)) . "...</span>
                </div>
                <div class='flex items-center gap-3'>
                    " . ((isset($res['multa_generata']) && $res['multa_generata'] > 0) ? "<span class='badge badge-warning'>SANZIONE GENERATA</span>" : "") . "
                    <span class='badge badge-success'>RIENTRATO</span>
                </div>
            </div>";

        } catch (Exception $e) {
            echo "
            <div class='log-row error'>
                <div class='flex flex-col'>
                    <span class='text-[10px] font-extrabold text-red-400 uppercase tracking-tighter'>Copia #$idInventario</span>
                    <span class='font-bold text-red-800 text-sm'>" . htmlspecialchars($e->getMessage()) . "</span>
                </div>
                <span class='badge badge-error'>RIFIUTATO</span>
            </div>";
        }
    }

    // 6. AREA DOWNLOAD E RICEVUTA
    if (!empty($successi)) {
        $datiPDF = [
            'utente' => $utenteDatiPDF,
            'libri' => $successi,
            'data_operazione' => date('d/m/Y H:i')
        ];

        $pdfFileName = RicevutaRestituzionePDF::genera($datiRicevuta ?? $datiPDF);

        echo "
        <div style='margin-top: 40px; padding-top: 30px; border-top: 1px dashed #e2e8f0; text-align: center;'>
            <h2 class='text-xl font-bold text-slate-800 mb-2'>Ciclo di rientro completato</h2>
            <p class='text-slate-500 text-sm mb-6'>Le pendenze degli utenti e lo stato dell'inventario sono stati aggiornati correttamente.</p>
            
            <a href='../../public/assets/docs/$pdfFileName' target='_blank' class='btn-download'>
                <i class='fas fa-file-pdf'></i> SCARICA RICEVUTA PDF
            </a>
            
            <div class='mt-6'>
                <a href='returns.php' class='text-xs font-bold text-slate-400 hover:text-emerald-600 transition-colors uppercase tracking-widest'>
                    Inizia nuovo rientro &rarr;
                </a>
            </div>
        </div>";
    }

} catch (Exception $e) {
    echo "<div class='p-6 bg-red-100 text-red-700 rounded-xl font-bold text-center border-2 border-red-200 mt-4'>
            ERRORE CRITICO DI SISTEMA: " . $e->getMessage() . "
          </div>";
}

echo "</div>"; // fine content-body
echo "</div>"; // fine main-container
echo "</body></html>";

require_once __DIR__ . '/../../src/Views/layout/footer.php';