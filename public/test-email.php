<?php
/**
 * Test Invio Email
 * File: test-email.php
 *
 * Testa se mail() funziona sul server
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$sent = false;
$error = '';
$result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Inserisci un indirizzo email valido';
    } else {
        $subject = "Test Email - Biblioteca ITIS Rossi";
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #bf2121; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Test Email Funzionante!</h1>
                </div>
                <div class='content'>
                    <p>Se ricevi questa email, significa che la funzione mail() del server funziona correttamente.</p>
                    <p><strong>Dettagli test:</strong></p>
                    <ul>
                        <li>Data: " . date('d/m/Y H:i:s') . "</li>
                        <li>Server: " . $_SERVER['SERVER_NAME'] . "</li>
                        <li>IP: " . $_SERVER['SERVER_ADDR'] . "</li>
                    </ul>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@bibliotecaitisrossi.it" . "\r\n";
        $headers .= "Reply-To: noreply@bibliotecaitisrossi.it" . "\r\n";

        // Prova invio
        $mailResult = @mail($to, $subject, $message, $headers);

        if ($mailResult) {
            $sent = true;
            $result = "✅ mail() ha ritornato TRUE - Email dovrebbe essere stata inviata";
        } else {
            $error = "❌ mail() ha ritornato FALSE - Invio fallito";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Invio Email</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #bf2121; margin-bottom: 20px; }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #155724;
        }
        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #721c24;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #856404;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #bf2121;
        }
        .btn {
            background: #bf2121;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn:hover { background: #931b1b; }
        .diagnostics {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 13px;
        }
        .diagnostics h3 {
            font-family: Arial;
            margin-bottom: 10px;
            color: #333;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📧 Test Invio Email</h1>

    <div class="info-box">
        <strong>ℹ️ Scopo di questo test:</strong><br>
        Verificare se la funzione <code>mail()</code> di PHP funziona sul tuo server.
    </div>

    <?php if ($sent): ?>
        <div class="success-box">
            <h3>✅ Email Inviata!</h3>
            <p><?= htmlspecialchars($result) ?></p>
            <p style="margin-top: 10px;"><strong>Cosa fare ora:</strong></p>
            <ul style="margin-left: 20px; margin-top: 5px;">
                <li>Controlla la tua inbox</li>
                <li>Controlla anche nella cartella SPAM</li>
                <li>Attendi qualche minuto (può richiedere tempo)</li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box">
            <strong>❌ Errore:</strong><br>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="to">Invia email di test a:</label>
            <input
                type="email"
                id="to"
                name="to"
                placeholder="tua.email@esempio.it"
                required
                value="<?= htmlspecialchars($_POST['to'] ?? '') ?>"
            >
        </div>
        <button type="submit" class="btn">📤 Invia Test Email</button>
    </form>

    <div class="diagnostics">
        <h3>🔍 Diagnostica Server</h3>
        <?php
        echo "Sistema Operativo: " . PHP_OS . "\n";
        echo "Versione PHP: " . PHP_VERSION . "\n";
        echo "SMTP configurato: " . (ini_get('SMTP') ?: 'Non impostato') . "\n";
        echo "Porta SMTP: " . (ini_get('smtp_port') ?: 'Non impostata') . "\n";
        echo "Sendmail path: " . (ini_get('sendmail_path') ?: 'Non impostato') . "\n";

        // Check se siamo su localhost
        $isLocalhost = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1']);
        if ($isLocalhost) {
            echo "\n⚠️ SEI SU LOCALHOST - mail() potrebbe non funzionare!\n";
        }
        ?>
    </div>

    <?php if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1'])): ?>
        <div class="warning-box">
            <strong>⚠️ Stai testando su LOCALHOST</strong><br><br>
            La funzione <code>mail()</code> di PHP di solito <strong>NON funziona su localhost</strong> senza configurazione aggiuntiva.<br><br>

            <strong>Soluzioni per localhost:</strong>
            <ol style="margin-left: 20px; margin-top: 10px;">
                <li><strong>Opzione 1 - PHPMailer (CONSIGLIATA):</strong><br>
                    Usa una libreria SMTP come PHPMailer con Gmail/Outlook
                </li>
                <li><strong>Opzione 2 - MailHog:</strong><br>
                    Installa MailHog per testare email localmente
                </li>
                <li><strong>Opzione 3 - Configura Sendmail:</strong><br>
                    Configura sendmail/postfix (complesso)
                </li>
                <li><strong>Opzione 4 - Testa su server reale:</strong><br>
                    Carica il sito su un hosting vero
                </li>
            </ol>
        </div>

        <div class="info-box">
            <strong>💡 Setup Veloce con Gmail (PHPMailer):</strong><br><br>
            Se vuoi che le email funzionino anche su localhost, posso aiutarti a configurare PHPMailer con Gmail.<br>
            È veloce, gratuito e funziona ovunque!
        </div>
    <?php else: ?>
        <div class="warning-box">
            <strong>📝 Se l'email NON arriva anche su server reale:</strong><br><br>
            <strong>Possibili cause:</strong>
            <ul style="margin-left: 20px; margin-top: 5px;">
                <li>SPF/DKIM non configurati (email va in spam)</li>
                <li>Server bloccato da provider email</li>
                <li>Hosting non permette invio diretto</li>
                <li>Necessita configurazione SMTP</li>
            </ul>
            <br>
            <strong>Soluzione:</strong> Usa un servizio SMTP come SendGrid, Mailgun, o Gmail SMTP.
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
        <a href="forgot-password.php" style="color: #bf2121; text-decoration: none; font-weight: 600;">
            ← Torna a Forgot Password
        </a>
    </div>
</div>
</body>
</html>