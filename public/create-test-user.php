<?php
/**
 * Script per creare un utente di test
 * File: public/create-test-user.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../src/config/database.php';
require_once '../src/config/session.php';

$db = getDB();
$results = [];

// Dati test utente
$testEmail = 'studente@test.it';
$testPassword = 'Password123!';
$testNome = 'Test';
$testCognome = 'Utente';
$testCF = 'TSTSTU0001A01H50'; // Codice fiscale esattamente 16 caratteri
$testDataNascita = '2006-01-01';
$testSesso = 'M';
$testComune = 'Milano';

try {
    // 1. Verifica se utente esiste già
    $stmt = $db->prepare("SELECT id_utente FROM Utenti WHERE email = ?");
    $stmt->execute([$testEmail]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        $results[] = [
            'status' => 'warning',
            'message' => "Utente con email '$testEmail' esiste già (ID: {$existingUser['id_utente']})"
        ];
    } else {
        // 2. Crea nuovo utente
        $hashedPassword = password_hash($testPassword, PASSWORD_BCRYPT);

        // Assicurati che il CF sia esattamente 16 caratteri e senza spazi
        $testCF = trim($testCF);
        if (strlen($testCF) !== 16) {
            $results[] = [
                'status' => 'error',
                'message' => "❌ Errore: CF deve essere esattamente 16 caratteri, hai " . strlen($testCF) . " caratteri"
            ];
        } else {
            $stmt = $db->prepare("
                INSERT INTO Utenti (cf, nome, cognome, email, password, data_nascita, sesso, comune_nascita, email_verificata, consenso_privacy)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute([$testCF, $testNome, $testCognome, $testEmail, $hashedPassword, $testDataNascita, $testSesso, $testComune]);
            $userId = $db->lastInsertId();

            $results[] = [
                'status' => 'success',
                'message' => "✅ Utente creato con successo (ID: $userId)"
            ];

            // 3. Assegna ruolo di Studente
            $stmtRole = $db->prepare("
                SELECT id_ruolo FROM Ruoli WHERE nome = 'Studente' LIMIT 1
            ");
            $stmtRole->execute();
            $role = $stmtRole->fetch(PDO::FETCH_ASSOC);

            if ($role) {
                $stmtAssign = $db->prepare("
                    INSERT INTO Utenti_Ruoli (id_utente, id_ruolo)
                    VALUES (?, ?)
                ");
                $stmtAssign->execute([$userId, $role['id_ruolo']]);

                $results[] = [
                    'status' => 'success',
                    'message' => "✅ Ruolo 'Studente' assegnato"
                ];
            }
        }
    }

    // 4. Mostra credenziali di test
    $results[] = [
        'status' => 'info',
        'message' => "<strong>Credenziali di Test:</strong><br>
                      CF: <code>$testCF</code><br>
                      Email: <code>$testEmail</code><br>
                      Password: <code>$testPassword</code>"
    ];

} catch (Exception $e) {
    error_log("Errore creazione utente test: " . $e->getMessage());
    $results[] = [
        'status' => 'error',
        'message' => "❌ Errore: " . $e->getMessage()
    ];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Utente Test - Biblioteca ITIS Rossi</title>
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
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
        }

        .result {
            background: white;
            padding: 15px;
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

        .result.info {
            border-left-color: #17a2b8;
            background: #f0f8fb;
            color: #0c5460;
        }

        .result code {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-weight: 600;
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
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✅ Crea Utente di Test</h1>
        <p>Crea un account di test per verificare il login</p>
    </div>

    <?php foreach ($results as $result): ?>
        <div class="result <?= $result['status'] ?>">
            <?= $result['message'] ?>
        </div>
    <?php endforeach; ?>

    <div class="actions">
        <a href="login.php" class="btn">🔐 Vai al Login</a>
        <a href="test-database.php" class="btn btn-secondary">🗄️ Test Database</a>
    </div>
</div>
</body>
</html>

