<?php
/**
 * Dashboard Studente
 * File: dashboard/student/index.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();
$nomeCompleto = Session::getNomeCompleto();
$email = Session::getEmail();

// Recupera i prestiti attivi dell'utente
try {
    $stmt = $db->prepare("
        SELECT 
            p.id_prestito,
            l.titolo,
            p.data_prestito,
            p.scadenza_prestito,
            p.data_restituzione,
            DATEDIFF(p.scadenza_prestito, CURDATE()) as giorni_rimanenti
        FROM Prestiti p
        INNER JOIN Inventari i ON p.id_inventario = i.id_inventario
        INNER JOIN Libri l ON i.id_libro = l.id_libro
        WHERE p.id_utente = ? AND p.data_restituzione IS NULL
        ORDER BY p.scadenza_prestito ASC
    ");
    $stmt->execute([$userId]);
    $prestiti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Errore nel recuperare prestiti: " . $e->getMessage());
    $prestiti = [];
}

// Calcola statistiche
$totalePrestiti = count($prestiti);
$pratitiScaduti = count(array_filter($prestiti, function($p) { return $p['giorni_rimanenti'] < 0; }));
$pratitiInScadenza = count(array_filter($prestiti, function($p) { return $p['giorni_rimanenti'] >= 0 && $p['giorni_rimanenti'] <= 3; }));

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Mia Dashboard - Biblioteca ITIS Rossi</title>
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

        .header-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .header-right a {
            padding: 10px 20px;
            text-decoration: none;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }

        .header-right a:first-child {
            background: #bf2121;
            color: white;
        }

        .header-right a:first-child:hover {
            background: #931b1b;
            transform: translateY(-2px);
        }

        .header-right a:last-child {
            background: #dc3545;
            color: white;
        }

        .header-right a:last-child:hover {
            background: #c82333;
            transform: translateY(-2px);
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

        .prestiti-list {
            display: grid;
            gap: 15px;
            margin-bottom: 40px;
        }

        .prestito-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .prestito-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .prestito-card.scaduto {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .prestito-card.in-scadenza {
            border-left-color: #ffc107;
            background: #fffbf0;
        }

        .prestito-card.ok {
            border-left-color: #28a745;
        }

        .prestito-info h4 {
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .prestito-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .prestito-status {
            text-align: right;
            padding-left: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .status-badge.scaduto {
            background: #dc3545;
            color: white;
        }

        .status-badge.in-scadenza {
            background: #ffc107;
            color: #333;
        }

        .status-badge.ok {
            background: #28a745;
            color: white;
        }

        .scadenza-date {
            font-size: 13px;
            color: #666;
        }

        .empty-state {
            background: white;
            padding: 60px 20px;
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
            padding: 8px 15px;
            font-size: 13px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header-right {
                margin-top: 20px;
            }

            .prestito-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .prestito-status {
                text-align: left;
                padding-left: 0;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
<!-- DEBUG: Pagina caricata, userId: <?= $userId ?> -->
<div class="container">
    <!-- Header -->
    <div class="header">
        <div>
            <h1><i class="fas fa-home" style="color: #bf2121; margin-right: 10px;"></i>Benvenuto, <?= htmlspecialchars($nomeCompleto ?? 'Utente') ?></h1>
            <p>La tua dashboard della Biblioteca ITIS Rossi</p>
        </div>
        <div class="header-right">
            <a href="profile.php" style="background: #bf2121; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;" onmouseover="this.style.background='#931b1b'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='#bf2121'; this.style.transform='translateY(0)';">
                <i class="fas fa-user-circle" style="font-size: 18px;"></i>
                <strong>PROFILO</strong>
            </a>
            <a href="../../public/logout.php" style="background: #dc3545; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;" onmouseover="this.style.background='#c82333'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='#dc3545'; this.style.transform='translateY(0)';">
                <i class="fas fa-sign-out-alt" style="font-size: 18px;"></i>
                <strong>LOGOUT</strong>
            </a>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="stats">
        <div class="stat-card">
            <h3><i class="fas fa-book"></i> Prestiti Attivi</h3>
            <div class="number"><?= $totalePrestiti ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-exclamation-circle" style="color: #ffc107;"></i> In Scadenza</h3>
            <div class="number" style="color: #ffc107;"><?= $pratitiInScadenza ?></div>
        </div>
        <div class="stat-card">
            <h3><i class="fas fa-times-circle" style="color: #dc3545;"></i> Scaduti</h3>
            <div class="number" style="color: #dc3545;"><?= $pratitiScaduti ?></div>
        </div>
    </div>

    <!-- Prestiti -->
    <div class="section-title"><i class="fas fa-list"></i> I Miei Prestiti</div>

    <?php if (empty($prestiti)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <p>Non hai prestiti attivi al momento. Buona lettura! 📚</p>
            <div class="actions">
                <a href="profile.php" class="btn">Visualizza Profilo</a>
            </div>
        </div>
    <?php else: ?>
        <div class="prestiti-list">
            <?php foreach ($prestiti as $prestito):
                $giorni = (int)$prestito['giorni_rimanenti'];
                $scaduto = $giorni < 0;
                $in_scadenza = $giorni >= 0 && $giorni <= 3;
                $class = $scaduto ? 'scaduto' : ($in_scadenza ? 'in-scadenza' : 'ok');
                $badge_class = $scaduto ? 'scaduto' : ($in_scadenza ? 'in-scadenza' : 'ok');
            ?>
                <div class="prestito-card <?= $class ?>">
                    <div class="prestito-info">
                        <h4><i class="fas fa-book"></i> <?= htmlspecialchars($prestito['titolo']) ?></h4>
                        <p><strong>Autore:</strong> <?= htmlspecialchars($prestito['autore']) ?></p>
                        <p><strong>Data Prestito:</strong> <?= date('d/m/Y', strtotime($prestito['data_prestito'])) ?></p>
                        <p><strong>Rinnovi:</strong> <?= $prestito['rinnovi'] ?>/1</p>
                    </div>
                    <div class="prestito-status">
                        <div class="status-badge <?= $badge_class ?>">
                            <?php if ($scaduto): ?>
                                ❌ SCADUTO DA <?= abs($giorni) ?> GG
                            <?php elseif ($in_scadenza): ?>
                                ⚠️ SCADE TRA <?= $giorni ?> GG
                            <?php else: ?>
                                ✅ OK
                            <?php endif; ?>
                        </div>
                        <p class="scadenza-date">Scadenza: <strong><?= date('d/m/Y', strtotime($prestito['scadenza_prestito'])) ?></strong></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Footer Actions -->
    <div style="margin-top: 40px; padding: 20px; background: white; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <a href="profile.php" class="btn" style="margin-right: 10px;">
            <i class="fas fa-user"></i> Gestisci Profilo
        </a>
        <a href="../../public/logout.php" class="btn" style="background: #6c757d;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
</body>
</html>

