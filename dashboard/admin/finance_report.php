<?php
/**
 * Vista: Report Contabile per la Segreteria (Epic 10.5)
 * File: dashboard/admin/finance_report.php
 */
session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /StackMasters/public/login.php');
    exit;
}

$mainRole = $_SESSION['main_role'] ?? 'Studente';
if ($mainRole !== 'Admin') {
    http_response_code(403);
    die("Accesso negato.");
}

// Dati mock di esempio se non passati dal controller
if (!isset($data)) {
    $data = [
        'report' => [
            ['data' => date('Y-m-d'), 'operazione' => 5, 'totale' => 15.50],
            ['data' => date('Y-m-d', strtotime('-1 day')), 'operazione' => 8, 'totale' => 24.00],
            ['data' => date('Y-m-d', strtotime('-2 day')), 'operazione' => 3, 'totale' => 7.50]
        ],
        'start' => date('Y-m-01'),
        'end' => date('Y-m-d')
    ];
}

$report = $data['report'] ?? [];
$startDate = $data['start'] ?? date('Y-m-01');
$endDate = $data['end'] ?? date('Y-m-d');

$grandTotal = 0;
foreach ($report as $row) {
    $grandTotal += $row['totale'] ?? $row['totale_incassato'] ?? 0;
}
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #666;
        }

        .text-danger {
            color: #bf2121;
        }

        .mt-40 {
            margin-top: 40px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 26px;
            color: #333;
        }

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
            font-size: 14px;
        }

        .btn:hover {
            background: #931b1b;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .card-red {
            border-left: 4px solid #bf2121;
        }

        .card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-box .label {
            font-size: 11px;
            color: #999;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .stat-box .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .stat-box .value.total {
            color: #28a745;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            text-align: left;
            padding: 15px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid #eee;
            background: #fdfdfd;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f5f7fa;
            font-size: 15px;
        }

        tr:hover {
            background: #fcfcfc;
        }

        .amount-cell {
            font-weight: bold;
            text-align: right;
            color: #28a745;
        }

        .table-footer {
            background: #f9f9f9;
            font-weight: bold;
        }

        .table-footer td {
            padding: 20px;
            font-size: 16px;
        }

        .total-amount {
            font-size: 20px;
            color: #bf2121;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .filter-form input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
        }

        .print-footer {
            margin-top: 60px;
            display: none;
        }

        .signature-box {
            text-align: center;
            width: 250px;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 8px;
        }

        .signature-label {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
                padding: 0;
                font-size: 12pt;
            }

            .container {
                max-width: 100%;
                margin: 0;
            }

            .card {
                box-shadow: none;
                border: 1px solid #eee;
                padding: 0;
                margin-bottom: 20px;
            }

            .stat-box {
                box-shadow: none;
                border: 1px solid #ccc;
            }

            /* Mostra il footer firme solo in stampa */
            .print-footer {
                display: flex !important;
                justify-content: space-between;
            }

            th {
                background-color: #eee !important;
                -webkit-print-color-adjust: exact;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header no-print">
        <h1><i class="fas fa-chart-line"></i> Report Incassi Segreteria</h1>
        <div class="user-info">
            <a href="../librarian/finance.php" class="btn btn-secondary" style="margin-right: 10px;"><i class="fas fa-arrow-left"></i> Torna a
                Gestione</a>
            <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> Stampa Report</button>
        </div>
    </div>

    <!-- Filtri -->
    <div class="card card-red no-print">
        <h3>Filtra Periodo Contabile</h3>
        <form action="" method="GET" class="filter-form">
            <div class="filter-group">
                <label>Da:</label>
                <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="filter-group">
                <label>A:</label>
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
            <div class="label">Transazioni Totali</div>
            <div class="value"><?= count($report) ?> Giorni</div>
        </div>
        <div class="stat-box" style="border-bottom: 4px solid #28a745;">
            <div class="label">Totale Incassato</div>
            <div class="value total">€ <?= number_format($grandTotal, 2) ?></div>
        </div>
    </div>

    <!-- Tabella Dati -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h2 class="text-danger" style="font-size: 20px;">Dettaglio Incassi Giornalieri</h2>
            <p class="text-muted" style="font-size: 12px;">Generato il: <?= date('d/m/Y H:i') ?></p>
        </div>

        <table>
            <thead>
            <tr>
                <th>Data Operazione</th>
                <th>Movimenti</th>
                <th class="text-right">Importo Giornaliero</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($report)): ?>
                <tr>
                    <td colspan="3" class="text-center text-muted" style="padding: 50px; font-style: italic;">
                        Nessun incasso registrato per l'intervallo di date selezionato.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($report as $row): ?>
                    <tr>
                        <td><strong><?= date('d/m/Y', strtotime($row['data'] ?? $row['data_operazione'])) ?></strong>
                        </td>
                        <td class="text-muted"><?= $row['operazione'] ?? $row['num_transazioni'] ?> pagamenti
                            registrati
                        </td>
                        <td class="amount-cell">
                            € <?= number_format($row['totale'] ?? $row['totale_incassato'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot>
            <tr class="table-footer">
                <td colspan="2" class="text-right">TOTALE DEL PERIODO</td>
                <td class="text-right total-amount">
                    € <?= number_format($grandTotal, 2) ?>
                </td>
            </tr>
            </tfoot>
        </table>

        <!-- Firma e Validazione (Visibile solo in stampa grazie al CSS) -->
        <div class="print-footer" id="print-footer">
            <div class="signature-box">
                <p class="text-muted" style="font-size: 12px;">Documento prodotto da StackMasters LMS</p>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <p class="signature-label">Firma Responsabile Segreteria</p>
            </div>
        </div>
    </div>
</div>

<footer class="no-print text-center text-muted mt-40" style="font-size: 12px; padding-bottom: 30px;">
    &copy; <?= date('Y') ?> Biblioteca Scolastica ITIS Rossi - Sistema Gestione Crediti
</footer>

</body>
</html>