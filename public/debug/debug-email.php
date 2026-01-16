<?php
// File: public/debug/debug-email.php
// Questo script testa la TUA configurazione in src/config/email.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORREZIONE PERCORSO: Aggiunto un '../' per risalire dalla cartella /debug
require_once __DIR__ . '/../../src/config/email.php';

$msg = "";
$log = "";

if (isset($_POST['email'])) {
    try {
        // Attiva il debug mode (true) per vedere il dialogo col server
        $emailService = new EmailService(true);

        // Cattura l'output di debug
        ob_start();
        $inviata = $emailService->send(
            $_POST['email'],
            "Test Sistema Biblioteca",
            "<h1>Funziona!</h1><p>Se leggi questo, PHPMailer è configurato correttamente.</p>"
        );
        $log = ob_get_clean();

        if ($inviata) {
            $msg = "<div style='color:green; font-weight:bold'>✅ Email inviata con successo!</div>";
        } else {
            $msg = "<div style='color:red; font-weight:bold'>❌ Errore nell'invio (vedi log sotto)</div>";
        }
    } catch (Exception $e) {
        $msg = "<div style='color:red'>❌ Eccezione: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head><title>Test SMTP Reale</title></head>
<body style="font-family: sans-serif; padding: 20px;">
<h2>🔧 Test Configurazione Email (PHPMailer)</h2>

<?= $msg ?>

<form method="post" style="margin-bottom: 20px;">
    <input type="email" name="email" placeholder="tua@email.com" required style="padding: 5px;">
    <button type="submit" style="padding: 5px 10px;">Invia Test</button>
</form>

<?php if ($log): ?>
    <h3>Log della connessione:</h3>
    <pre style="background: #eee; padding: 10px; border: 1px solid #ccc; font-size: 11px;"><?= htmlspecialchars($log) ?></pre>
<?php endif; ?>
</body>
</html>