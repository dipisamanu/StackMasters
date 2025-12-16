<?php
/**
 * PAGINA DI TEST LOGIN
 * File: public/test-login.php
 *
 * Questa pagina ti aiuta a diagnosticare problemi di login
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../src/config/database.php';
require_once '../src/config/session.php';

$testResults = [];
$overallStatus = 'success';

// TEST 1: Connessione Database
try {
    $db = getDB();
    $testResults[] = [
        'test' => 'Connessione Database',
        'status' => 'success',
        'message' => 'Connessione al database riuscita'
    ];
} catch (Exception $e) {
    $testResults[] = [
        'test' => 'Connessione Database',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 2: Verifica Tabella Utenti
try {
    $stmt = $db->query("DESCRIBE Utenti");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredColumns = ['id_utente', 'nome', 'cognome', 'email', 'password', 'email_verificata'];
    $missingColumns = array_diff($requiredColumns, $columns);

    if (empty($missingColumns)) {
        $testResults[] = [
            'test' => 'Struttura Tabella Utenti',
            'status' => 'success',
            'message' => 'Tutte le colonne richieste presenti'
        ];
    } else {
        $testResults[] = [
            'test' => 'Struttura Tabella Utenti',
            'status' => 'error',
            'message' => 'Colonne mancanti: ' . implode(', ', $missingColumns)
        ];
        $overallStatus = 'error';
    }
} catch (Exception $e) {
    $testResults[] = [
        'test' => 'Struttura Tabella Utenti',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 3: Verifica Tabella Ruoli
try {
    $stmt = $db->query("SELECT COUNT(*) FROM Ruoli");
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $testResults[] = [
            'test' => 'Tabella Ruoli',
            'status' => 'success',
            'message' => "Trovati $count ruoli"
        ];
    } else {
        $testResults[] = [
            'test' => 'Tabella Ruoli',
            'status' => 'warning',
            'message' => 'Nessun ruolo trovato. Esegui install.sql'
        ];
        $overallStatus = 'warning';
    }
} catch (Exception $e) {
    $testResults[] = [
        'test' => 'Tabella Ruoli',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 4: Verifica Utenti di Test
try {
    $stmt = $db->query("SELECT id_utente, nome, cognome, email, email_verificata FROM Utenti LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        $message = "Trovati " . count($users) . " utenti:<br>";
        foreach ($users as $user) {
            $verified = $user['email_verificata'] ? '✅' : '❌';
            $message .= "- {$user['nome']} {$user['cognome']} ({$user['email']}) $verified<br>";
        }
        $testResults[] = [
            'test' => 'Utenti di Test',
            'status' => 'success',
            'message' => $message
        ];
    } else {
        $testResults[] = [
            'test' => 'Utenti di Test',
            'status' => 'warning',
            'message' => 'Nessun utente trovato. Crea un account di test.'
        ];
        $overallStatus = 'warning';
    }
} catch (Exception $e) {
    $testResults[] = [
        'test' => 'Utenti di Test',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 5: Verifica Sessione
try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $testResults[] = [
            'test' => 'Gestione Sessione',
            'status' => 'success',
            'message' => 'Sessione attiva. ID: ' . session_id()
        ];
    } else {
        $testResults[] = [
            'test' => 'Gestione Sessione',
            'status' => 'error',
            'message' => 'Sessione non attiva'
        ];
        $overallStatus = 'error';
    }
} catch (Exception $e) {
    $testResults[] = [
        'test' => 'Gestione Sessione',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 6: Verifica File di Log
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

if (is_writable($logDir)) {
    $testResults[] = [
        'test' => 'Directory Log',
        'status' => 'success',
        'message' => 'Directory log scrivibile: ' . $logDir
    ];
} else {
    $testResults[] = [
        'test' => 'Directory Log',
        'status' => 'warning',
        'message' => 'Directory log non scrivibile. I log potrebbero non funzionare.'
    ];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Sistema Login - Biblioteca ITIS Rossi</title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .overall-status {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 10px;
        }

        .overall-status.success {
            background: #d4edda;
            color: #155724;
        }

        .overall-status.warning {
            background: #fff3cd;
            color: #856404;
        }

        .overall-status.error {
            background: #f8d7da;
            color: #721c24;
        }

        .test-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #ddd;
        }

        .test-card.success {
            border-left-color: #28a745;
        }

        .test-card.warning {
            border-left-color: #ffc107;
        }

        .test-card.error {
            border-left-color: #dc3545;
        }

        .test-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .test-icon {
            font-size: 24px;
            margin-right: 10px;
        }

        .test-name {
            font-weight: 600;
            font-size: 18px;
            color: #333;
        }

        .test-message {
            color: #666;
            line-height: 1.6;
        }

        .actions {
            text-align: center;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #bf2121;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 0 10px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #931b1b;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box h3 {
            color: #0c5460;
            margin-bottom: 10px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #0c5460;
        }

        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔍 Test Sistema Login</h1>
        <p style="color: #666; margin-top: 10px;">Diagnostica e Verifica Configurazione</p>
        <div class="overall-status <?= $overallStatus ?>">
            <?php
            if ($overallStatus === 'success') echo '✅ Tutti i test superati';
            if ($overallStatus === 'warning') echo '⚠️ Alcuni avvisi presenti';
            if ($overallStatus === 'error') echo '❌ Errori critici rilevati';
            ?>
        </div>
    </div>

    <?php foreach ($testResults as $result): ?>
        <div class="test-card <?= $result['status'] ?>">
            <div class="test-header">
                <div class="test-icon">
                    <?php
                    if ($result['status'] === 'success') echo '✅';
                    if ($result['status'] === 'warning') echo '⚠️';
                    if ($result['status'] === 'error') echo '❌';
                    ?>
                </div>
                <div class="test-name"><?= htmlspecialchars($result['test']) ?></div>
            </div>
            <div class="test-message"><?= $result['message'] ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($overallStatus === 'error'): ?>
        <div class="info-box">
            <h3>📋 Azioni Raccomandate</h3>
            <ul>
                <li>Verifica che il database sia attivo e accessibile</li>
                <li>Esegui lo script <code>install.sql</code> per creare/aggiornare le tabelle</li>
                <li>Controlla i permessi della directory <code>/logs</code></li>
                <li>Verifica le credenziali in <code>database.php</code></li>
            </ul>
        </div>
    <?php endif; ?>

    <div class="actions">
        <a href="login.php" class="btn">Vai al Login</a>
        <a href="register.php" class="btn btn-secondary">Vai alla Registrazione</a>
        <a href="test-login.php" class="btn btn-secondary">🔄 Ricarica Test</a>
    </div>
</div>
</body>
</html>