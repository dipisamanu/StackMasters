<?php
/**
 * Process Login - Entry Point
 * File: public/process-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $_SESSION['login_error'] = 'Inserisci email e password per accedere.';
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
        $_SESSION['login_error'] = $result; // Il messaggio dal model è già specifico
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

    // Reindirizza TUTTI alla home page applicativa
    header('Location: ' . BASE_URL . '/public/home.php');
    exit;

} catch (Exception $e) {
    error_log("ERRORE LOGIN: " . $e->getMessage());
    $_SESSION['login_error'] = 'Si è verificato un errore di sistema. Riprova più tardi.';
    header('Location: login.php');
    exit;
}