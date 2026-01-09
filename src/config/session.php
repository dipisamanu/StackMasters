<?php
/**
 * Gestione Sessioni Sicure - VERSIONE CORRETTA
 * File: src/config/session.php
 */

// Determina il base URL
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_FILENAME'];
    $public = str_contains($script, '/public/') ? '/public' : (str_contains($script, '/dashboard/') ? '/dashboard' : '');

    define('BASE_URL', $protocol . '://' . $host . '/StackMasters');
}

// Configurazione sessione sicura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Metti 1 se usi HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Nome sessione personalizzato
session_name('BIBLIOTECA_SESSION');

// Durata sessione: 2 ore di inattività
ini_set('session.gc_maxlifetime', 7200);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * Classe per gestire le sessioni utente
 */
class Session {

    public static function login($userId, $nomeCompleto, $email, $ruoli) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['nome_completo'] = $nomeCompleto;
        $_SESSION['email'] = $email;
        $_SESSION['ruoli'] = $ruoli;
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['created'] = time();

        // Determina il ruolo principale (priorità più bassa = più importante)
        usort($ruoli, function($a, $b) {
            return $a['priorita'] <=> $b['priorita'];
        });
        $_SESSION['ruolo_principale'] = $ruoli[0];
    }

    /**
     * Logout utente
     */
    public static function logout() {
        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();
    }

    /**
     * Verifica se utente è loggato
     */
    public static function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }

        // Verifica timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
            self::logout();
            return false;
        }

        // Verifica IP (protezione contro session hijacking) - DISABILITATO IN SVILUPPO
        // if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
        //     self::logout();
        //     return false;
        // }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Ottieni ID utente corrente
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Ottieni nome completo
     */
    public static function getNomeCompleto() {
        return $_SESSION['nome_completo'] ?? null;
    }

    /**
     * Ottieni email
     */
    public static function getEmail() {
        return $_SESSION['email'] ?? null;
    }

    /**
     * Verifica se utente ha un ruolo specifico
     */
    public static function hasRole($nomeRuolo) {
        if (!isset($_SESSION['ruoli'])) return false;

        foreach ($_SESSION['ruoli'] as $ruolo) {
            if ($ruolo['nome'] === $nomeRuolo) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se utente è admin
     */
    public static function isAdmin() {
        return self::hasRole('Admin');
    }

    /**
     * Verifica se utente è bibliotecario
     */
    public static function isLibrarian() {
        return self::hasRole('Bibliotecario') || self::isAdmin();
    }

    /**
     * Ottieni ruolo principale
     */
    public static function getMainRole() {
        return $_SESSION['ruolo_principale']['nome'] ?? 'Studente';
    }

    /**
     * Ottieni tutti i ruoli
     */
    public static function getRoles() {
        return $_SESSION['ruoli'] ?? [];
    }

    /**
     * Reindirizza alla dashboard appropriata
     */
    public static function redirectToDashboard() {
        $role = self::getMainRole();

        switch ($role) {
            case 'Admin':
                header('Location: ' . BASE_URL . '/dashboard/admin/index.php');
                break;
            case 'Bibliotecario':
                header('Location: ' . BASE_URL . '/dashboard/librarian/index.php');
                break;
            case 'Docente':
            case 'Studente':
            default:
                header('Location: ' . BASE_URL . '/dashboard/student/index.php');
                break;
        }
        exit;
    }

    /**
     * Richiedi autenticazione (o reindirizza a login)
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . BASE_URL . '/public/login.php');
            exit;
        }
    }

    /**
     * Richiedi ruolo specifico
     */
    public static function requireRole($nomeRuolo) {
        self::requireLogin();

        if (!self::hasRole($nomeRuolo)) {
            http_response_code(403);
            die("Accesso negato. Permessi insufficienti.");
        }
    }

    /**
     * Messaggi flash (es. "Registrazione completata!")
     */
    public static function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    public static function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    public static function hasFlash() {
        return isset($_SESSION['flash']);
    }

    /**
     * Debug info sessione (solo per sviluppo)
     */
    public static function debugInfo() {
        if (!defined('DEBUG_MODE') || DEBUG_MODE !== true) {
            return null;
        }

        return [
            'user_id' => self::getUserId(),
            'nome_completo' => self::getNomeCompleto(),
            'email' => self::getEmail(),
            'ruolo_principale' => self::getMainRole(),
            'tutti_ruoli' => self::getRoles(),
            'logged_in' => self::isLoggedIn(),
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'ip' => $_SESSION['ip'] ?? null
        ];
    }
}

/**
 * Helper function per CSRF token
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}