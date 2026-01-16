<?php
/**
 * registra-prestito.php - ELABORAZIONE SEQUENZIALE FINALE
 * Percorso: dashboard/librarian/registra-prestito.php
 */

// 1. MONITORAGGIO ERRORI
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "
<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Finalizzazione Operazioni | StackMasters</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f1f5f9; 
            color: #0f172a; 
            margin: 0;
            padding: 60px 20px;
            font-size: 18px; 
        }

        .main-card { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: #ffffff; 
            border-radius: 30px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); 
            overflow: hidden; 
        }

        .top-banner { 
            background: #bf2121; 
            padding: 60px 50px; 
            color: white; 
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 8px solid #9b1b1b;
        }

        .content-area { padding: 50px; }

        .summary-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: #f8fafc;
            padding: 35px;
            border-radius: 24px;
            border: 2px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-card span { font-size: 0.9rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-card strong { font-size: 1.6rem; color: #bf2121; font-weight: 800; }

        .operation-row {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .operation-row:hover { 
            border-color: #bf2121; 
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .badge-pill {
            padding: 10px 20px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .btn-main {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            background: #bf2121;
            color: white;
            padding: 25px 60px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 1.4rem;
            text-decoration: none;
            transition: all 0.4s ease;
            box-shadow: 0 20px 25px -5px rgba(191, 33, 33, 0.3);
        }

        .btn-main:hover {
            background: #9b1b1b;
            transform: translateY(-5px);
            box-shadow: 0 25px 30px rgba(191, 33, 33, 0.4);
        }

        .section-label {
            font-size: 1rem;
            font-weight: 900;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
    </style>
</head>
<body>

<div class='main-card'>
    <div class='top-banner'>
        <div>
            <h1 class='text-5xl font-black uppercase tracking-tight'>Output Sessione</h1>
            <p class='text-xl opacity-90 font-semibold mt-2'>Controllo Circolazione Volumi - ITIS Rossi</p>
        </div>
        <i class='fas fa-sync-alt text-7xl opacity-20 animate-spin-slow'></i>
    </div>

    <div class='content-area'>";

// 2. LOGICA BACKEND
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ottaviodipisa\StackMasters\Models\Loan;
use Ottaviodipisa\StackMasters\Helpers\RicevutaPrestitoPDF;

$userCode = $_POST['user_barcode'] ?? '';
$bookIds  = $_POST['book_ids'] ?? [];
$conditions = $_POST['conditions'] ?? [];

echo "
    <div class='section-label'><i class='fas fa-sliders-h'></i> Parametri di Ingresso</div>
    <div class='summary-header'>
        <div class='stat-card'>
            <span>Codice Utente Rilevato</span>
            <strong>" . htmlspecialchars($userCode) . "</strong>
        </div>
        <div class='stat-card'>
            <span>Unità in Registro</span>
            <strong>" . count($bookIds) . " Libri</strong>
        </div>
    </div>";

if (empty($userCode) || empty($bookIds)) {
    die("<div class='p-10 bg-red-100 text-red-800 rounded-3xl font-black text-center border-4 border-red-200 text-2xl'><i class='fas fa-exclamation-circle mb-4 text-5xl block'></i> DATI SESSIONE MANCANTI</div></div></div></body></html>");
}

try {
    $db = Database::getInstance()->getConnection();
    $loanModel = new Loan();

    $stmtU = $db->prepare("SELECT id_utente, nome, cognome, email, cf FROM utenti WHERE cf = :cf OR id_utente = :id LIMIT 1");
    $stmtU->execute(['cf' => $userCode, 'id' => $userCode]);
    $utente = $stmtU->fetch(PDO::FETCH_ASSOC);

    if (!$utente) {
        die("<div class='p-10 bg-red-100 text-red-800 rounded-3xl font-black text-center border-4 border-red-200 text-2xl'><i class='fas fa-user-slash mb-4 text-5xl block'></i> UTENTE NON RICONOSCIUTO</div></div></div></body></html>");
    }

    $stmtMulte = $db->prepare("SELECT SUM(importo) as totale_multe FROM multe WHERE id_utente = :id_utente AND data_pagamento IS NULL");
    $stmtMulte->execute(['id_utente' => $utente['id_utente']]);
    $saldoMulte = $stmtMulte->fetch(PDO::FETCH_ASSOC);

    if ($saldoMulte && $saldoMulte['totale_multe'] > 0) {
        $importoFormattato = number_format($saldoMulte['totale_multe'], 2, ',', '.');
        die("<div class='p-10 bg-yellow-100 text-yellow-800 rounded-3xl font-black text-center border-4 border-yellow-200 text-2xl'><i class='fas fa-hand-paper mb-4 text-5xl block'></i> PRESTITO BLOCCATO<p class='text-lg font-medium mt-4'>L'utente ha un saldo multe non pagato di €{$importoFormattato}.<br>È necessario regolarizzare la posizione prima di procedere.</p></div><div class='flex justify-center py-16'><a href='new-loan.php' class='text-slate-400 hover:text-red-600 font-bold text-xl transition-all flex items-center gap-3'><i class='fas fa-arrow-left'></i> Nuova Scansione Rapida</a></div></div></div></body></html>");
    }

    echo "<div class='section-label'><i class='fas fa-user-shield'></i> Verifica Soggetto Abilitato</div><div class='bg-slate-900 text-white p-10 rounded-3xl mb-12 flex items-center justify-between border-b-8 border-emerald-500'><div><h2 class='text-4xl font-black mt-2'>" . strtoupper($utente['cognome']) . " " . strtoupper($utente['nome']) . "</h2><p class='text-slate-400 text-lg mt-1'>Codice Fiscale: <span class='font-mono'>" . $utente['cf'] . "</span></p></div><div class='text-right'><i class='fas fa-id-card-alt text-7xl opacity-40'></i></div></div>";

    echo "<div class='section-label'><i class='fas fa-list-check'></i> Dettaglio Operazioni Automatizzate</div>";
    $successi = [];

    foreach ($bookIds as $idInventario) {
        try {
            $condizioneUscita = $conditions[$idInventario] ?? 'BUONO';
            
            // Aggiorna la condizione nell'inventario PRIMA di registrare il prestito
            $stmtUpdateCond = $db->prepare("UPDATE inventari SET condizione = ? WHERE id_inventario = ?");
            $stmtUpdateCond->execute([$condizioneUscita, $idInventario]);

            $res = $loanModel->registraPrestito((int)$utente['id_utente'], (int)$idInventario);

            $stmtL = $db->prepare("SELECT l.titolo FROM libri l JOIN inventari i ON l.id_libro = i.id_libro WHERE i.id_inventario = ?");
            $stmtL->execute([$idInventario]);
            $infoLibro = $stmtL->fetch(PDO::FETCH_ASSOC);

            $successi[] = ['id_inventario' => $idInventario, 'titolo' => $infoLibro['titolo'] ?? 'Titolo non disponibile', 'scadenza' => $res['data_scadenza']];

            echo "<div class='operation-row'><div class='flex items-center gap-6'><div class='w-16 h-16 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center text-xl font-black border-2 border-emerald-200'>#$idInventario</div><div><span class='text-xs font-black text-slate-400 uppercase tracking-tighter'>Titolo Volume</span><div class='font-extrabold text-slate-800 text-xl'>" . htmlspecialchars($infoLibro['titolo']) . "</div></div></div><span class='badge-pill bg-emerald-600 text-white shadow-lg shadow-emerald-100'>Processato</span></div>";
        } catch (Exception $e) {
            $msgErrore = $e->getMessage();
            if (str_contains($msgErrore, 'riservata per il ritiro')) {
                preg_match('/ID:\s*(\d+)/', $msgErrore, $matches);
                if (isset($matches[1])) {
                    $stmtN = $db->prepare("SELECT nome, cognome FROM utenti WHERE id_utente = ?");
                    $stmtN->execute([$matches[1]]);
                    if ($uRes = $stmtN->fetch(PDO::FETCH_ASSOC)) $msgErrore = "Riservato per il ritiro di: <b class='text-red-900'>" . strtoupper($uRes['cognome']) . " " . strtoupper($uRes['nome']) . "</b>";
                }
            } elseif (str_contains($msgErrore, 'già in prestito')) {
                $stmtP = $db->prepare("SELECT u.nome, u.cognome FROM prestiti p JOIN utenti u ON p.id_utente = u.id_utente WHERE p.id_inventario = ? AND p.data_restituzione IS NULL LIMIT 1");
                $stmtP->execute([$idInventario]);
                if ($uPoss = $stmtP->fetch(PDO::FETCH_ASSOC)) $msgErrore = "Attualmente in possesso di: <b class='text-red-900'>" . strtoupper($uPoss['cognome']) . " " . strtoupper($uPoss['nome']) . "</b>";
            }
            echo "<div class='operation-row border-red-200 bg-red-50/50'><div class='flex items-center gap-6'><div class='w-16 h-16 bg-red-100 text-red-700 rounded-2xl flex items-center justify-center text-xl font-black border-2 border-red-200'><i class='fas fa-ban'></i></div><div><span class='text-xs font-black text-red-400 uppercase tracking-tighter'>Anomalia Rilevata</span><div class='font-bold text-red-800 text-lg'>Copia #$idInventario - " . $msgErrore . "</div></div></div><span class='badge-pill bg-red-600 text-white shadow-lg shadow-red-100'>Rifiutato</span></div>";
        }
    }

    if (!empty($successi)) {
        $pdfData = ['utente' => $utente, 'libri' => $successi, 'data_operazione' => date('d/m/Y H:i')];
        $pdfFileName = RicevutaPrestitoPDF::genera($pdfData);
        echo "<div class='mt-20 p-14 bg-emerald-600 rounded-[40px] text-center text-white shadow-2xl shadow-emerald-200 relative overflow-hidden'><i class='fas fa-check-double text-[12rem] absolute -bottom-10 -right-10 opacity-10'></i><i class='fas fa-cloud-arrow-down text-7xl mb-6'></i><h2 class='text-4xl font-black mb-4'>Operazione Finalizzata</h2><p class='text-emerald-100 text-xl mb-12 max-w-2xl mx-auto font-medium'>Il sistema ha aggiornato i database. La ricevuta digitale è pronta per l'archiviazione o la stampa.</p><a href='../../public/assets/docs/$pdfFileName' target='_blank' class='btn-main bg-white text-emerald-700'><i class='fas fa-file-pdf'></i> SCARICA DOCUMENTO RICEVUTA</a></div>";
    }

} catch (Exception $e) {
    echo "<div class='bg-red-700 text-white p-10 rounded-3xl font-black text-center'>ERRORE DI SISTEMA: " . $e->getMessage() . "</div>";
}

echo "<div class='flex justify-center py-16'><a href='new-loan.php' class='text-slate-400 hover:text-red-600 font-bold text-xl transition-all flex items-center gap-3'><i class='fas fa-arrow-left'></i> Nuova Scansione Rapida</a></div></div></div></body></html>";
require_once __DIR__ . '/../../src/Views/layout/footer.php';