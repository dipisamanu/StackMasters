<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

require_once '../src/config/database.php';
require_once '../src/utils/password-validator.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

try {
    $db = getDB();

    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validazione base
    if (empty($token) || empty($password) || empty($passwordConfirm)) {
        $_SESSION['reset_error'] = 'Tutti i campi sono obbligatori';
        header('Location: reset-password.php?token=' . urlencode($token));
        exit;
    }

    // Verifica corrispondenza password
    if ($password !== $passwordConfirm) {
        $_SESSION['reset_error'] = 'Le password non corrispondono';
        header('Location: reset-password.php?token=' . urlencode($token));
        exit;
    }

    // EPIC 2.2 - Valida robustezza password con tutti i controlli
    $validation = PasswordValidator::validate($password);

    if (!$validation['valid']) {
        $_SESSION['reset_error'] = 'Password non valida: ' . implode(', ', $validation['errors']);
        header('Location: reset-password.php?token=' . urlencode($token));
        exit;
    }

    // Verifica token (con hash SHA-256 per sicurezza)
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare("
        SELECT id_utente, nome, cognome, email
        FROM Utenti 
        WHERE token = ? AND scadenza_verifica > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['login_error'] = 'Token scaduto o non valido. Richiedi un nuovo link di reset.';
        header('Location: forgot-password.php');
        exit;
    }

    // EPIC 2.3 - Hash password con Bcrypt (cost 12 per sicurezza)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Aggiorna password e invalida token
    $stmt = $db->prepare("
        UPDATE Utenti 
        SET 
            password = ?,
            token = NULL,
            scadenza_verifica = NULL,
            tentativi_login_falliti = 0,
            blocco_account_fino_al = NULL
        WHERE id_utente = ?
    ");
    $stmt->execute([$passwordHash, $user['id_utente']]);

    // Log successo nel sistema di audit
    try {
        $db->prepare("
            INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address) 
            VALUES (?, 'PASSWORD_RESET_SUCCESS', ?, INET_ATON(?))
        ")->execute([
            $user['id_utente'],
            "Password reimpostata con successo",
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Errore log audit: " . $e->getMessage());
    }

    // Invia email di conferma
    $subject = "Password Modificata - Biblioteca ITIS Rossi";
    $nomeCompleto = htmlspecialchars($user['nome'] . ' ' . $user['cognome']);

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Password Modificata</h1>
            </div>
            <div class='content'>
                <p>Ciao <strong>$nomeCompleto</strong>,</p>
                
                <p>La tua password è stata modificata con successo!</p>
                
                <p>Da ora puoi accedere al sistema utilizzando la nuova password.</p>
                
                <div class='warning'>
                    <strong>⚠️ Non hai richiesto tu questa modifica?</strong><br>
                    Se non sei stato tu a modificare la password, contatta immediatamente l'amministrazione della biblioteca.
                </div>
                
                <p><strong>Dettagli modifica:</strong></p>
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

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@bibliotecaitisrossi.it" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    @mail($user['email'], $subject, $message, $headers);

    // Successo - reindirizza al login
    $_SESSION['login_success'] = 'Password reimpostata con successo! Ora puoi accedere con la nuova password.';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    error_log("ERRORE PDO RESET PASSWORD: " . $e->getMessage());
    $_SESSION['reset_error'] = 'Errore del database. Riprova più tardi.';
    header('Location: reset-password.php?token=' . urlencode($_POST['token'] ?? ''));
    exit;

} catch (Exception $e) {
    error_log("ERRORE GENERICO RESET PASSWORD: " . $e->getMessage());
    $_SESSION['reset_error'] = 'Errore durante il reset. Riprova più tardi.';
    header('Location: reset-password.php?token=' . urlencode($_POST['token'] ?? ''));
    exit;
}
/**
 * Process Reset Password - Backend Reset Password
 * File: public/process-reset-password.php
 *
 * EPIC 2.5 - Feature: Flow recupero password (Step 4)
 * EPIC 2.2 - Security: Validazione robustezza password
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

require_once '../src/config/database.php';
require_once '../src/utils/password-validator.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

try {
    $db = getDB();

    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validazione base
    if (empty($token) || empty($password) || empty($passwordConfirm)) {
        $_SESSION['reset_error'] = 'Tutti i campi sono obbligatori';
        header('Location: reset-password.php?token=' . urlencode($token));
        exit;
    }

    // Verifica corrispondenza password
    if ($password !== $passwordConfirm) {
        $_SESSION['reset_error'] = 'Le password non corrispondono';
        header('Location: reset-password.php?token=' . urlencode($token));
        exit;
    }

    // EPIC 2.2 - Valida robustezza password con tutti i controlli
    $validation = PasswordValidator::validate($password);

    if (!$validation['valid']) {
        $_SESSION['reset_error'] = 'Password non valida: ' . implode(', ', $validation['errors']);
        header('Location: reset-password.php?token=' . urlencode($token));
        exit;
    }

    // Verifica token (con hash SHA-256 per sicurezza)
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare("
        SELECT id_utente, nome, cognome, email
        FROM Utenti 
        WHERE token = ? AND scadenza_verifica > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['login_error'] = 'Token scaduto o non valido. Richiedi un nuovo link di reset.';
        header('Location: forgot-password.php');
        exit;
    }

    // EPIC 2.3 - Hash password con Bcrypt (cost 12 per sicurezza)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Aggiorna password e invalida token
    $stmt = $db->prepare("
        UPDATE Utenti 
        SET 
            password = ?,
            token = NULL,
            scadenza_verifica = NULL,
            tentativi_login_falliti = 0,
            blocco_account_fino_al = NULL
        WHERE id_utente = ?
    ");
    $stmt->execute([$passwordHash, $user['id_utente']]);

    // Log successo nel sistema di audit
    try {
        $db->prepare("
            INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address) 
            VALUES (?, 'PASSWORD_RESET_SUCCESS', ?, INET_ATON(?))
        ")->execute([
            $user['id_utente'],
            "Password reimpostata con successo",
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Errore log audit: " . $e->getMessage());
    }

    // Invia email di conferma
    $subject = "Password Modificata - Biblioteca ITIS Rossi";
    $nomeCompleto = htmlspecialchars($user['nome'] . ' ' . $user['cognome']);

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Password Modificata</h1>
            </div>
            <div class='content'>
                <p>Ciao <strong>$nomeCompleto</strong>,</p>
                
                <p>La tua password è stata modificata con successo!</p>
                
                <p>Da ora puoi accedere al sistema utilizzando la nuova password.</p>
                
                <div class='warning'>
                    <strong>⚠️ Non hai richiesto tu questa modifica?</strong><br>
                    Se non sei stato tu a modificare la password, contatta immediatamente l'amministrazione della biblioteca.
                </div>
                
                <p><strong>Dettagli modifica:</strong></p>
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

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@bibliotecaitisrossi.it" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    @mail($user['email'], $subject, $message, $headers);

    // Successo - reindirizza al login
    $_SESSION['login_success'] = 'Password reimpostata con successo! Ora puoi accedere con la nuova password.';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    error_log("ERRORE PDO RESET PASSWORD: " . $e->getMessage());
    $_SESSION['reset_error'] = 'Errore del database. Riprova più tardi.';
    header('Location: reset-password.php?token=' . urlencode($_POST['token'] ?? ''));
    exit;

} catch (Exception $e) {
    error_log("ERRORE GENERICO RESET PASSWORD: " . $e->getMessage());
    $_SESSION['reset_error'] = 'Errore durante il reset. Riprova più tardi.';
    header('Location: reset-password.php?token=' . urlencode($_POST['token'] ?? ''));
    exit;
}