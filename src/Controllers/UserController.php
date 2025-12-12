<?php

namespace Ottaviodipisa\StackMasters\Controllers;

use Ottaviodipisa\StackMasters\Core\Controller;
use Ottaviodipisa\StackMasters\Models\UserModel;

/**
 * UserController - Gestisce le operazioni relative al profilo utente
 */
class UserController extends Controller
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        // TODO: Implementare verifica autenticazione
    }

    /**
     * Mostra la pagina profilo utente con dati personali e prestiti attivi
     * Gestisce le sezioni 'profile' e 'loans' tramite parametro GET 'section'
     */
    public function profilo()
    {
        // TODO: Recuperare l'ID utente dalla sessione
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            // Redirect al login se non autenticato
            header('Location: /login');
            exit;
        }

        // Recupero sezione attiva dal parametro GET
        $activeSection = $_GET['section'] ?? 'profile';

        // Valida la sezione
        if (!in_array($activeSection, ['profile', 'loans'])) {
            $activeSection = 'profile';
        }

        try {
            // Recupera dati utente
            $utente = $this->userModel->getUserById($userId);

            // Recupera ruoli utente
            $ruoli = $this->userModel->getUserRoles($userId);

            // Recupera prestiti attivi
            $prestiti_attivi = $this->userModel->getActiveLoans($userId);

            // Prepara dati per la vista
            $data = [
                'utente' => $utente,
                'ruoli' => $ruoli,
                'prestiti_attivi' => $prestiti_attivi,
                'active_section' => $activeSection,
                'message' => '',
                'message_type' => ''
            ];

            $this->view('admin/profile', $data);

        } catch (\Exception $e) {
            // Gestione errori
            $data = [
                'utente' => [],
                'ruoli' => [],
                'prestiti_attivi' => [],
                'active_section' => $activeSection,
                'message' => 'Errore nel caricamento dei dati: ' . $e->getMessage(),
                'message_type' => 'error'
            ];

            $this->view('admin/profile', $data);
        }
    }

    /**
     * Aggiorna i dati del profilo utente
     */
    public function updateProfile()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /utente/profilo');
            exit;
        }

        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            header('Location: /login');
            exit;
        }

        try {
            // Recupera dati dal POST
            $data = [
                'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
                'notifiche_attive' => isset($_POST['notifiche_attive']),
                'quiet_hours_start' => $_POST['quiet_hours_start'] ?? null,
                'quiet_hours_end' => $_POST['quiet_hours_end'] ?? null
            ];

            // Aggiorna il profilo
            $this->userModel->updateUserProfile($userId, $data);

            // Redirect con messaggio di successo
            $_SESSION['message'] = 'Profilo aggiornato con successo!';
            $_SESSION['message_type'] = 'success';
            header('Location: /utente/profilo');
            exit;

        } catch (\Exception $e) {
            $_SESSION['message'] = 'Errore nell\'aggiornamento del profilo: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header('Location: /utente/profilo');
            exit;
        }
    }
}
