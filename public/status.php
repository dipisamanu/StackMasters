<?php
/**
 * Pagina di Diagnostica Sistema
 * File: public/status.php
 */

session_start();

require_once '../src/config/database.php';

$results = [];

// 1. Test Connessione Database
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM Utenti");
    $countUtenti = $stmt->fetchColumn();
    $results[] = ['status' => 'success', 'title' => '✅ Database Connesso', 'message' => "$countUtenti utenti nel sistema"];
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'title' => '❌ Database', 'message' => $e->getMessage()];
}

// 2. Test Tabelle
$expectedTables = ['Utenti', 'Libri', 'Prestiti', 'Ruoli', 'Generi', 'Autori', 'Inventari'];
try {
    $db = getDB();
    foreach ($expectedTables as $table) {
        $stmt = $db->prepare("SELECT 1 FROM $table LIMIT 1");
        $stmt->execute();
    }
    $results[] = ['status' => 'success', 'title' => '✅ Schema Database', 'message' => 'Tutte le tabelle presenti'];
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'title' => '❌ Schema Database', 'message' => $e->getMessage()];
}

// 3. Test Ruoli
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM Ruoli");
    $countRuoli = $stmt->fetchColumn();
    if ($countRuoli >= 4) {
        $results[] = ['status' => 'success', 'title' => '✅ Ruoli Configurati', 'message' => "$countRuoli ruoli trovati"];
    } else {
        $results[] = ['status' => 'warning', 'title' => '⚠️ Ruoli', 'message' => "Solo $countRuoli ruoli (attesi 4)"];
    }
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'title' => '❌ Ruoli', 'message' => $e->getMessage()];
}

// 4. Test Dati Seed
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM Libri");
    $countLibri = $stmt->fetchColumn();
    $results[] = ['status' => 'success', 'title' => '✅ Dati di Test', 'message' => "$countLibri libri nel catalogo"];
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'title' => '❌ Dati di Test', 'message' => $e->getMessage()];
}

// 5. Test Session
$sessionOk = isset($_SESSION);
$results[] = ['status' => $sessionOk ? 'success' : 'warning', 'title' => '✅ Session PHP', 'message' => $sessionOk ? 'Sessioni PHP attive' : 'Attenzione con le sessioni'];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Sistema - Biblioteca ITIS Rossi</title>
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
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .result {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #ddd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .result.success {
            border-left-color: #28a745;
            background: #f0f8f4;
            color: #155724;
        }

        .result.error {
            border-left-color: #dc3545;
            background: #fff5f5;
            color: #721c24;
        }

        .result.warning {
            border-left-color: #ffc107;
            background: #fffbf0;
            color: #856404;
        }

        .result-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .result-message {
            font-size: 14px;
        }

        .links {
            text-align: center;
            margin-top: 30px;
        }

        .links a {
            display: inline-block;
            padding: 12px 30px;
            background: #bf2121;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }

        .links a:hover {
            background: #931b1b;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔧 Stato Sistema</h1>
        <p>Verifica della configurazione</p>
    </div>

    <?php foreach ($results as $result): ?>
        <div class="result <?= $result['status'] ?>">
            <div class="result-title"><?= $result['title'] ?></div>
            <div class="result-message"><?= $result['message'] ?></div>
        </div>
    <?php endforeach; ?>

    <div class="links">
        <a href="/StackMasters/public/login.php">🔐 Accedi</a>
        <a href="/StackMasters/public/register.php">📝 Registrati</a>
    </div>
</div>
</body>
</html>

