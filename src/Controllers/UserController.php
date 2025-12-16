<?php


/**
 * UserController - Gestisce le operazioni relative al profilo utente
 */
class UserController
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
            $active_section = $activeSection;
            $message = '';
            $message_type = '';

            include __DIR__ . '/../Views/admin/profile.php';

        } catch (\Exception $e) {
            // Gestione errori
            $utente = [];
            $ruoli = [];
            $prestiti_attivi = [];
            $active_section = $activeSection;
            $message = 'Errore nel caricamento dei dati: ' . $e->getMessage();
            $message_type = 'error';

            include __DIR__ . '/../Views/admin/profile.php';
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

    /**
     * Mostra la pagina di login
     */
    public function showLogin()
    {
        // Se l'utente è già loggato, redirect alla dashboard
        if (isset($_SESSION['user_id'])) {
            header('Location: /utente/profilo');
            exit;
        }

        $error = $_SESSION['login_error'] ?? null;

        // Pulisce il messaggio di errore dalla sessione
        unset($_SESSION['login_error']);

        include __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * Gestisce il processo di login
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        // Recupera i dati dal form
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        // Validazione base
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email e password sono obbligatorie';
            header('Location: /login');
            exit;
        }

        try {
            // Verifica le credenziali
            $user = $this->userModel->verifyLogin($email, $password);

            if ($user) {
                // Login riuscito - salva i dati in sessione
                $_SESSION['user_id'] = $user['id_utente'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nome'] = $user['nome'];
                $_SESSION['cognome'] = $user['cognome'];
                $_SESSION['email'] = $user['email'];

                // Redirect alla dashboard
                header('Location: /utente/profilo');
                exit;
            } else {
                // Credenziali errate
                $_SESSION['login_error'] = 'Email o password non corretti';
                header('Location: /login');
                exit;
            }

        } catch (\Exception $e) {
            $_SESSION['login_error'] = 'Errore durante il login: ' . $e->getMessage();
            header('Location: /login');
            exit;
        }
    }

    /**
     * Gestisce il logout
     */
    public function logout()
    {
        // Distrugge la sessione
        session_unset();
        session_destroy();

        // Redirect alla pagina di login
        header('Location: /login');
        exit;
    }
}
