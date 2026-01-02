<?php
/**
 * Process Reset Password - Backend Cambio Password
 * File: public/process-reset-password.php
 */

// 1. Sessioni e Config
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
    $_SESSION['reset_error'] = 'Compila tutti i campi.';
    header("Location: reset-password.php?token=$token");
    exit;
}

if ($password !== $confirm) {
    $_SESSION['reset_error'] = 'Le password non coincidono.';
    header("Location: reset-password.php?token=$token");
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['reset_error'] = 'La password deve essere di almeno 8 caratteri.';
    header("Location: reset-password.php?token=$token");
    exit;
}

try {
    $db = getDB();

    // 2. Calcola Hash del Token per confronto DB
    // Nel forgot-password il token viene salvato come md5($random_bytes)
    $tokenHash = md5($token);

    // 3. Verifica Token valido e non scaduto
    $stmt = $db->prepare("
        SELECT id_utente 
        FROM Utenti 
        WHERE token = ? 
        AND scadenza_verifica > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['reset_error'] = 'Link non valido o scaduto. Richiedi un nuovo reset.';
        header('Location: forgot-password.php');
        exit;
    }

    // 4. Aggiorna Password e Pulisce Token
    $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);

    $update = $db->prepare("
        UPDATE Utenti 
        SET 
            password = ?,
            token = NULL,
            scadenza_verifica = NULL,
            ultimo_aggiornamento = NOW()
        WHERE id_utente = ?
    ");

    $update->execute([$newPasswordHash, $user['id_utente']]);

    // 5. Successo -> Login
    $_SESSION['login_success'] = 'Password aggiornata con successo! Ora puoi accedere.';
    header('Location: login.php');
    exit;

} catch (Exception $e) {
    error_log("Errore Reset Password: " . $e->getMessage());
    $_SESSION['reset_error'] = 'Errore di sistema. Riprova.';
    header("Location: reset-password.php?token=$token");
    exit;
}