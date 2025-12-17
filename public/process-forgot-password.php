<?php
/**
 * Process Forgot Password - Backend Recupero Password
 * File: public/process-forgot-password.php
 *
 * EPIC 2.5 - Feature: Flow recupero password (Step 2)
 * Genera token sicuro e invia email con link di reset
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

require_once '../src/config/database.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-password.php');
    exit;
}

try {
    $db = getDB();

    $email = trim($_POST['email'] ?? '');

    // Validazione base
    if (empty($email)) {
        $_SESSION['forgot_error'] = 'Inserisci un indirizzo email';
        header('Location: forgot-password.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['forgot_error'] = 'Indirizzo email non valido';
        header('Location: forgot-password.php');
        exit;
    }

    // Cerca l'utente (case-insensitive)
    $stmt = $db->prepare("
        SELECT id_utente, nome, cognome, email, email_verificata
        FROM Utenti 
        WHERE LOWER(email) = LOWER(?)
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Per motivi di sicurezza, mostriamo sempre lo stesso messaggio
    // anche se l'email non esiste (previene email enumeration)
    if (!$user) {
        error_log("FORGOT PASSWORD: Email non trovata - " . $email);

        // Messaggio generico per sicurezza
        $_SESSION['forgot_success'] = 'Se l\'email esiste nel sistema, riceverai le istruzioni per il reset della password.';
        header('Location: forgot-password.php');
        exit;
    }

    // Verifica che l'account sia verificato
    if (!$user['email_verificata']) {
        error_log("FORGOT PASSWORD: Account non verificato - " . $email);

        $_SESSION['forgot_error'] = 'Account non ancora verificato. Controlla la tua email per il link di verifica.';
        header('Location: forgot-password.php');
        exit;
    }

    // Rate limiting: controlla ultimi tentativi (max 3 ogni 15 minuti)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM Logs_Audit
        WHERE id_utente = ?
        AND azione = 'PASSWORD_RESET_REQUEST'
        AND timestamp > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$user['id_utente']]);
    $recentAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($recentAttempts >= 3) {
        error_log("FORGOT PASSWORD: Rate limit exceeded - " . $email);

        $_SESSION['forgot_error'] = 'Troppi tentativi di reset. Riprova tra 15 minuti.';
        header('Location: forgot-password.php');
        exit;
    }

    // Genera token sicuro (32 caratteri random)
    if (function_exists('random_bytes')) {
        $token = bin2hex(random_bytes(16)); // 16 bytes = 32 caratteri hex
    } else if (function_exists('openssl_random_pseudo_bytes')) {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
    } else {
        // Fallback per server molto vecchi
        $token = bin2hex(substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 16)), 0, 16));
    }

    // Hash del token con MD5 (32 caratteri, perfetto per VARCHAR(32))
    $tokenHash = md5($token);

    // Scadenza: 24 ore da ora
    $expiryTime = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Aggiorna database con token e scadenza
    $stmt = $db->prepare("
        UPDATE Utenti 
        SET 
            token = ?,
            scadenza_verifica = ?
        WHERE id_utente = ?
    ");
    $stmt->execute([$tokenHash, $expiryTime, $user['id_utente']]);

    // Log della richiesta nel sistema di audit
    try {
        $db->prepare("
            INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address) 
            VALUES (?, 'PASSWORD_RESET_REQUEST', ?, INET_ATON(?))
        ")->execute([
            $user['id_utente'],
            "Richiesta reset password",
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Errore log audit: " . $e->getMessage());
    }

    // Costruisci link di reset (usa token NON hashato nell'URL)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $resetLink = $protocol . '://' . $host . '/StackMasters/public/reset-password.php?token=' . $token;

    // Prepara email
    $subject = "Reset Password - Biblioteca ITIS Rossi";
    $nomeCompleto = htmlspecialchars($user['nome'] . ' ' . $user['cognome']);
    $expiryFormatted = date('d/m/Y H:i', strtotime($expiryTime));

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #bf2121; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; padding: 15px 30px; background: #bf2121; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .info { background: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Reset Password</h1>
            </div>
            <div class='content'>
                <p>Ciao <strong>$nomeCompleto</strong>,</p>
                
                <p>Abbiamo ricevuto una richiesta per reimpostare la password del tuo account Biblioteca ITIS Rossi.</p>
                
                <p>Clicca sul pulsante qui sotto per impostare una nuova password:</p>
                
                <div style='text-align: center;'>
                    <a href='$resetLink' class='button'>Reimposta Password</a>
                </div>
                
                <div class='info'>
                    <strong>ℹ️ Link alternativo:</strong><br>
                    Se il pulsante non funziona, copia e incolla questo link nel tuo browser:<br>
                    <code style='word-break: break-all;'>$resetLink</code>
                </div>
                
                <div class='warning'>
                    <strong>⏰ Attenzione:</strong><br>
                    Questo link scadrà il <strong>$expiryFormatted</strong> (24 ore).<br>
                    Dopo la scadenza, dovrai richiedere un nuovo link.
                </div>
                
                <div class='warning' style='background: #f8d7da; border-left-color: #dc3545;'>
                    <strong>⚠️ Non hai richiesto tu il reset?</strong><br>
                    Se non sei stato tu a richiedere il reset della password, ignora questa email. 
                    Il tuo account è al sicuro e la password attuale rimarrà invariata.
                </div>
                
                <p><strong>Dettagli richiesta:</strong></p>
                <ul>
                    <li>Data: " . date('d/m/Y H:i:s') . "</li>
                    <li>IP: " . htmlspecialchars($_SERVER['REMOTE_ADDR']) . "</li>
                </ul>
                
                <p>Cordiali saluti,<br>
                <strong>Staff Biblioteca ITIS Rossi</strong></p>
            </div>
            <div class='footer'>
                <p>Questa è una email automatica, non rispondere a questo messaggio.</p>
                <p>© " . date('Y') . " Biblioteca ITIS Rossi - Tutti i diritti riservati</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Headers email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@bibliotecaitisrossi.it" . "\r\n";
    $headers .= "Reply-To: noreply@bibliotecaitisrossi.it" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1" . "\r\n";

    // Invia email
    $emailSent = @mail($user['email'], $subject, $message, $headers);

    if (!$emailSent) {
        error_log("ERRORE INVIO EMAIL RESET PASSWORD: " . $user['email']);

        // Anche se l'invio fallisce, non lo diciamo all'utente per sicurezza
        // ma loggiamo l'errore
    }

    // Log successo (indipendentemente dall'invio email per sicurezza)
    error_log("RESET PASSWORD REQUEST SUCCESS: " . $email);

    // Messaggio generico di successo (sempre uguale per sicurezza)
    $_SESSION['forgot_success'] = 'Se l\'email esiste nel sistema, riceverai le istruzioni per il reset della password entro pochi minuti.';
    header('Location: forgot-password.php');
    exit;

} catch (PDOException $e) {
    error_log("ERRORE PDO FORGOT PASSWORD: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // In sviluppo mostra errore dettagliato, in produzione messaggio generico
    if (ini_get('display_errors')) {
        $_SESSION['forgot_error'] = 'Errore Database: ' . $e->getMessage();
    } else {
        $_SESSION['forgot_error'] = 'Errore del sistema. Riprova più tardi.';
    }
    header('Location: forgot-password.php');
    exit;

} catch (Exception $e) {
    error_log("ERRORE GENERICO FORGOT PASSWORD: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // In sviluppo mostra errore dettagliato
    if (ini_get('display_errors')) {
        $_SESSION['forgot_error'] = 'Errore: ' . $e->getMessage();
    } else {
        $_SESSION['forgot_error'] = 'Errore durante la richiesta. Riprova più tardi.';
    }
    header('Location: forgot-password.php');
    exit;
}