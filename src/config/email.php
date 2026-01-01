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
        $dotenv = Dotenv::createImmutable($projectRoot);
        $dotenv->load();
    } catch (\Throwable $e) {
        error_log("Errore caricamento .env: " . $e->getMessage());
        throw new \Exception("Errore caricamento .env: " . $e->getMessage());
    }
}

class EmailService {
    private $mailer;
    private $smtpConfig;
    private $debug;

    public function __construct($debug = false) {
        $this->debug = $debug;
        $this->mailer = new PHPMailer(true);

        // Verifica che le variabili d'ambiente siano caricate
        $requiredVars = ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
        foreach ($requiredVars as $var) {
            if (empty($_ENV[$var])) {
                throw new \Exception("Variabile $var non definita in .env");
            }
        }

        // Configurazione SMTP
        $this->smtpConfig = [
            'host' => $_ENV['MAIL_HOST'],
            'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'username' => $_ENV['MAIL_USERNAME'],
            'password' => $_ENV['MAIL_PASSWORD'],
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'],
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Biblioteca ITIS Rossi',
        ];

        $this->configureMailer();
    }

    private function configureMailer() {
        try {
            // Debug mode (per vedere errori dettagliati)
            if ($this->debug) {
                $this->mailer->SMTPDebug = 2; // 0 = off, 1 = client, 2 = client and server
                $this->mailer->Debugoutput = function($str, $level) {
                    error_log("PHPMailer [$level]: $str");
                };
            }

            // Configurazione SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtpConfig['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtpConfig['username'];
            $this->mailer->Password = $this->smtpConfig['password'];
            $this->mailer->SMTPSecure = $this->smtpConfig['encryption'];
            $this->mailer->Port = $this->smtpConfig['port'];

            // Impostazioni aggiuntive per Mailtrap
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Mittente
            $this->mailer->setFrom($this->smtpConfig['from_email'], $this->smtpConfig['from_name']);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';

        } catch (PHPMailerException $e) {
            error_log("Errore configurazione email: " . $e->getMessage());
            throw $e;
        }
    }

    public function sendVerificationEmail($to, $nome, $token) {
        // Usa APP_URL dal .env se disponibile, altrimenti usa BASE_URL o costruisci manualmente
        if (!empty($_ENV['APP_URL'])) {
            $baseUrl = rtrim($_ENV['APP_URL'], '/');
        } elseif (defined('BASE_URL')) {
            $baseUrl = BASE_URL;
        } else {
            // Fallback: costruisci URL manualmente
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host . '/StackMasters';
        }

        $verifyUrl = $baseUrl . '/public/verify-email.php?token=' . urlencode($token);

        // Log per debug
        error_log("BASE_URL utilizzato: " . $baseUrl);
        error_log("URL Verifica completo: " . $verifyUrl);

        $subject = "Verifica il tuo account - Biblioteca ITIS Rossi";
        $body = $this->getVerificationTemplate($nome, $verifyUrl);

        return $this->send($to, $subject, $body);
    }

    public function send($to, $subject, $body, $altBody = '') {
        try {
            // Reset destinatari precedenti
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Aggiungi destinatario
            $this->mailer->addAddress($to);

            // Imposta contenuto
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);

            // Invia
            $result = $this->mailer->send();

            if ($result) {
                error_log("Email inviata con successo a: $to");
                return true;
            } else {
                error_log("Invio email fallito (nessuna eccezione)");
                return false;
            }

        } catch (PHPMailerException $e) {
            error_log("Errore invio email a $to: " . $e->getMessage());
            error_log("ErrorInfo: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    private function getVerificationTemplate($nome, $verifyUrl) {
        // Template email con stili inline (migliore compatibilità con client email)
        return '
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background-color: #bf2121; padding: 30px; text-align: center;">
                                    <h1 style="margin: 0; color: #ffffff; font-size: 24px;">📚 Benvenuto nella Biblioteca ITIS Rossi!</h1>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 30px;">
                                    <p style="margin: 0 0 15px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                        Ciao <strong>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</strong>,
                                    </p>
                                    
                                    <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                        Grazie per esserti registrato! Per completare la registrazione e attivare il tuo account, clicca sul pulsante qui sotto:
                                    </p>
                                    
                                    <!-- Button -->
                                    <table>
                                        <tr>
                                            <td align="center" style="padding: 20px 0;">
                                                <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" 
                                                   rel="noreferrer"
                                                   style="display: inline-block; padding: 15px 40px; background-color: #bf2121; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                                                    VERIFICA EMAIL
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style="margin: 20px 0 10px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                        Oppure copia e incolla questo link nel browser:
                                    </p>
                                    
                                    <div style="background-color: #f8f9fa; padding: 15px; border-left: 3px solid #bf2121; word-break: break-all; margin: 15px 0;">
                                        <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" 
                                           rel="noreferrer"
                                           style="color: #0066cc; text-decoration: none; font-size: 14px;">
                                            ' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '
                                        </a>
                                    </div>
                                    
                                    <p style="margin: 20px 0 0 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                        <strong>⚠️ Attenzione:</strong> Questo link scadrà tra 24 ore.
                                    </p>
                                    
                                    <p style="margin: 15px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;">
                                        Se non hai effettuato questa registrazione, ignora questa email.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 20px; text-align: center;">
                                    <p style="margin: 0 0 5px 0; color: #666666; font-size: 14px;">
                                        Biblioteca ITIS Rossi - Sistema Gestionale
                                    </p>
                                    <p style="margin: 0; color: #999999; font-size: 12px;">
                                        Questa è un\'email automatica, non rispondere.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
    }

    public function sendLoanConfirmation($to, $nome, $titoloLibro, $dataScadenza) {
        $subject = "Conferma prestito - " . $titoloLibro;
        $body = "
        <!DOCTYPE html>
        <html lang=\"it\">
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <style>
                body { 
                    font-family: 'Segoe UI', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #f4f4f4;
                }
                .container { 
                    max-width: 600px; 
                    margin: 20px auto;
                    background: white;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header { 
                    background: #28a745; 
                    color: white; 
                    padding: 20px; 
                    text-align: center;
                }
                .content { 
                    padding: 30px;
                }
                .info-box { 
                    background: #f8f9fa; 
                    padding: 15px; 
                    margin: 15px 0; 
                    border-left: 4px solid #28a745;
                    border-radius: 4px;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    background: #f8f9fa;
                    color: #666;
                    font-size: 0.9em;
                }
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
                <div class=\"footer\">
                    <p>Biblioteca ITIS Rossi - Sistema Gestionale</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body);
    }
}

// Funzione helper per ottenere EmailService
function getEmailService($debug = false) {
    return new EmailService($debug);
}