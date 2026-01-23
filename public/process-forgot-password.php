<?php
/**
 * Backend Recupero Password
 * File: public/process-forgot-password.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // In produzione 0, debug 1
ini_set('log_errors', 1);

// Usa Session::setFlash invece di session_start manuale
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-password.php');
    exit;
}

try {
    $db = getDB();
    $email = trim($_POST['email'] ?? '');

    // Validazione Input
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Session::setFlash('error', 'Inserisci un indirizzo email valido.');
        header('Location: forgot-password.php');
        exit;
    }

    // Cerca l'utente
    $stmt = $db->prepare("SELECT id_utente, nome, cognome, email, email_verificata FROM utenti WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Sicurezza: Messaggio generico se email non trovata
    if (!$user) {
        // Simula attesa per prevenire timing attacks
        sleep(1);
        Session::setFlash('success', 'Se l\'email esiste nel sistema, riceverai le istruzioni per il reset.');
        header('Location: forgot-password.php');
        exit;
    }

    // Controllo verifica account
    if (!$user['email_verificata']) {
        Session::setFlash('error', 'Account non ancora verificato. Controlla la tua email per il link di attivazione.');
        header('Location: forgot-password.php');
        exit;
    }

    // Generazione Token
    $token = bin2hex(random_bytes(16)); // Token per l'URL
    $tokenHash = md5($token);           // Hash per il DB
    $expiryTime = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Aggiornamento DB
    $stmt = $db->prepare("UPDATE utenti SET token = ?, scadenza_verifica = ? WHERE id_utente = ?");
    $stmt->execute([$tokenHash, $expiryTime, $user['id_utente']]);

    // Costruzione Link e Messaggio
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $resetLink = $protocol . '://' . $host . '/StackMasters/public/reset-password.php?token=' . $token;

    $subject = "Reset Password - Biblioteca ITIS Rossi";
    $nomeCompleto = htmlspecialchars($user['nome'] . ' ' . $user['cognome']);

    // Template HTML Moderno
    $message = "
    <!DOCTYPE html>
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <h2 style='color: #bf2121; text-align: center;'>🔐 Reset Password</h2>
            <p>Ciao <strong>$nomeCompleto</strong>,</p>
            <p>Abbiamo ricevuto una richiesta di reimpostazione della password per il tuo account.</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='$resetLink' style='background-color: #bf2121; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reimposta Password</a>
            </p>
            <p>Se non hai richiesto tu il reset, ignora questa email. Il tuo account è al sicuro.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #666; text-align: center;'>Link alternativo: <br> <a href='$resetLink' style='color: #666;'>$resetLink</a></p>
        </div>
    </body>
    </html>
    ";

    // INVIO TRAMITE EMAILSERVICE (PHPMailer)
    try {
        $emailService = new EmailService(false);
        $sent = $emailService->send($user['email'], $subject, $message);

        if ($sent) {
            error_log("Email reset inviata a: " . $user['email']);
        } else {
            error_log("EmailService ha restituito false per: " . $user['email']);
            throw new Exception("Impossibile inviare email tramite SMTP");
        }

    } catch (Exception $e) {
        error_log("Errore invio EmailService: " . $e->getMessage());
    }

    Session::setFlash('success', 'Se l\'email esiste nel sistema, riceverai le istruzioni per il reset.');
    header('Location: forgot-password.php');
    exit;

} catch (Exception $e) {
    error_log("Errore Generale Reset Password: " . $e->getMessage());
    Session::setFlash('error', 'Si è verificato un errore imprevisto. Riprova più tardi.');
    header('Location: forgot-password.php');
    exit;
}
?>