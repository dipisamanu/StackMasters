<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailerService {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setup();
    }

    private function setup() {
        // --- CONFIGURAZIONE SMTP ---
        $this->mailer->isSMTP();
        $this->mailer->Host       = 'smtp.gmail.com';
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = 'tuamail@gmail.com'; // <--- TUA EMAIL
        $this->mailer->Password   = 'tua_password_app';   // <--- TUA PASSWORD APP
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port       = 587;

        $this->mailer->setFrom('no-reply@itisrossi.it', 'Biblioteca ITIS Rossi');
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(true);
    }

    public function sendActivationEmail($userEmail, $userName, $token) {
        try {
            // 1. Percorso Template
            $templatePath = dirname(__DIR__) . '/Views/emails/activation.php';

            if (!file_exists($templatePath)) {
                throw new Exception("Template non trovato: " . $templatePath);
            }

            // 2. PREPARAZIONE VARIABILI PER IL TEMPLATE
            // Definiamo le variabili ESATTAMENTE come si chiamano nel file HTML.
            // Poiché 'require' avviene in questa funzione, il file HTML vedrà queste variabili.

            // $userName è già passato come argomento della funzione, quindi esiste.
            $activationLink = "http://localhost/StackMasters/public/index.php?route=activate&token=" . $token;

            // 3. Configurazione Email
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, $userName);
            $this->mailer->Subject = 'Attiva il tuo account - Biblioteca ITIS Rossi';

            // 4. Caricamento Template
            ob_start();
            // Includendo il file qui, esso eredita $userName e $activationLink dallo scope locale
            require $templatePath;
            $body = ob_get_clean();

            $this->mailer->Body = $body;
            $this->mailer->AltBody = "Ciao $userName, attiva il tuo account qui: $activationLink";

            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            // Debug: vedi l'errore a schermo se fallisce
            echo "Mailer Error: " . $e->getMessage();
            return false;
        }
    }
}