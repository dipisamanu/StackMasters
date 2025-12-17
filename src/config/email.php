<?php

// Carica autoload di Composer (PHPMailer e vlucas/phpdotenv)
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Dotenv\Dotenv;

// Carica .env (se presente) dalla root del progetto
$projectRoot = dirname(__DIR__, 2);
if (file_exists($projectRoot . '/.env')) {
    try {
        Dotenv::createImmutable($projectRoot)->load();
    } catch (\Throwable $e) {
        throw new \Exception("Errore caricamento .env: " . $e->getMessage());
    }
}

class EmailService {
    private $mailer;
    private $smtpConfig;

    public function __construct() {
        $this->mailer = new PHPMailer(true);

        // Leggi la configurazione da $_ENV (populate da .env)
        if (empty($_ENV['MAIL_HOST'])) {
            throw new \Exception("Variabile MAIL_HOST non definita in .env");
        }
        if (empty($_ENV['MAIL_USERNAME'])) {
            throw new \Exception("Variabile MAIL_USERNAME non definita in .env");
        }
        if (empty($_ENV['MAIL_PASSWORD'])) {
            throw new \Exception("Variabile MAIL_PASSWORD non definita in .env");
        }
        if (empty($_ENV['MAIL_FROM_ADDRESS'])) {
            throw new \Exception("Variabile MAIL_FROM_ADDRESS non definita in .env");
        }

        $this->smtpConfig = [
            'host' => getenv('MAIL_HOST') !== false ? getenv('MAIL_HOST') : 'smtp.example.com',
            'port' => getenv('MAIL_PORT') !== false ? intval(getenv('MAIL_PORT')) : 587,
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'username' => getenv('MAIL_USERNAME') !== false ? getenv('MAIL_USERNAME') : null,
            'password' => getenv('MAIL_PASSWORD') !== false ? getenv('MAIL_PASSWORD') : null,
            'from_email' => getenv('MAIL_FROM_ADDRESS') !== false ? getenv('MAIL_FROM_ADDRESS') : 'no-reply@example.com',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'StackMasters',
        ];

        try {
            // Configurazione SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtpConfig['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtpConfig['username'];
            $this->mailer->Password = $this->smtpConfig['password'];
            $this->mailer->SMTPSecure = $this->smtpConfig['encryption'];
            $this->mailer->Port = $this->smtpConfig['port'];

            // Mittente
            $this->mailer->setFrom($this->smtpConfig['from_email'], $this->smtpConfig['from_name']);
            $this->mailer->CharSet = 'UTF-8';

        } catch (PHPMailerException $e) {
            error_log("Errore configurazione email: " . $e->getMessage());
            throw $e;
        }
    }

    public function sendVerificationEmail($to, $nome, $token) {
        // Costruisci l'URL dinamicamente
        $host = $_SERVER['HTTP_HOST'] ?? $_ENV['APP_HOST'] ?? '';
        if (empty($host)) {
            throw new \Exception("Impossibile determinare APP_HOST. Definisci APP_HOST o HTTP_HOST in .env");
        }
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $protocol . '://' . $host . '/StackMasters';
        $verifyUrl = $baseUrl . '/public/verify-email.php?token=' . urlencode($token);

        $subject = "Verifica il tuo account - Biblioteca ITIS Rossi";
        $body = $this->getVerificationTemplate($nome, $verifyUrl);

        return $this->send($to, $subject, $body);
    }

    public function send($to, $subject, $body, $altBody = '') {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);

            $this->mailer->send();
            return true;

        } catch (PHPMailerException $e) {
            error_log("Errore invio email: " . $e->getMessage());
            return false;
        }
    }

    private function getVerificationTemplate($nome, $verifyUrl) {
        return "
        <!DOCTYPE html>
        <html lang=\"it\">
        <head>
            <meta charset=\"UTF-8\">
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #bf2121; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; padding: 15px 30px; background: #bf2121; color: white !important; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <h1>Benvenuto nella Biblioteca ITIS Rossi!</h1>
                </div>
                <div class=\"content\">
                    <p>Ciao <strong>" . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                    <p>Grazie per esserti registrato! Per completare la registrazione e attivare il tuo account, clicca sul pulsante qui sotto:</p>
                    
                    <div style=\"text-align: center;\">
                        <a href=\"" . $verifyUrl . "\" class=\"button\">VERIFICA EMAIL</a>
                    </div>
                    
                    <p>Oppure copia e incolla questo link nel browser:</p>
                    <p style=\"background: white; padding: 10px; border-left: 3px solid #bf2121; word-break: break-all;\">
                        <code>" . $verifyUrl . "</code>
                    </p>
                    
                    <p><strong>Attenzione:</strong> Questo link scadrà tra 24 ore.</p>
                    
                    <p>Se non hai effettuato questa registrazione, ignora questa email.</p>
                </div>
                <div class=\"footer\">
                    <p>Biblioteca ITIS Rossi - Sistema Gestionale</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    public function sendLoanConfirmation($to, $nome, $titoloLibro, $dataScadenza) {
        $subject = "Conferma prestito - " . $titoloLibro;
        $body = "
        <!DOCTYPE html>
        <html lang=\"it\">
        <head>
            <meta charset=\"UTF-8\">
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <h2>✓ Prestito Confermato</h2>
                </div>
                <div class=\"content\">
                    <p>Ciao <strong>" . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                    <p>Il prestito è stato registrato con successo!</p>
                    
                    <div class=\"info-box\">
                        <strong>📚 Libro:</strong> " . htmlspecialchars($titoloLibro, ENT_QUOTES, 'UTF-8') . "<br>
                        <strong>📅 Data scadenza:</strong> " . htmlspecialchars($dataScadenza, ENT_QUOTES, 'UTF-8') . "
                    </div>
                    
                    <p>Buona lettura!</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body);
    }
}

function getEmailService() {
    return new EmailService();
}

