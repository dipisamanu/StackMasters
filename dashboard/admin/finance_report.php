<?php
/**
 * Vista: Report Contabile per la Segreteria (Epic 10.5)
 * File: dashboard/admin/finance_report.php
 */
require_once '../../src/config/session.php'; // Include the Session class
require_once '../../src/config/database.php'; // Include the Database class

Session::requireRole('Admin'); // Use the Session class to enforce role

$db = Database::getInstance()->getConnection();

// Get start and end dates from GET request, or set defaults
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-d');

$report = [];
$grandTotal = 0;
$totalTransactions = 0;

try {
    // Query to fetch daily financial data
    $stmt = $db->prepare("
        SELECT
            DATE(data_pagamento) as data,
            COUNT(id_multa) as operazione,
            SUM(importo) as totale
        FROM multe
        WHERE data_pagamento IS NOT NULL
          AND DATE(data_pagamento) >= :start_date
          AND DATE(data_pagamento) <= :end_date
        GROUP BY DATE(data_pagamento)
        ORDER BY data ASC
    ");
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate grand total and total transactions from fetched data
    foreach ($report as $row) {
        $grandTotal += $row['totale'];
        $totalTransactions += $row['operazione'];
    }

} catch (Exception $e) {
    error_log("Errore nel recupero del report finanziario: " . $e->getMessage());
    // Fallback to empty report on error
    $report = [];
    $grandTotal = 0;
    $totalTransactions = 0;
}

$nomeCompleto = Session::getNomeCompleto() ?? 'Amministratore';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Contabile - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        :root {
            --primary-color: #bf2121;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-color: #1e293b;
            --muted-text-color: #64748b;
            --border-color: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            padding: 20px;
        }

        .container { max-width: 1200px; margin: 0 auto; }

        .header {
            background: var(--card-background);
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }
        .header h1 { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 12px; }

        .btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.1); }
        .btn-secondary { background: var(--secondary-color); }

        .card {
            background: var(--card-background);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }
        .card h3 {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted-text-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: var(--card-background);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .stat-box .icon { font-size: 24px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .stat-box .label { font-size: 12px; color: var(--muted-text-color); font-weight: 600; margin-bottom: 2px; }
        .stat-box .value { font-size: 22px; font-weight: 800; color: var(--text-color); }
        .stat-box .icon-period { background: #e0f2fe; color: #0ea5e9; }
        .stat-box .icon-trans { background: #fef3c7; color: #d97706; }
        .stat-box .icon-total { background: #dcfce7; color: var(--success-color); }
        .stat-box.total-card { border-top: 4px solid var(--success-color); }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px 15px; color: var(--muted-text-color); font-size: 11px; text-transform: uppercase; border-bottom: 2px solid var(--border-color); background: #f8fafc; font-weight: 700; letter-spacing: 0.05em; }
        td { padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        tbody tr:nth-child(even) { background-color: #f8fafc; }
        tbody tr:hover { background-color: #f1f5f9; }
        .amount-cell { font-weight: 700; text-align: right; color: var(--success-color); }
        tfoot td { font-weight: 800; font-size: 16px; }
        tfoot .total-amount { font-size: 20px; color: var(--primary-color); }

        .filter-form { display: flex; gap: 15px; align-items: flex-end; }
        .filter-group label { font-size: 12px; margin-bottom: 5px; font-weight: 600; color: var(--muted-text-color); }
        .filter-form input[type="date"] { padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; background: #f8fafc; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; font-size: 10pt; }
            .container { max-width: 100%; margin: 0; }
            .card, .stat-box, .header { box-shadow: none; border: 1px solid #ccc; border-radius: 0; }
            th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }
            tbody tr:nth-child(even) { background-color: #f8f8f8 !important; -webkit-print-color-adjust: exact; }
            .print-footer { display: flex !important; justify-content: space-between; margin-top: 60px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header no-print">
        <h1><i class="fas fa-file-invoice-dollar text-slate-400"></i> Report Contabile</h1>
        <div>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Torna alla Dashboard</a>
            <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> Stampa Report</button>
        </div>
    </div>

    <div class="card no-print">
        <h3>Filtra Periodo</h3>
        <form action="" method="GET" class="filter-form">
            <div class="filter-group">
                <label for="start">Data Inizio</label>
                <input type="date" id="start" name="start" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="filter-group">
                <label for="end">Data Fine</label>
                <input type="date" id="end" name="end" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <button type="submit" class="btn" style="height: 42px;">Applica Filtri</button>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="icon icon-period"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <div class="label">Periodo di Riferimento</div>
                <div class="value" style="font-size: 18px;"><?= date('d/m/y', strtotime($startDate)) ?> - <?= date('d/m/y', strtotime($endDate)) ?></div>
            </div>
        </div>
        <div class="stat-box">
            <div class="icon icon-trans"><i class="fas fa-exchange-alt"></i></div>
            <div>
                <div class="label">Pagamenti Registrati</div>
                <div class="value"><?= $totalTransactions ?></div>
            </div>
        </div>
        <div class="stat-box total-card">
            <div class="icon icon-total"><i class="fas fa-euro-sign"></i></div>
            <div>
                <div class="label">Totale Incassato</div>
                <div class="value" style="color: var(--success-color);"><?= number_format($grandTotal, 2) ?> €</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
            <h2 style="font-size: 20px; font-weight: 700; color: var(--primary-color);">Dettaglio Incassi Giornalieri</h2>
            <p style="font-size: 12px; color: var(--muted-text-color);">Generato il: <?= date('d/m/Y H:i') ?></p>
        </div>

        <table>
            <thead>
            <tr>
                <th>Data Operazione</th>
                <th>Movimenti</th>
                <th style="text-align: right;">Importo Giornaliero</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($report)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 50px; color: var(--muted-text-color); font-style: italic;">
                        Nessun incasso registrato per l'intervallo di date selezionato.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($report as $row): ?>
                    <tr>
                        <td><strong><?= date('d F Y', strtotime($row['data'])) ?></strong></td>
                        <td style="color: var(--muted-text-color);"><?= $row['operazione'] ?> pagamenti registrati</td>
                        <td class="amount-cell">+ <?= number_format($row['totale'], 2) ?> €</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot>
            <tr style="background: #f1f5f9; border-top: 2px solid var(--border-color);">
                <td colspan="2" style="text-align: right;">TOTALE DEL PERIODO</td>
                <td class="total-amount" style="text-align: right;color: var(--success-color)">
                    <?= number_format($grandTotal, 2) ?> €
                </td>
            </tr>
            </tfoot>
        </table>

        <div class="print-footer" style="display: none; margin-top: 80px; padding-top: 20px; border-top: 1px solid #ccc;">
            <div style="text-align: left;">
                <p style="font-size: 8pt; color: #999;">Documento generato da StackMasters LMS</p>
            </div>
            <div style="text-align: right; width: 250px;">
                <div style="border-bottom: 1px solid #333; margin: 40px 0 8px 0;"></div>
                <p style="font-size: 9pt; font-weight: bold;">Firma Responsabile Segreteria</p>
            </div>
        </div>
    </div>
</div>

<footer class="no-print" style="text-align: center; margin-top: 40px; color: #aaa; font-size: 12px; padding-bottom: 30px;">
    &copy; <?= date('Y') ?> Biblioteca Scolastica ITIS Rossi - Sistema Gestione Crediti
</footer>

</body>
</html>