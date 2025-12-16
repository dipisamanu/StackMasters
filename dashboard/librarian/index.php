<?php
/**
 * Dashboard Bibliotecario
 * File: dashboard/librarian/index.php
 */

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

// Richiedi login e verifica ruolo
Session::requireLogin();
if (!Session::isLibrarian()) {
    http_response_code(403);
    die("Accesso negato. Questa pagina è riservata ai bibliotecari.");
}

$db = getDB();
$userId = Session::getUserId();
$nomeCompleto = Session::getNomeCompleto();

// Recupera statistiche
try {
    // Prestiti da ricevere
    $stmtDaRicevere = $db->query("
        SELECT COUNT(*) as totale FROM Prestiti 
        WHERE data_restituzione IS NULL 
        AND scadenza_prestito < CURDATE()
    ");
    $daRicevere = $stmtDaRicevere->fetchColumn();

    // Prestiti attivi
    $stmtPrestiti = $db->query("SELECT COUNT(*) as totale FROM Prestiti WHERE data_restituzione IS NULL");
    $prestatiAttivi = $stmtPrestiti->fetchColumn();

    // Libri con pochi copie disponibili
    $stmtLibriPochi = $db->query("
        SELECT COUNT(*) as totale FROM Libri 
        WHERE copie_disponibili < 3
    ");
    $libroConPoche = $stmtLibriPochi->fetchColumn();

    // Prestiti da ricevere
    $stmtPrestatiScaduti = $db->prepare("
        SELECT p.id_prestito, l.titolo, u.nome, u.cognome, p.scadenza_prestito
        FROM Prestiti p
        INNER JOIN Libri l ON p.id_libro = l.id_libro
        INNER JOIN Utenti u ON p.id_utente = u.id_utente
        WHERE p.data_restituzione IS NULL AND p.scadenza_prestito < CURDATE()
        ORDER BY p.scadenza_prestito ASC
        LIMIT 10
    ");
    $stmtPrestatiScaduti->execute();
    $prestatiScaduti = $stmtPrestatiScaduti->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Errore nel recuperare statistiche: " . $e->getMessage());
    $daRicevere = $prestatiAttivi = $libroConPoche = 0;
    $prestatiScaduti = [];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bibliotecario - Biblioteca ITIS Rossi</title>
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
            margin-bottom: 30px;
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

        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #856404;
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
            <h1><i class="fas fa-book-reader" style="color: #bf2121; margin-right: 10px;"></i>Dashboard Bibliotecario</h1>
            <p>Benvenuto, <?= htmlspecialchars($nomeCompleto) ?></p>
        </div>
        <div class="header-right">
            <a href="../../public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="stats">
        <div class="stat-card">
            <h3><i class="fas fa-hand-holding-heart"></i> Prestiti Attivi</h3>
            <div class="number"><?= $prestatiAttivi ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Da Ricevere</h3>
            <div class="number" style="color: #dc3545;"><?= $daRicevere ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-warning" style="color: #ffc107;"></i> Libri Con Poche Copie</h3>
            <div class="number" style="color: #ffc107;"><?= $libroConPoche ?></div>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($daRicevere > 0): ?>
        <div class="alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Attenzione!</strong> Hai <?= $daRicevere ?> prestito/i scaduto/i da ricevere.
        </div>
    <?php endif; ?>

    <!-- Prestiti Scaduti -->
    <div class="section-title"><i class="fas fa-calendar-times"></i> Prestiti Scaduti Da Ricevere</div>

    <?php if (empty($prestatiScaduti)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>Nessun prestito scaduto in sospeso. Ottimo lavoro! ✅</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID Prestito</th>
                    <th>Titolo Libro</th>
                    <th>Utente</th>
                    <th>Scadenza</th>
                    <th>Giorni Scaduti</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prestatiScaduti as $prestito):
                    $giorni_scaduti = abs((strtotime($prestito['scadenza_prestito']) - time()) / 86400);
                ?>
                    <tr>
                        <td>#<?= $prestito['id_prestito'] ?></td>
                        <td><?= htmlspecialchars($prestito['titolo']) ?></td>
                        <td><?= htmlspecialchars($prestito['nome'] . ' ' . $prestito['cognome']) ?></td>
                        <td><?= date('d/m/Y', strtotime($prestito['scadenza_prestito'])) ?></td>
                        <td><strong style="color: #dc3545;">-<?= floor($giorni_scaduti) ?> GG</strong></td>
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

