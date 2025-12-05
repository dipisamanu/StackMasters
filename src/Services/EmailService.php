<?php

namespace Ottaviodipisa\StackMasters\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $mailer;
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/mail.php';
        $this->mailer = new PHPMailer(true);
        $this->configuraSMTP();
    }

    private function configuraSMTP()
    {
        try {
            // Configurazione server SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $this->config['port'];

            // Configurazione mittente
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->isHTML(true);

        } catch (Exception $e) {
            error_log("Errore configurazione SMTP: {$e->getMessage()}");
            throw new \Exception("Impossibile configurare il servizio email");
        }
    }

    /**
     * Invia email di verifica account
     *
     * @param string $destinatario Email destinatario
     * @param string $nome Nome utente
     * @param string $token Token di verifica
     * @return bool Successo invio
     */
    public function inviaEmailVerifica($destinatario, $nome, $token)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($destinatario, $nome);

            $this->mailer->Subject = 'Verifica il tuo account - Biblioteca ITIS Rossi';

            // Genera link di verifica
            $linkVerifica = $this->config['base_url'] . "/verifica-email?token=" . urlencode($token);

            // Carica template HTML
            $templatePath = __DIR__ . '/../../templates/email/verifica_account.html';
            $htmlBody = file_get_contents($templatePath);

            // Sostituisci placeholder
            $htmlBody = str_replace('{{NOME}}', htmlspecialchars($nome), $htmlBody);
            $htmlBody = str_replace('{{LINK_VERIFICA}}', $linkVerifica, $htmlBody);
            $htmlBody = str_replace('{{ANNO}}', date('Y'), $htmlBody);

            $this->mailer->Body = $htmlBody;

            // Testo alternativo per client che non supportano HTML
            $this->mailer->AltBody = "Ciao $nome,\n\n"
                . "Grazie per esserti registrato alla Biblioteca ITIS Rossi.\n"
                . "Per completare la registrazione, clicca sul seguente link:\n\n"
                . "$linkVerifica\n\n"
                . "Il link è valido per 1 ora.\n\n"
                . "Se non hai richiesto questa registrazione, ignora questa email.";

            return $this->mailer->send();

        } catch (Exception $e) {
            error_log("Errore invio email verifica: {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    /**
     * Invia email di reset password
     */
    public function inviaEmailResetPassword($destinatario, $nome, $token)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($destinatario, $nome);

            $this->mailer->Subject = 'Reset Password - Biblioteca ITIS Rossi';

            $linkReset = $this->config['base_url'] . "/reset-password?token=" . urlencode($token);

            $htmlBody = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Reset Password</h2>
                <p>Ciao <strong>$nome</strong>,</p>
                <p>Hai richiesto il reset della password per il tuo account.</p>
                <p>Clicca sul pulsante qui sotto per procedere:</p>
                <p style='margin: 30px 0;'>
                    <a href='$linkReset' 
                       style='background-color: #bf2121; color: white; padding: 12px 24px; 
                              text-decoration: none; border-radius: 6px; display: inline-block;'>
                        Reset Password
                    </a>
                </p>
                <p style='color: #666; font-size: 14px;'>
                    Il link è valido per 1 ora. Se non hai richiesto questa operazione, ignora questa email.
                </p>
            </body>
            </html>";

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = "Reset password: $linkReset";

            return $this->mailer->send();

        } catch (Exception $e) {
            error_log("Errore invio email reset: {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    /**
     * Invia email generica
     */
    public function inviaEmail($destinatario, $oggetto, $corpo, $isHTML = true)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($destinatario);
            $this->mailer->Subject = $oggetto;

            if ($isHTML) {
                $this->mailer->Body = $corpo;
                $this->mailer->AltBody = strip_tags($corpo);
            } else {
                $this->mailer->Body = $corpo;
            }

            return $this->mailer->send();

        } catch (Exception $e) {
            error_log("Errore invio email: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
}