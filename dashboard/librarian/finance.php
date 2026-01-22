<?php
/**
 * Vista: Gestione Finanziaria Admin
 * File: dashboard/admin/finance.php
 */

// Dati da passare al controller
$user = $data['user'] ?? null;
$fines = $data['fines'] ?? [];
$discount = $data['discount'] ?? 0;
$debtors = $data['debtors'] ?? [];
$msg = $_GET['msg'] ?? '';
$status = $_GET['status'] ?? '';
$nomeCompleto = ($_SESSION['nome'] ?? 'Admin') . ' ' . ($_SESSION['cognome'] ?? '');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Finanziaria - StackMasters</title>
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
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
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
            font-size: 28px;
            color: #333;
        }

        .user-info {
            text-align: right;
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
            transition: 0.2s;
        }

        .btn:hover {
            background: #931b1b;
        }

        .btn-green {
            background: #28a745;
        }

        .btn-green:hover {
            background: #218838;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 25px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            position: relative;
        }

        .card-red-border {
            border-left: 5px solid #bf2121;
        }

        .card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            font-family: inherit;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #eee;
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .amount {
            font-weight: bold;
            color: #bf2121;
        }

        .loyalty-badge {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeeba;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .debtor-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .debtor-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-sack-dollar"></i> Gestione Finanziaria</h1>
        <div class="user-info">
            <p>Admin: <strong><?= htmlspecialchars($nomeCompleto) ?></strong></p>
            <div style="margin-top: 10px;">
                <a href="../admin/index.php" class="btn" style="background: #333;"><i class="fas fa-home"></i> Dashboard</a>
                <a href="../admin/finance_report.php" class="btn" style="background: #6c757d;"><i class="fas fa-chart-bar"></i> Report Segreteria</a>
                <a href="/StackMasters/public/logout.php" class="btn" style="background: #dc3545;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="card" style="background: #d4edda; color: #155724; border-left: 5px solid #28a745; padding: 15px;">
            <i class="fas fa-check-circle"></i> <?= str_replace('_', ' ', htmlspecialchars($msg)) ?>
        </div>
    <?php endif; ?>

    <div class="main-grid">
        <div class="content-area">

            <!-- Ricerca Utente -->
            <div class="card card-red-border">
                <h3><i class="fas fa-search"></i> Ricerca Rapida Utente</h3>
                <form action="" method="GET" style="display: flex; gap: 10px;">
                    <input type="number" name="user_id" class="form-control"
                           placeholder="Inserisci ID Utente (es. 101)..." value="<?= $_GET['user_id'] ?? '' ?>"
                           required>
                    <button type="submit" class="btn" style="white-space: nowrap;">Cerca Utente</button>
                </form>
            </div>

            <?php if ($user): ?>
                <!-- Dettaglio Portafoglio -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                        <div>
                            <h2 style="font-size: 26px;"><?= $user['nome'] ?> <?= $user['cognome'] ?></h2>
                            <p style="color: #888;"><?= $user['email'] ?></p>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 11px; font-weight: bold; color: #aaa; text-transform: uppercase;">Debito Totale Pendente</span>
                            <div style="font-size: 42px; font-weight: bold; color: #bf2121;"><?= number_format($user['debito_totale'], 2) ?>
                                €
                            </div>
                        </div>
                    </div>

                    <?php if ($discount > 0): ?>
                        <div class="loyalty-badge">
                            <span style="font-size: 24px;"><i class="fas fa-star"></i></span>
                            <div>
                                <strong>Algoritmo Affidabilità (Epic 10.4):</strong> Utente meritevole rilevato.
                                Applicare lo sconto del <strong><?= $discount * 100 ?>%</strong> sulla quietanza finale.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                        <!-- Modulo Pagamento -->
                        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
                            <h3>Registra Pagamento</h3>
                            <p style="font-size: 13px; color: #777; margin-bottom: 15px;">Il saldo estingue tutte le
                                pendenze e genera una ricevuta liberatoria PDF.</p>
                            <form action="../../src/Controllers/FineController.php?action=pay" method="POST">
                                <input type="hidden" name="user_id" value="<?= $user['id_utente'] ?>">
                                <button type="submit" class="btn btn-green" style="width: 100%; padding: 15px;">
                                    <i class="fas fa-file-invoice-dollar"></i> Paga e Genera PDF
                                </button>
                            </form>
                        </div>

                        <!-- Modulo Addebito -->
                        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
                            <h3>Nuovo Addebito Manuale</h3>
                            <form action="../../src/Controllers/FineController.php?action=charge" method="POST">
                                <input type="hidden" name="user_id" value="<?= $user['id_utente'] ?>">
                                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                    <input type="number" step="0.01" name="amount" class="form-control"
                                           placeholder="Importo €" required style="margin-bottom:0">
                                    <select name="causa" class="form-control" style="margin-bottom:0">
                                        <option value="DANNI">Danni</option>
                                        <option value="RITARDO">Ritardo</option>
                                    </select>
                                </div>
                                <textarea name="commento" class="form-control"
                                          placeholder="Note aggiuntive (causale specifica)..." rows="1"></textarea>
                                <button type="submit" class="btn" style="width: 100%; background: #444;">Aggiungi al
                                    Conto
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Lista Singole Multe -->
                    <h3 style="margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Storico Pendenze
                        Attive</h3>
                    <table>
                        <thead>
                        <tr>
                            <th>Data Emissione</th>
                            <th>Motivazione</th>
                            <th>Note Bibliotecario</th>
                            <th style="text-align: right;">Importo</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($fines)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color: #aaa; padding: 30px;">Nessuna multa
                                    pendente trovata.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($fines as $fine): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($fine['data_creazione'])) ?></td>
                                <td><span style="font-weight: 700; color: #444;"><?= $fine['causa'] ?></span></td>
                                <td style="color: #666; italic"><?= htmlspecialchars($fine['commento']) ?></td>
                                <td style="text-align: right;" class="amount"><?= number_format($fine['importo'], 2) ?>
                                    €
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Debitori -->
        <div class="sidebar">
            <div class="card card-red-border">
                <h3><i class="fas fa-exclamation-triangle"></i> Debitori Critici</h3>
                <p style="font-size: 11px; color: #999; margin-bottom: 15px;">Utenti con i debiti più alti.</p>
                <?php foreach ($debtors as $d): ?>
                    <div class="debtor-item">
                        <div>
                            <p style="font-weight: bold; font-size: 14px;"><?= $d['nome'] ?> <?= substr($d['cognome'], 0, 1) ?>
                                .</p>
                            <p style="font-size: 10px; color: #bbb;">ID: <?= $d['id_utente'] ?></p>
                        </div>
                        <span class="amount" style="font-size: 16px;"><?= number_format($d['debito'], 2) ?> €</span>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top: 20px; text-align: center;">
                    <a href="../admin/finance_report.php"
                       style="font-size: 12px; color: #bf2121; font-weight: bold; text-decoration: none;">Vedi tutti i
                        report &rarr;</a>
                </div>
            </div>

            <div class="card" style="background: #333; color: white; text-align: center;">
                <p style="font-size: 13px;">Il sistema aggiorna automaticamente le multe notturne alle ore 00:00.</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>