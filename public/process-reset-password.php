<?php
/**
 * Backend Cambio Password
 * File: public/process-reset-password.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

// Validazione base
if (empty($token) || empty($password) || empty($confirm)) {
    Session::setFlash('error', 'Tutti i campi sono obbligatori.');
    header("Location: reset-password.php?token=$token");
    exit;
}

if ($password !== $confirm) {
    Session::setFlash('error', 'Le password inserite non coincidono.');
    header("Location: reset-password.php?token=$token");
    exit;
}

if (strlen($password) < 8) {
    Session::setFlash('error', 'La password deve contenere almeno 8 caratteri.');
    header("Location: reset-password.php?token=$token");
    exit;
}

try {
    $db = getDB();

    // Calcola Hash del Token per confronto DB
    $tokenHash = md5($token);

    // Verifica Token valido e non scaduto
    $stmt = $db->prepare("
        SELECT id_utente 
        FROM utenti 
        WHERE token = ? 
        AND scadenza_verifica > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        Session::setFlash('error', 'Il link di reset non è valido o è scaduto. Richiedine uno nuovo.');
        header('Location: forgot-password.php');
        exit;
    }

    // Aggiorna Password e Pulisce Token
    $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);

    $update = $db->prepare("
        UPDATE utenti 
        SET 
            password = ?,
            token = NULL,
            scadenza_verifica = NULL,
            ultimo_aggiornamento = NOW()
        WHERE id_utente = ?
    ");

    $update->execute([$newPasswordHash, $user['id_utente']]);

    // Successo -> Login
    Session::setFlash('success', 'Password aggiornata con successo! Ora puoi accedere con le nuove credenziali.');
    header('Location: login.php');
    exit;

} catch (Exception $e) {
    error_log("Errore Reset Password: " . $e->getMessage());
    Session::setFlash('error', 'Si è verificato un errore di sistema. Riprova più tardi.');
    header("Location: reset-password.php?token=$token");
    exit;
}