<?php
/**
 * Dashboard Admin
 * File: dashboard/admin/index.php
 */

session_start();

require_once '../../src/config/database.php';

// Richiedi login
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /StackMasters/public/login.php');
    exit;
}

// Verifica ruolo admin
$mainRole = $_SESSION['main_role'] ?? 'Studente';
if ($mainRole !== 'Admin') {
    http_response_code(403);
    die("Accesso negato. Questa pagina è riservata agli amministratori.");
}

$db = getDB();
$userId = $_SESSION['user_id'] ?? null;
$nomeCompleto = ($_SESSION['nome'] ?? '') . ' ' . ($_SESSION['cognome'] ?? '');

if (!$userId) {
    header('Location: /StackMasters/public/login.php');
    exit;
}

// Recupera statistiche
try {
    // Totale utenti
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM utenti");
    $stmt->execute();
    $totalUtenti = $stmt->fetchColumn();

    // Totale libri
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM libri");
    $stmt->execute();
    $totalLibri = $stmt->fetchColumn();

    // Prestiti attivi
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM prestiti WHERE data_restituzione IS NULL");
    $stmt->execute();
    $presitiAttivi = $stmt->fetchColumn();

} catch (Exception $e) {
    error_log("Errore nel recuperare statistiche: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
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
            font-size: 28px;
        }

        .user-info {
            text-align: right;
        }

        .user-info p {
            color: #666;
            margin-bottom: 5px;
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
        }

        .btn:hover {
            background: #931b1b;
        }

        .stats-grid {
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
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .number {
            color: #bf2121;
            font-size: 36px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>👨‍💼 Dashboard Amministratore</h1>
        <div class="user-info">
            <p>Benvenuto, <strong><?= htmlspecialchars($nomeCompleto) ?></strong></p>
            <div style="margin-top: 10px;">
                <a href="../../Views/admin/profile.php" class="btn" style="margin-right: 10px;">👤 Profilo</a>
                <a href="/StackMasters/public/logout.php" class="btn" style="background: #dc3545;">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Utenti Registrati</h3>
            <div class="number"><?= $totalUtenti ?></div>
        </div>
        <div class="stat-card">
            <h3>Libri nel Catalogo</h3>
            <div class="number"><?= $totalLibri ?></div>
        </div>
        <div class="stat-card">
            <h3>Prestiti Attivi</h3>
            <div class="number"><?= $presitiAttivi ?></div>
        </div>
    </div>
</div>
</body>
</html>

