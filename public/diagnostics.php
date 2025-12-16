<?php
/**
 * File di Diagnostica Completa del Sistema Login
 * File: public/diagnostics.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../src/config/database.php';
require_once '../src/config/session.php';

$results = [];
$overallStatus = 'success';

// TEST 1: Connessione Database
try {
    $db = getDB();
    $results[] = [
        'test' => 'Connessione Database',
        'status' => 'success',
        'message' => 'Connessione al database riuscita ✅'
    ];
} catch (Exception $e) {
    $results[] = [
        'test' => 'Connessione Database',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
    exit;
}

// TEST 2: Verifica Tabella Utenti
try {
    $stmt = $db->query("SHOW COLUMNS FROM Utenti");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $requiredColumns = ['id_utente', 'nome', 'cognome', 'email', 'password', 'email_verificata'];
    $missingColumns = array_diff($requiredColumns, $columns);

    if (empty($missingColumns)) {
        $results[] = [
            'test' => 'Struttura Tabella Utenti',
            'status' => 'success',
            'message' => 'Tutte le colonne richieste presenti ✅'
        ];
    } else {
        $results[] = [
            'test' => 'Struttura Tabella Utenti',
            'status' => 'error',
            'message' => 'Colonne mancanti: ' . implode(', ', $missingColumns)
        ];
        $overallStatus = 'error';
    }
} catch (Exception $e) {
    $results[] = [
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
        $stmtRuoli = $db->query("SELECT id_ruolo, nome, priorita FROM Ruoli");
        $ruoli = $stmtRuoli->fetchAll(PDO::FETCH_ASSOC);
        $ruoliList = implode(', ', array_map(fn($r) => $r['nome'], $ruoli));

        $results[] = [
            'test' => 'Tabella Ruoli',
            'status' => 'success',
            'message' => "Trovati $count ruoli: $ruoliList ✅"
        ];
    } else {
        $results[] = [
            'test' => 'Tabella Ruoli',
            'status' => 'error',
            'message' => 'Nessun ruolo trovato. Esegui install.sql'
        ];
        $overallStatus = 'error';
    }
} catch (Exception $e) {
    $results[] = [
        'test' => 'Tabella Ruoli',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 4: Verifica Tabella Utenti_Ruoli
try {
    $stmt = $db->query("SHOW COLUMNS FROM Utenti_Ruoli");
    $results[] = [
        'test' => 'Tabella Utenti_Ruoli',
        'status' => 'success',
        'message' => 'Tabella esiste ✅'
    ];
} catch (Exception $e) {
    $results[] = [
        'test' => 'Tabella Utenti_Ruoli',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 5: Conta utenti di test
try {
    $stmt = $db->query("SELECT COUNT(*) FROM Utenti WHERE email_verificata = 1");
    $countUtentiVerificati = $stmt->fetchColumn();

    $stmt2 = $db->query("SELECT id_utente, nome, cognome, email FROM Utenti WHERE email_verificata = 1 LIMIT 5");
    $utenti = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if (count($utenti) > 0) {
        $message = "Trovati $countUtentiVerificati utenti verificati:<br>";
        foreach ($utenti as $user) {
            $message .= "• {$user['nome']} {$user['cognome']} ({$user['email']})<br>";
        }
        $results[] = [
            'test' => 'Utenti Verificati',
            'status' => 'success',
            'message' => $message
        ];
    } else {
        $results[] = [
            'test' => 'Utenti Verificati',
            'status' => 'warning',
            'message' => '⚠️ Nessun utente verificato trovato. Crea un account di test.'
        ];
        $overallStatus = 'warning';
    }
} catch (Exception $e) {
    $results[] = [
        'test' => 'Utenti Verificati',
        'status' => 'error',
        'message' => 'ERRORE: ' . $e->getMessage()
    ];
    $overallStatus = 'error';
}

// TEST 6: Verifica file di configurazione
if (file_exists('../src/config/database.php')) {
    $results[] = [
        'test' => 'File database.php',
        'status' => 'success',
        'message' => 'File presente ✅'
    ];
} else {
    $results[] = [
        'test' => 'File database.php',
        'status' => 'error',
        'message' => 'File NON TROVATO ❌'
    ];
    $overallStatus = 'error';
}

// TEST 7: Verifica file di sessione
if (file_exists('../src/config/session.php')) {
    $results[] = [
        'test' => 'File session.php',
        'status' => 'success',
        'message' => 'File presente ✅'
    ];
} else {
    $results[] = [
        'test' => 'File session.php',
        'status' => 'error',
        'message' => 'File NON TROVATO ❌'
    ];
    $overallStatus = 'error';
}

// TEST 8: Verifica Dashboard
$dashboards = [
    'admin' => '../dashboard/admin/index.php',
    'librarian' => '../dashboard/librarian/index.php',
    'student' => '../dashboard/student/index.php'
];

foreach ($dashboards as $role => $path) {
    if (file_exists($path)) {
        $results[] = [
            'test' => "Dashboard $role",
            'status' => 'success',
            'message' => "File presente ✅"
        ];
    } else {
        $results[] = [
            'test' => "Dashboard $role",
            'status' => 'error',
            'message' => "File MANCANTE ❌ ($path)"
        ];
        $overallStatus = 'error';
    }
}

// TEST 9: Sessione attiva
if (session_status() === PHP_SESSION_ACTIVE) {
    $results[] = [
        'test' => 'Gestione Sessione',
        'status' => 'success',
        'message' => 'Sessione attiva ✅'
    ];
} else {
    $results[] = [
        'test' => 'Gestione Sessione',
        'status' => 'error',
        'message' => 'Sessione non attiva ❌'
    ];
    $overallStatus = 'error';
}

// TEST 10: Verifica Directory Log
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

if (is_writable($logDir)) {
    $results[] = [
        'test' => 'Directory Log',
        'status' => 'success',
        'message' => 'Directory log scrivibile ✅'
    ];
} else {
    $results[] = [
        'test' => 'Directory Log',
        'status' => 'warning',
        'message' => 'Directory log non scrivibile (potrebbero non registrarsi log) ⚠️'
    ];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostica Sistema Login - Biblioteca ITIS Rossi</title>
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
            padding: 12px 25px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 15px;
            font-size: 16px;
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

        .test-name {
            font-weight: 600;
            font-size: 16px;
            color: #333;
            margin-bottom: 8px;
        }

        .test-message {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
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
            color: #0c5460;
        }

        .info-box h3 {
            margin-bottom: 10px;
        }

        .info-box ul {
            margin-left: 20px;
        }

        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔍 Diagnostica Sistema Login</h1>
        <p>Verifica dello stato di configurazione e database</p>
        <div class="overall-status <?= $overallStatus ?>">
            <?php
            if ($overallStatus === 'success') echo '✅ Sistema Operativo';
            if ($overallStatus === 'warning') echo '⚠️ Funzionamento Limitato';
            if ($overallStatus === 'error') echo '❌ Errori Critici';
            ?>
        </div>
    </div>

    <?php foreach ($results as $result): ?>
        <div class="test-card <?= $result['status'] ?>">
            <div class="test-name"><?= htmlspecialchars($result['test']) ?></div>
            <div class="test-message"><?= $result['message'] ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($overallStatus === 'success'): ?>
        <div class="info-box">
            <h3>✅ Sistema Pronto!</h3>
            <p>Il sistema è pronto per il login. Procedi ai seguenti passi:</p>
            <ul>
                <li>Vai al <strong>Login</strong> con le credenziali di test</li>
                <li>Se non hai un account di test, creane uno</li>
                <li>Controlla che la dashboard si carichi correttamente</li>
            </ul>
        </div>
    <?php elseif ($overallStatus === 'warning'): ?>
        <div class="info-box">
            <h3>⚠️ Avvisi Presenti</h3>
            <p>Il sistema funziona ma con alcuni avvisi. Considera di:</p>
            <ul>
                <li>Verificare i file di log</li>
                <li>Controllare i permessi delle directory</li>
                <li>Eseguire lo script install.sql se necessario</li>
            </ul>
        </div>
    <?php else: ?>
        <div class="info-box">
            <h3>❌ Errori Critici</h3>
            <p>Occorre risolvere i problemi elencati sopra:</p>
            <ul>
                <li>Verifica la connessione al database</li>
                <li>Esegui lo script <code>install.sql</code> per creare le tabelle</li>
                <li>Controlla le credenziali in <code>database.php</code></li>
                <li>Crea i file dashboard mancanti</li>
            </ul>
        </div>
    <?php endif; ?>

    <div class="actions">
        <a href="login.php" class="btn">🔐 Vai al Login</a>
        <a href="create-test-user.php" class="btn btn-secondary">👤 Crea Utente Test</a>
        <a href="diagnostics.php" class="btn btn-secondary">🔄 Ricarica Diagnostica</a>
    </div>
</div>
</body>
</html>

