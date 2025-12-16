<?php
/**
 * Dashboard Admin
 * File: dashboard/admin/index.php
 */

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

// Richiedi login e verifica ruolo
Session::requireLogin();
Session::requireRole('Admin');

$db = getDB();
$userId = Session::getUserId();
$nomeCompleto = Session::getNomeCompleto();

// Recupera statistiche generali
try {
    // Totale utenti
    $stmtUtenti = $db->query("SELECT COUNT(*) as totale FROM Utenti");
    $totalUtenti = $stmtUtenti->fetchColumn();

    // Totale libri
    $stmtLibri = $db->query("SELECT COUNT(*) as totale FROM Libri");
    $totalLibri = $stmtLibri->fetchColumn();

    // Prestiti attivi
    $stmtPrestiti = $db->query("SELECT COUNT(*) as totale FROM Prestiti WHERE data_restituzione IS NULL");
    $prestatiAttivi = $stmtPrestiti->fetchColumn();

    // Ultimi prestiti
    $stmtUltimiPrestiti = $db->query("
        SELECT p.id_prestito, l.titolo, u.nome, u.cognome, p.data_prestito
        FROM Prestiti p
        INNER JOIN Libri l ON p.id_libro = l.id_libro
        INNER JOIN Utenti u ON p.id_utente = u.id_utente
        ORDER BY p.data_prestito DESC
        LIMIT 5
    ");
    $ultimiPrestiti = $stmtUltimiPrestiti->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Errore nel recuperare statistiche: " . $e->getMessage());
    $totalUtenti = $totalLibri = $prestatiAttivi = 0;
    $ultimiPrestiti = [];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="../../public/assets/img/itisrossi.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
        }

        .header-right a {
            color: #bf2121;
            text-decoration: none;
            font-weight: 600;
            margin-left: 20px;
        }

        .header-right a:hover {
            color: #931b1b;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #bf2121;
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #bf2121;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #bf2121;
        }

        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        table th {
            padding: 15px;
            text-align: left;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            color: #333;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #bf2121;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #931b1b;
            transform: translateY(-2px);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .empty-state {
            background: white;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state p {
            color: #666;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header-right {
                margin-top: 20px;
            }

            table {
                font-size: 12px;
            }

            table th, table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <div>
            <h1><i class="fas fa-lock" style="color: #bf2121; margin-right: 10px;"></i>Pannello Admin</h1>
            <p>Benvenuto, <?= htmlspecialchars($nomeCompleto) ?></p>
        </div>
        <div class="header-right">
            <a href="../../public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="stats">
        <div class="stat-card">
            <h3><i class="fas fa-users"></i> Utenti Totali</h3>
            <div class="number"><?= $totalUtenti ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-book"></i> Libri Nel Catalogo</h3>
            <div class="number"><?= $totalLibri ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-hand-holding-heart"></i> Prestiti Attivi</h3>
            <div class="number"><?= $prestatiAttivi ?></div>
        </div>
    </div>

    <!-- Ultimi Prestiti -->
    <div class="section-title"><i class="fas fa-list"></i> Ultimi Prestiti</div>

    <?php if (empty($ultimiPrestiti)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Nessun prestito registrato</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID Prestito</th>
                    <th>Titolo Libro</th>
                    <th>Utente</th>
                    <th>Data Prestito</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimiPrestiti as $prestito): ?>
                    <tr>
                        <td>#<?= $prestito['id_prestito'] ?></td>
                        <td><?= htmlspecialchars($prestito['titolo']) ?></td>
                        <td><?= htmlspecialchars($prestito['nome'] . ' ' . $prestito['cognome']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($prestito['data_prestito'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Footer Actions -->
    <div style="margin-top: 40px; padding: 20px; background: white; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <a href="../../public/logout.php" class="btn btn-secondary">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
</body>
</html>

