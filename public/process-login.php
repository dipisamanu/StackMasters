<?php
/**
 * Process Login - Entry Point
 * File: public/process-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Nascondi errori a video in produzione

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/Models/UserModel.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'Email e password sono obbligatorie';
    header('Location: login.php');
    exit;
}

try {
    // Istanzio il nuovo modello
    $userModel = new UserModel();

    // Eseguo il login
    // Restituisce array (utente) o stringa (errore)
    $result = $userModel->login($email, $password);

    if (is_string($result)) {
        // È un errore (es. password errata, account bloccato)
        $_SESSION['login_error'] = $result;
        header('Location: login.php');
        exit;
    }

    // Login avvenuto con successo
    $user = $result;

    // Log nel DB (come prima)
    $userModel->logAudit($user['id_utente'], 'LOGIN_SUCCESS', "Login effettuato da: " . $email);

    // Crea la sessione (usa la tua classe Session esistente)
    $nomeCompleto = $user['nome'] . ' ' . $user['cognome'];
    Session::login($user['id_utente'], $nomeCompleto, $user['email'], $user['roles']);

    // Reindirizzamento in base al ruolo (Logica originale mantenuta)
    $mainRole = $user['roles'][0]['nome'] ?? 'Studente';

    switch ($mainRole) {
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

} catch (Exception $e) {
    error_log("ERRORE LOGIN: " . $e->getMessage());
    $_SESSION['login_error'] = 'Errore di sistema. Riprova più tardi.';
    header('Location: login.php');
    exit;
}