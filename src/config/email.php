<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Dotenv\Dotenv;

// Carica .env dalla root del progetto
$projectRoot = dirname(__DIR__, 2);
if (file_exists($projectRoot . '/.env')) {
    try {
        $dotenv = Dotenv::createImmutable($projectRoot);
        $dotenv->load();
    } catch (\Throwable $e) {
        error_log("Errore caricamento .env: " . $e->getMessage());
    }
}

class EmailService {
    private $mailer;
    private $smtpConfig;
    private $debug;
    private $lastError = '';

    public function __construct($debug = false) {
        $this->debug = $debug;
        $this->mailer = new PHPMailer(true); // True abilita le eccezioni

        // Fallback per $_ENV se non popolato (capita in alcune config PHP)
        if (empty($_ENV['MAIL_HOST']) && getenv('MAIL_HOST')) {
            $_ENV['MAIL_HOST'] = getenv('MAIL_HOST');
            $_ENV['MAIL_PORT'] = getenv('MAIL_PORT');
            $_ENV['MAIL_USERNAME'] = getenv('MAIL_USERNAME');
            $_ENV['MAIL_PASSWORD'] = getenv('MAIL_PASSWORD');
            $_ENV['MAIL_FROM_ADDRESS'] = getenv('MAIL_FROM_ADDRESS');
        }

        $requiredVars = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
        foreach ($requiredVars as $var) {
            if (empty($_ENV[$var])) {
                // Non blocchiamo qui, proviamo a configurare lo stesso, magari fallirà dopo
                error_log("Attenzione: Variabile $var mancante in EmailService");
            }
        }

        $this->smtpConfig = [
            'host' => $_ENV['MAIL_HOST'] ?? '',
            'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Biblioteca ITIS Rossi',
        ];

        $this->configureMailer();
    }

    public function getLastError() {
        return $this->lastError;
    }

    private function configureMailer() {
        try {
            // Configurazione Debug
            if ($this->debug) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER; // Output dettagliato
                $this->mailer->Debugoutput = 'html';
            } else {
                $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
            }

            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtpConfig['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtpConfig['username'];
            $this->mailer->Password = $this->smtpConfig['password'];
            $this->mailer->SMTPSecure = $this->smtpConfig['encryption'];
            $this->mailer->Port = $this->smtpConfig['port'];
            
            // Opzioni SSL permissive (spesso risolve problemi di certificati locali)
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $this->mailer->setFrom($this->smtpConfig['from_email'], $this->smtpConfig['from_name']);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';

        } catch (PHPMailerException $e) {
            $this->lastError = "Configurazione fallita: " . $e->getMessage();
            error_log($this->lastError);
        }
    }

    public function send($to, $subject, $body, $altBody = '') {
        $this->lastError = '';
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearAllRecipients();
            
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);

            $result = $this->mailer->send();
            return $result;

        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage() . " (SMTP Error: " . $this->mailer->ErrorInfo . ")";
            error_log("Errore invio email a $to: " . $this->lastError);
            return false;
        } catch (\Exception $e) {
            $this->lastError = "Eccezione generica: " . $e->getMessage();
            error_log("Errore generico invio email a $to: " . $this->lastError);
            return false;
        }
    }

    // --- TEMPLATE EMAIL ---

    public function sendVerificationEmail($to, $nome, $token) {
        $baseUrl = $_ENV['APP_URL'] ?? (defined('BASE_URL') ? BASE_URL : 'http://localhost/StackMasters');
        $verifyUrl = rtrim($baseUrl, '/') . '/public/verify-email.php?token=' . urlencode($token);
        $subject = "Verifica il tuo account - Biblioteca ITIS Rossi";
        
        $body = $this->getBaseTemplate(
            "Benvenuto!",
            "<p>Ciao <strong>" . htmlspecialchars($nome) . "</strong>,</p>
             <p>Grazie per esserti registrato! Per attivare il tuo account, clicca sul pulsante qui sotto:</p>
             <p style='text-align: center; margin: 30px 0;'>
                <a href='" . htmlspecialchars($verifyUrl) . "' style='background-color: #0d6efd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>VERIFICA EMAIL</a>
             </p>
             <p style='font-size: 13px; color: #666;'>Il link scade tra 24 ore.</p>"
        );

        return $this->send($to, $subject, $body);
    }

    public function sendResetPasswordEmail($to, $nome, $resetLink) {
        $subject = "Reset Password - Biblioteca ITIS Rossi";
        
        $body = $this->getBaseTemplate(
            "Reset Password",
            "<p>Ciao <strong>" . htmlspecialchars($nome) . "</strong>,</p>
             <p>Abbiamo ricevuto una richiesta di reimpostazione della password.</p>
             <p style='text-align: center; margin: 30px 0;'>
                <a href='" . htmlspecialchars($resetLink) . "' style='background-color: #dc3545; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reimposta Password</a>
             </p>
             <p>Se non hai richiesto tu il reset, ignora questa email.</p>"
        );

        return $this->send($to, $subject, $body);
    }

    public function sendLoanConfirmation($to, $nome, $titoloLibro, $dataScadenza) {
        $subject = "Conferma prestito - " . $titoloLibro;
        
        $body = $this->getBaseTemplate(
            "Prestito Confermato",
            "<p>Ciao <strong>" . htmlspecialchars($nome) . "</strong>,</p>
             <p>Il prestito è stato registrato con successo!</p>
             <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #198754; margin: 20px 0;'>
                <strong>📚 Libro:</strong> " . htmlspecialchars($titoloLibro) . "<br>
                <strong>📅 Scadenza:</strong> " . htmlspecialchars($dataScadenza) . "
             </div>
             <p>Buona lettura!</p>"
        );

        return $this->send($to, $subject, $body);
    }

    public function sendReservationAvailable($to, $nome, $titoloLibro, $scadenzaRitiro) {
        $subject = "Prenotazione Disponibile - " . $titoloLibro;
        
        $body = $this->getBaseTemplate(
            "Libro Disponibile!",
            "<p>Ciao <strong>" . htmlspecialchars($nome) . "</strong>,</p>
             <p>Il libro che aspettavi è rientrato ed è stato messo da parte per te.</p>
             <div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>
                <strong>📚 Libro:</strong> " . htmlspecialchars($titoloLibro) . "<br>
                <strong>⏳ Ritiro entro:</strong> " . htmlspecialchars($scadenzaRitiro) . "
             </div>
             <p style='color: #dc3545; font-weight: bold;'>⚠ Hai 48 ore per il ritiro.</p>"
        );

        return $this->send($to, $subject, $body);
    }

    // Template Base Comune
    private function getBaseTemplate($title, $content) {
        return "
        <!DOCTYPE html>
        <html lang='it'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
                .header { background: #0f172a; color: white; padding: 25px; text-align: center; }
                .content { padding: 30px; color: #333; line-height: 1.6; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0;'>$title</h2>
                </div>
                <div class='content'>
                    $content
                </div>
                <div class='footer'>
                    <p>Biblioteca ITIS Rossi - Sistema Gestionale</p>
                    <p>Questa è un'email automatica, non rispondere.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

// Funzione helper per ottenere EmailService
function getEmailService($debug = false) {
    return new EmailService($debug);
}
