<?php
/**
 * Logica di Registrazione
 * File: public/process-register.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseConfigPath = __DIR__ . '/../src/config/';

require_once $baseConfigPath . 'database.php';
require_once $baseConfigPath . 'session.php';
require_once $baseConfigPath . 'email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// Controllo CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    Session::setFlash('error', "Errore di sicurezza (Token scaduto). Riprova.");
    header('Location: register.php');
    exit;
}

$db = getDB();
$errors = [];

// Raccolta Dati
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$dataNascita = $_POST['dataNascita'] ?? '';
$sesso = $_POST['sesso'] ?? '';
$comune = trim($_POST['comune'] ?? '');
$codiceFiscale = strtoupper(trim($_POST['codiceFiscale'] ?? ''));
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confermaPassword = $_POST['confermaPassword'] ?? '';

// Validazioni Base
if (empty($nome) || empty($cognome)) $errors[] = "Nome e Cognome sono obbligatori.";
if (empty($dataNascita)) $errors[] = "Data di nascita obbligatoria.";
if (!in_array($sesso, ['M', 'F'])) $errors[] = "Sesso non valido.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Inserisci un indirizzo email valido.";
if (empty($codiceFiscale)) {
    $errors[] = "Il Codice Fiscale è obbligatorio.";
} elseif (strlen($codiceFiscale) !== 16) {
    $errors[] = "Il Codice Fiscale deve essere di 16 caratteri.";
}

// Validazione Password Robusta
if (strlen($password) < 8) $errors[] = "La password deve contenere almeno 8 caratteri.";
if (!preg_match('/[A-Z]/', $password)) $errors[] = "La password deve contenere almeno una lettera maiuscola.";
if (!preg_match('/[0-9]/', $password)) $errors[] = "La password deve contenere almeno un numero.";
if ($password !== $confermaPassword) $errors[] = "Le password inserite non coincidono.";

// Controllo Duplicati nel DB
if (empty($errors)) {
    try {
        $stmt = $db->prepare("SELECT id_utente FROM utenti WHERE email = ? OR cf = ?");
        $stmt->execute([$email, $codiceFiscale]);
        if ($stmt->fetch()) {
            $errors[] = "Esiste già un account registrato con questa Email o Codice Fiscale.";
        }
    } catch (PDOException $e) {
        $errors[] = "Errore di sistema durante la verifica dei dati.";
        error_log("DB Error: " . $e->getMessage());
    }
}

// Se ci sono errori, torna indietro
if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_data'] = $_POST; // Mantiene i dati compilati
    header('Location: register.php');
    exit;
}

// Inserimento Utente
try {
    $db->beginTransaction();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $token = bin2hex(random_bytes(16));
    $scadenzaVerifica = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Insert Utente
    $sql = "INSERT INTO utenti (cf, nome, cognome, email, password, data_nascita, sesso, comune_nascita, token, email_verificata, scadenza_verifica, consenso_privacy) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 1)";

    $stmt = $db->prepare($sql);
    $stmt->execute([$codiceFiscale, $nome, $cognome, $email, $passwordHash, $dataNascita, $sesso, $comune, $token, $scadenzaVerifica]);

    $userId = $db->lastInsertId();

    // Assegnazione Ruolo Studente
    $stmtRuolo = $db->prepare("SELECT id_ruolo FROM ruoli WHERE nome = 'Studente' LIMIT 1");
    $stmtRuolo->execute();
    $ruoloId = $stmtRuolo->fetchColumn();

    if ($ruoloId) {
        $db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, ?)")->execute([$userId, $ruoloId]);
    }

    // Log Audit
    $db->prepare("INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address) VALUES (?, 'CREAZIONE_UTENTE', ?, INET_ATON(?))")
        ->execute([$userId, "Registrazione email: $email", $_SERVER['REMOTE_ADDR']]);

    // Invio Email di Verifica
    if (function_exists('getEmailService')) {
        getEmailService()->sendVerificationEmail($email, $nome, $token);
    }

    $db->commit();

    // Successo
    Session::setFlash('success', "Account creato con successo! Controlla la tua email per attivarlo.");
    header('Location: login.php');
    exit;

} catch (Exception $e) {
    $db->rollBack();
    error_log("Register Exception: " . $e->getMessage());
    Session::setFlash('error', "Si è verificato un errore durante la registrazione. Riprova più tardi.");
    header('Location: register.php');
    exit;
}