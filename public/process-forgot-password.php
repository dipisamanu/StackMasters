<?php
/**
 * Process Forgot Password - Backend Recupero Password
 * File: public/process-forgot-password.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // In produzione 0, debug 1
ini_set('log_errors', 1);

session_start();

// Importiamo Database e il servizio Email
require_once '../src/config/database.php';
require_once '../src/config/email.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-password.php');
    exit;
}

try {
    $db = getDB();
    $email = trim($_POST['email'] ?? '');

    // 1. Validazione Input
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['forgot_error'] = 'Inserisci un indirizzo email valido';
        header('Location: forgot-password.php');
        exit;
    }

    // 2. Cerca l'utente
    $stmt = $db->prepare("SELECT id_utente, nome, cognome, email, email_verificata FROM Utenti WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Sicurezza: Messaggio generico se email non trovata
    if (!$user) {
        // Simula attesa per prevenire timing attacks
        sleep(1);
        $_SESSION['forgot_success'] = 'Se l\'email esiste nel sistema, riceverai le istruzioni per il reset.';
        header('Location: forgot-password.php');
        exit;
    }

    // 3. Controllo verifica account
    if (!$user['email_verificata']) {
        $_SESSION['forgot_error'] = 'Account non ancora verificato. Controlla la tua email.';
        header('Location: forgot-password.php');
        exit;
    }

    // 4. Rate Limiting (Opzionale, mantenuto dal tuo codice originale)
    // ... (Logica rate limiting omessa per brevità, puoi lasciarla se vuoi)

    // 5. Generazione Token
    $token = bin2hex(random_bytes(16)); // Token per l'URL
    $tokenHash = md5($token);           // Hash per il DB
    $expiryTime = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // 6. Aggiornamento DB
    $stmt = $db->prepare("UPDATE Utenti SET token = ?, scadenza_verifica = ? WHERE id_utente = ?");
    $stmt->execute([$tokenHash, $expiryTime, $user['id_utente']]);

    // 7. Costruzione Link e Messaggio
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // Assicurati che il percorso /StackMasters/public/ sia corretto per il tuo server
    $resetLink = $protocol . '://' . $host . '/StackMasters/public/reset-password.php?token=' . $token;

    $subject = "Reset Password - Biblioteca ITIS Rossi";
    $nomeCompleto = htmlspecialchars($user['nome'] . ' ' . $user['cognome']);

    // Template HTML (Semplificato per integrazione con EmailService)
    $message = "
    <h2>🔐 Reset Password</h2>
    <p>Ciao <strong>$nomeCompleto</strong>,</p>
    <p>Hai richiesto di reimpostare la tua password.</p>
    <p><a href='$resetLink' style='background:#bf2121; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Reimposta Password</a></p>
    <p>Oppure copia questo link: $resetLink</p>
    <p><small>Il link scade tra 24 ore.</small></p>
    ";

    // 8. INVIO TRAMITE EMAILSERVICE (PHPMailer)
    try {
        // Istanzia il servizio email (definito in src/config/email.php)
        $emailService = new EmailService(false); // false = no debug output a schermo

        $sent = $emailService->send(
            $user['email'],
            $subject,
            $message
        );

        if ($sent) {
            error_log("Email reset inviata a: " . $user['email']);
        } else {
            error_log("EmailService ha restituito false per: " . $user['email']);
            throw new Exception("Impossibile inviare email tramite SMTP");
        }

    } catch (Exception $e) {
        error_log("Errore invio EmailService: " . $e->getMessage());
        // Non mostriamo l'errore all'utente, ma lo logghiamo
    }

    // 9. Redirect finale
    $_SESSION['forgot_success'] = 'Se l\'email esiste nel sistema, riceverai le istruzioni per il reset.';
    header('Location: forgot-password.php');
    exit;

} catch (Exception $e) {
    error_log("Errore Generale Reset Password: " . $e->getMessage());
    $_SESSION['forgot_error'] = 'Si è verificato un errore. Riprova più tardi.';
    header('Location: forgot-password.php');
    exit;
}
?>