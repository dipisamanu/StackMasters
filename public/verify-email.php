<?php

require_once '../src/config/database.php';
require_once '../src/config/session.php';

$db = getDB();
$message = '';
$success = false;

// Prendi token dalla URL
$token = $_GET['token'] ?? '';

if (empty($token) || strlen($token) !== 32) {
    $message = "Link di verifica non valido.";
} else {
    try {
        // Cerca utente con questo token
        $stmt = $db->prepare("
            SELECT id_utente, nome, email, email_verificata, scadenza_verifica 
            FROM utenti 
            WHERE token = ? 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $message = "Link di verifica non valido o già utilizzato.";
        } elseif ($user['email_verificata']) {
            $message = "Questo account è già stato verificato! Puoi effettuare il login.";
            $success = true;
        } elseif (strtotime($user['scadenza_verifica']) < time()) {
            $message = "Il link di verifica è scaduto. Richiedi un nuovo link dalla pagina di login.";
        } else {
            // Tutto OK - Verifica l'account
            $stmtUpdate = $db->prepare("
                UPDATE utenti 
                SET email_verificata = TRUE, 
                    token = NULL, 
                    scadenza_verifica = NULL 
                WHERE id_utente = ?
            ");
            $stmtUpdate->execute([$user['id_utente']]);

            $message = "Email verificata con successo! Ora puoi effettuare il login.";
            $success = true;

            // Log dell'evento
            $stmtLog = $db->prepare("
                INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address)
                VALUES (?, 'VERIFICA_EMAIL', ?, INET_ATON(?))
            ");
            $stmtLog->execute([
                $user['id_utente'],
                "Email verificata per utente: " . $user['email'],
                $_SERVER['REMOTE_ADDR']
            ]);
        }

    } catch (Exception $e) {
        error_log("Errore verifica email: " . $e->getMessage());
        $message = "Errore durante la verifica. Contatta l'amministratore.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica Email - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #bd5555 0%, #cc3a3a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .verify-container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
        }

        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: #bf2121;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(191, 33, 33, 0.3);
        }

        .btn:hover {
            background: #931b1b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(191, 33, 33, 0.4);
        }

        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
<div class="verify-container">
    <div class="icon <?= $success ? 'success' : 'error' ?>">
        <?= $success ? '✔' : '✗' ?>
    </div>

    <h1><?= $success ? 'Verifica Completata!' : 'Verifica Fallita' ?></h1>

    <p><?= htmlspecialchars($message) ?></p>

    <?php if ($success): ?>
        <a href="login.php" class="btn">Vai al Login</a>
    <?php else: ?>
        <a href="register.php" class="btn">Torna alla Registrazione</a>
    <?php endif; ?>

    <div class="footer">
        Biblioteca ITIS Rossi - Sistema Gestionale
    </div>
</div>
</body>
</html>