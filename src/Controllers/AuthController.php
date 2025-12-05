<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Services\MailerService;

class AuthController extends Controller {

    public function register() {
        $data = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 1. Validazione base e Sanitizzazione
            $input = [
                'nome' => trim($_POST['nome'] ?? ''),
                'cognome' => trim($_POST['cognome'] ?? ''),
                'email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
                'password' => $_POST['password'],
                'cf' => strtoupper(trim($_POST['codiceFiscale'] ?? '')),
                // ... altri campi (data nascita, sesso, ecc) ...
            ];

            // 2. Generazione Token (Epic 1.5)
            $token = bin2hex(random_bytes(16)); // 32 caratteri

            // 3. Salvataggio Utente (Model)
            $userModel = new User();

            // Verifica duplicati prima di inserire
            if ($userModel->exists($input['email'], $input['cf'])) {
                $data['error'] = "Email o Codice Fiscale già registrati.";
            } else {
                $userId = $userModel->create([
                    'nome' => $input['nome'],
                    'cognome' => $input['cognome'],
                    'email' => $input['email'],
                    'password' => password_hash($input['password'], PASSWORD_BCRYPT),
                    'cf' => $input['cf'],
                    'token' => $token
                    // ... mappare altri campi ...
                ]);

                if ($userId) {
                    // 4. Invio Email (Epic 1.5)
                    $mailer = new MailerService();
                    $sent = $mailer->sendActivationEmail($input['email'], $input['nome'], $token);

                    if ($sent) {
                        $data['success_msg'] = "Registrazione completata! Controlla la tua email per attivare l'account.";
                        // Pulisci il form
                        $_POST = [];
                    } else {
                        $data['error'] = "Account creato, ma errore nell'invio della mail. Contatta l'admin.";
                    }
                } else {
                    $data['error'] = "Errore generico nel database.";
                }
            }
        }

        // Carica la View del form (Epic 1.1) passando eventuali messaggi
        $this->view('auth/register', $data);
    }
}