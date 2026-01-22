<?php

// Carica autoload di Composer (PHPMailer e vlucas/phpdotenv)
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__, 2);
if (file_exists($projectRoot . '/.env')) {
    try {
        $dotenv = Dotenv::createImmutable($projectRoot);
        $dotenv->load();
    } catch (\Throwable $e) {
        error_log("Errore caricamento .env: " . $e->getMessage());
        // Non blocchiamo tutto qui, lasciamo che sia il costruttore a gestire errori critici
    }
}

class EmailService {
    private $mailer;
    private $smtpConfig;
    private $debug;
    private $lastError = ''; // Variabile per memorizzare l'ultimo errore

    public function __construct($debug = false) {
        $this->debug = $debug;
        $this->mailer = new PHPMailer(true);

        // Verifica che le variabili d'ambiente siano caricate
        $requiredVars = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
        foreach ($requiredVars as $var) {
            if (empty($_ENV[$var])) {
                throw new \Exception("Variabile $var non definita in .env");
            }
        }

        // Configurazione SMTP usando i campi del nuovo .env
        $this->smtpConfig = [
            'host' => $_ENV['MAIL_HOST'],
            'port' => (int)($_ENV['MAIL_PORT']),
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'username' => $_ENV['MAIL_USERNAME'],
            'password' => $_ENV['MAIL_PASSWORD'],
            'from_email' => $_ENV['MAIL_FROM_ADDRESS'],
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Biblioteca ITIS Rossi',
        ];

        $this->configureMailer();
    }

    /**
     * Restituisce l'ultimo errore verificatosi durante l'invio
     */
    public function getLastError() {
        return $this->lastError;
    }

    private function configureMailer() {
        try {
            if ($this->debug) {
                $this->mailer->SMTPDebug = 0; // Disabilita output diretto (lo catturiamo se serve)
            }

            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtpConfig['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtpConfig['username'];
            $this->mailer->Password = $this->smtpConfig['password'];
            $this->mailer->SMTPSecure = $this->smtpConfig['encryption'];
            $this->mailer->Port = $this->smtpConfig['port'];
            
            // Mantiene la connessione viva per invii multipli
            $this->mailer->SMTPKeepAlive = true;

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
            throw $e;
        }
    }

    public function sendVerificationEmail($to, $nome, $token) {
        $baseUrl = $_ENV['APP_URL'] ?? (defined('BASE_URL') ? BASE_URL : 'http://localhost/StackMasters');
        $verifyUrl = rtrim($baseUrl, '/') . '/public/verify-email.php?token=' . urlencode($token);

        $subject = "Verifica il tuo account - Biblioteca ITIS Rossi";
        $body = $this->getVerificationTemplate($nome, $verifyUrl);

        return $this->send($to, $subject, $body);
    }

    public function send($to, $subject, $body, $altBody = '') {
        $this->lastError = ''; // Reset errore
        try {
            // Reset per evitare residui di invii precedenti
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearAllRecipients(); // Pulisce anche CC e BCC
            
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);

            $result = $this->mailer->send();

            if ($result) {
                return true;
            } else {
                $this->lastError = "Invio fallito (send ha restituito false)";
                return false;
            }

        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage() . " (Info: " . $this->mailer->ErrorInfo . ")";
            error_log("Errore invio email a $to: " . $this->lastError);
            return false;
        } catch (\Exception $e) {
            $this->lastError = "Eccezione generica: " . $e->getMessage();
            error_log("Errore generico invio email a $to: " . $this->lastError);
            return false;
        }
    }

    // ... (Template HTML invariati) ...
    private function getVerificationTemplate($nome, $verifyUrl) {
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
                            <tr>
                                <td style="background-color: #bf2121; padding: 30px; text-align: center;">
                                    <h1 style="margin: 0; color: #ffffff; font-size: 24px;">Benvenuto nella Biblioteca ITIS Rossi!</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 30px;">
                                    <p style="margin: 0 0 15px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                        Ciao <strong>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</strong>,
                                    </p>
                                    <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                        Grazie per esserti registrato! Per completare la registrazione e attivare il tuo account, clicca sul pulsante qui sotto:
                                    </p>
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
                                        <strong><i class="fas fa-exclamation-triangle"></i> Attenzione:</strong> Questo link scadrà tra 24 ore.
                                    </p>
                                    <p style="margin: 15px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;">
                                        Se non hai effettuato questa registrazione, ignora questa email.
                                    </p>
                                </td>
                            </tr>
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

    public function sendReservationAvailable($to, $nome, $titoloLibro, $scadenzaRitiro) {
        $subject = "Prenotazione Disponibile - " . $titoloLibro;
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
                    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
                }
                .header { 
                    background: #d35400; /* Arancione scuro, più elegante */
                    color: white; 
                    padding: 25px; 
                    text-align: center;
                }
                .header h2 {
                    margin: 0;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                .content { 
                    padding: 40px;
                }
                .info-box { 
                    background: #fdf2e9; /* Crema molto tenue */
                    padding: 20px; 
                    margin: 25px 0; 
                    border-left: 5px solid #d35400;
                    border-radius: 4px;
                    color: #5d4037;
                }
                .info-item {
                    margin-bottom: 8px;
                    font-size: 1.05em;
                }
                .info-item:last-child {
                    margin-bottom: 0;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    background: #f8f9fa;
                    color: #888;
                    font-size: 0.85em;
                    border-top: 1px solid #eee;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 25px;
                    background-color: #d35400;
                    color: white !important;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: bold;
                    margin-top: 20px;
                    transition: background-color 0.3s;
                }
                .btn:hover {
                    background-color: #a04000;
                }
                .urgent-note {
                    color: #c0392b;
                    font-weight: 600;
                    font-size: 0.9em;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <h2>🔔 Prenotazione Disponibile</h2>
                </div>
                <div class=\"content\">
                    <p>Ciao <strong>" . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                    <p>Ottime notizie! Il libro che stavi aspettando è rientrato ed è stato messo da parte per te.</p>
                    
                    <div class=\"info-box\">
                        <div class=\"info-item\"><strong>📚 Libro:</strong> " . htmlspecialchars($titoloLibro, ENT_QUOTES, 'UTF-8') . "</div>
                        <div class=\"info-item\"><strong>⏳ Scadenza Ritiro:</strong> " . htmlspecialchars($scadenzaRitiro, ENT_QUOTES, 'UTF-8') . "</div>
                    </div>
                    
                    <p class=\"urgent-note\">⚠ Importante: Hai 48 ore per il ritiro.</p>
                    <p>Se non passi entro la scadenza indicata, la prenotazione decadrà automaticamente e il libro verrà assegnato al prossimo utente in coda.</p>
                    
                    <p style=\"text-align: center;\">
                        <a href=\"" . ($_ENV['APP_URL'] ?? 'http://localhost/StackMasters') . "/dashboard/student/index.php\" class=\"btn\">Visualizza nella Dashboard</a>
                    </p>
                </div>
                <div class=\"footer\">
                    <p>Biblioteca ITIS Rossi - Sistema Gestionale</p>
                    <p>Via Legnano 12, Vicenza</p>
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
