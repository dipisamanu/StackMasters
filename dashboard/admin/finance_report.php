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

    // Calculate grand total from fetched data
    foreach ($report as $row) {
        $grandTotal += $row['totale'];
    }

} catch (Exception $e) {
    error_log("Errore nel recupero del report finanziario: " . $e->getMessage());
    // Fallback to empty report on error
    $report = [];
    $grandTotal = 0;
}


$nomeCompleto = Session::getNomeCompleto() ?? 'Amministratore'; // Use Session::getNomeCompleto()

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Contabile - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
            color: #333;
        }
        .container { max-width: 1100px; margin: 0 auto; }

        /* Header simile alla Dashboard */
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 26px; color: #333; }

        .btn {
            background: #bf2121;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover { background: #931b1b; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }

        /* Card System */
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .card-red { border-left: 4px solid #bf2121; }
        .card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-box .label { font-size: 11px; color: #999; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .stat-box .value { font-size: 24px; font-weight: bold; color: #333; }
        .stat-box .value.total { color: #28a745; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px; color: #666; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #eee; background: #fdfdfd; }
        td { padding: 15px; border-bottom: 1px solid #f5f7fa; font-size: 15px; }
        tr:hover { background: #fcfcfc; }
        .amount-cell { font-weight: bold; text-align: right; color: #28a745; }

        /* Filters */
        .filter-form { display: flex; gap: 15px; align-items: flex-end; }
        .filter-form input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; }

        /* Print Settings */
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .container { max-width: 100%; }
            .card { box-shadow: none; border: 1px solid #eee; }
            .stat-box { box-shadow: none; border: 1px solid #eee; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header no-print">
        <h1>📊 Report Incassi Segreteria</h1>
        <div class="user-info">
            <a href="index.php" class="btn btn-secondary" style="margin-right: 10px;">⬅ Torna alla Dashboard</a>
            <button onclick="window.print()" class="btn">🖨️ Stampa Report</button>
        </div>
    </div>

    <!-- Filtri -->
    <div class="card card-red no-print">
        <h3>Filtra Periodo Contabile</h3>
        <form action="" method="GET" class="filter-form">
            <div>
                <p style="font-size: 12px; margin-bottom: 5px; font-weight: bold;">Da:</p>
                <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div>
                <p style="font-size: 12px; margin-bottom: 5px; font-weight: bold;">A:</p>
                <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <button type="submit" class="btn" style="height: 42px;">Applica Filtri</button>
        </form>
    </div>

    <!-- Riepilogo Rapido -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="label">Periodo di Riferimento</div>
            <div class="value" style="font-size: 16px;">
                <?= date('d/m/Y', strtotime($startDate)) ?> - <?= date('d/m/Y', strtotime($endDate)) ?>
            </div>
        </div>
        <div class="stat-box">
            <div class="label">Giorni con Transazioni</div>
            <div class="value"><?= count($report) ?></div>
        </div>
        <div class="stat-box" style="border-bottom: 4px solid #28a745;">
            <div class="label">Totale Incassato</div>
            <div class="value total">€ <?= number_format($grandTotal, 2) ?></div>
        </div>
    </div>

    <!-- Tabella Dati -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h2 style="font-size: 20px; color: #bf2121;">Dettaglio Incassi Giornalieri</h2>
            <p style="font-size: 12px; color: #999;">Generato il: <?= date('d/m/Y H:i') ?></p>
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
                    <td colspan="3" style="text-align: center; padding: 50px; color: #999; font-style: italic;">
                        Nessun incasso registrato per l'intervallo di date selezionato.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($report as $row): ?>
                    <tr>
                        <td><strong><?= date('d/m/Y', strtotime($row['data'])) ?></strong></td>
                        <td style="color: #666;"><?= $row['operazione'] ?> pagamenti registrati</td>
                        <td class="amount-cell">€ <?= number_format($row['totale'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot>
            <tr style="background: #f9f9f9; font-weight: bold;">
                <td colspan="2" style="text-align: right; padding: 20px; font-size: 16px;">TOTALE DEL PERIODO</td>
                <td style="text-align: right; padding: 20px; font-size: 20px; color: #bf2121;">
                    € <?= number_format($grandTotal, 2) ?>
                </td>
            </tr>
            </tfoot>
        </table>

        <!-- Firma e Validazione (Solo Stampa) -->
        <div style="margin-top: 60px; display: none;" class="print-only" id="print-footer">
            <style>@media print { .print-only { display: flex !important; justify-content: space-between; } }</style>
            <div style="text-align: center; width: 250px;">
                <p style="font-size: 12px; color: #666;">Documento prodotto da StackMasters LMS</p>
            </div>
            <div style="text-align: center; width: 250px;">
                <div style="border-bottom: 1px solid #333; margin-bottom: 8px;"></div>
                <p style="font-size: 12px; font-weight: bold; text-transform: uppercase;">Firma Responsabile Segreteria</p>
            </div>
        </div>
    </div>
</div>

<footer class="no-print" style="text-align: center; margin-top: 40px; color: #aaa; font-size: 12px; padding-bottom: 30px;">
    &copy; <?= date('Y') ?> Biblioteca Scolastica ITIS Rossi - Sistema Gestione Crediti
</footer>

</body>
</html>