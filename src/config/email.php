<?php

// Carica PHPMailer - MODIFICA IL PERCORSO SE NECESSARIO
require_once __DIR__ . '/../../vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configurazione SMTP
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', '9e6c819b93a362');
define('SMTP_PASSWORD', '15d382d3bea77b');
define('SMTP_FROM_EMAIL', 'noreply@bibliotecaitisrossi.it');
define('SMTP_FROM_NAME', 'Biblioteca ITIS Rossi');

class EmailService {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);

        try {
            // Configurazione SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = SMTP_PORT;

            // Mittente
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mailer->CharSet = 'UTF-8';

        } catch (Exception $e) {
            error_log("Errore configurazione email: " . $e->getMessage());
            throw $e;
        }
    }

    public function sendVerificationEmail($to, $nome, $token) {
        $verifyUrl = "http://localhost/StackMasters/public/verify-email.php?token=" . $token;

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

        } catch (Exception $e) {
            error_log("Errore invio email: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    private function getVerificationTemplate($nome, $verifyUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
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
            <div class='container'>
                <div class='header'>
                    <h1>Benvenuto nella Biblioteca ITIS Rossi!</h1>
                </div>
                <div class='content'>
                    <p>Ciao <strong>$nome</strong>,</p>
                    <p>Grazie per esserti registrato! Per completare la registrazione e attivare il tuo account, clicca sul pulsante qui sotto:</p>
                    
                    <div style='text-align: center;'>
                        <a href='$verifyUrl' class='button'>VERIFICA EMAIL</a>
                    </div>
                    
                    <p>Oppure copia e incolla questo link nel browser:</p>
                    <p style='background: white; padding: 10px; border-left: 3px solid #bf2121; word-break: break-all;'>
                        <code>$verifyUrl</code>
                    </p>
                    
                    <p><strong>Attenzione:</strong> Questo link scadrà tra 24 ore.</p>
                    
                    <p>Se non hai effettuato questa registrazione, ignora questa email.</p>
                </div>
                <div class='footer'>
                    <p>Biblioteca ITIS Rossi - Sistema Gestionale</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    public function sendLoanConfirmation($to, $nome, $titoloLibro, $dataScadenza) {
        $subject = "Conferma prestito - $titoloLibro";
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>✓ Prestito Confermato</h2>
                </div>
                <div class='content'>
                    <p>Ciao <strong>$nome</strong>,</p>
                    <p>Il prestito è stato registrato con successo!</p>
                    
                    <div class='info-box'>
                        <strong>📚 Libro:</strong> $titoloLibro<br>
                        <strong>📅 Data scadenza:</strong> $dataScadenza
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