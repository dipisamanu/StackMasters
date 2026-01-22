<?php
/**
 * Script per testare la configurazione in src/config/email.php
 * File: public/debug/debug-email.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../src/config/email.php';

$msg = "";
$log = "";

if (isset($_POST['email'])) {
    try {
        // Debug mode (true)
        $emailService = new EmailService(true);

        // Cattura output di debug
        ob_start();
        $inviata = $emailService->send(
            $_POST['email'],
            "Test Sistema Biblioteca",
            "<h1>Funziona!</h1><p>Se leggi questo, PHPMailer è configurato correttamente.</p>"
        );
        $log = ob_get_clean();

        if ($inviata) {
            $msg = "<div style='color:green; font-weight:bold'><i class='fas fa-check-circle'></i> Email inviata con successo!</div>";
        } else {
            $msg = "<div style='color:red; font-weight:bold'><i class='fas fa-times-circle'></i> Errore nell'invio (vedi log sotto)</div>";
        }
    } catch (Exception $e) {
        $msg = "<div style='color:red'><i class='fas fa-exclamation-triangle'></i> Eccezione: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <title>Test SMTP Reale</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="font-family: sans-serif; padding: 20px;">
<h2><i class="fas fa-wrench"></i> Test Configurazione Email (PHPMailer)</h2>

<?= $msg ?>

<form method="post" style="margin-bottom: 20px;">
    <input type="email" name="email" placeholder="tua@email.com" required style="padding: 5px;">
    <button type="submit" style="padding: 5px 10px;"><i class="fas fa-paper-plane"></i> Invia Test</button>
</form>

<?php if ($log): ?>
    <h3>Log della connessione:</h3>
    <pre style="background: #eee; padding: 10px; border: 1px solid #ccc; font-size: 11px;"><?= htmlspecialchars($log) ?></pre>
<?php endif; ?>
</body>
</html>