<?php
/**
 * File: public/process-register.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$basePath = __DIR__ . '/../src/config/';

if (!file_exists($basePath . 'database.php')) {
    die("ERRORE: database.php non trovato in: " . $basePath);
}
if (!file_exists($basePath . 'session.php')) {
    die("ERRORE: session.php non trovato in: " . $basePath);
}
if (!file_exists($basePath . 'email.php')) {
    die("ERRORE: email.php non trovato in: " . $basePath);
}

require_once $basePath . 'database.php';
require_once $basePath . 'session.php';
require_once $basePath . 'email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    die("Token CSRF non valido. Riprova.");
}

$db = getDB();
$errors = [];

// Raccolta e sanitizzazione dati
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$dataNascita = $_POST['dataNascita'] ?? '';
$sesso = $_POST['sesso'] ?? '';
$comune = trim($_POST['comune'] ?? '');
$codiceFiscale = strtoupper(trim($_POST['codiceFiscale'] ?? ''));
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confermaPassword = $_POST['confermaPassword'] ?? '';

error_log("=== INIZIO REGISTRAZIONE ===");
error_log("Nome: $nome");
error_log("Cognome: $cognome");
error_log("Email: $email");
error_log("CF: $codiceFiscale");
error_log("Data nascita: $dataNascita");
error_log("Sesso: $sesso");
error_log("Comune: $comune");

// VALIDAZIONI LATO SERVER

// Campi obbligatori
if (empty($nome)) $errors[] = "Il nome è obbligatorio";
if (empty($cognome)) $errors[] = "Il cognome è obbligatorio";
if (empty($dataNascita)) $errors[] = "La data di nascita è obbligatoria";
if (empty($sesso) || !in_array($sesso, ['M', 'F'])) $errors[] = "Il sesso è obbligatorio";
if (empty($comune)) $errors[] = "Il comune di nascita è obbligatorio";
if (empty($email)) $errors[] = "L'email è obbligatoria";
if (empty($password)) $errors[] = "La password è obbligatoria";

// Validazione email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Formato email non valido";
}

// Validazione password
if (strlen($password) < 8) {
    $errors[] = "La password deve essere di almeno 8 caratteri";
}
$errors = checkPassword($password, $errors, $confermaPassword);

// Validazione data di nascita
$dataNascitaObj = DateTime::createFromFormat('Y-m-d', $dataNascita);
if (!$dataNascitaObj || $dataNascitaObj->format('Y-m-d') !== $dataNascita) {
    $errors[] = "Data di nascita non valida";
} else {
    $oggi = new DateTime();
    $eta = $oggi->diff($dataNascitaObj)->y;
    if ($eta < 10 || $eta > 120) {
        $errors[] = "L'età deve essere compresa tra 10 e 120 anni";
    }
}

// Validazione Codice Fiscale
if (!empty($codiceFiscale)) {
    if (!preg_match('/^[A-Z0-9]{16}$/', $codiceFiscale)) {
        $errors[] = "Formato Codice Fiscale non valido (deve essere 16 caratteri alfanumerici)";
    }
} else {
    $errors[] = "Il Codice Fiscale è obbligatorio. Usa il bottone 'Calcola'";
}

// Verifica se email o CF già esistenti
if (empty($errors)) {
    try {
        $stmt = $db->prepare("SELECT id_utente FROM utenti WHERE email = ? OR cf = ?");
        $stmt->execute([$email, $codiceFiscale]);
        if ($stmt->fetch()) {
            $errors[] = "Email o Codice Fiscale già registrati nel sistema";
        }
    } catch (PDOException $e) {
        error_log("Errore verifica duplicati: " . $e->getMessage());
        $errors[] = "Errore durante la verifica dei dati. Riprova.";
    }
}

// Se ci sono errori, torna al form
if (!empty($errors)) {
    error_log("Errori validazione: " . print_r($errors, true));
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_data'] = $_POST;
    header('Location: register.php');
    exit;
}

// TUTTO OK - Procede con registrazione

try {
    error_log("=== INIZIO TRANSAZIONE ===");
    $db->beginTransaction();

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    error_log("Password hashata");

    // Genera token verifica (16 byte = 32 caratteri hex)
    $token = bin2hex(random_bytes(16));
    error_log("Token generato: " . strlen($token) . " caratteri");

    // Scadenza verifica: 24 ore
    $scadenzaVerifica = date('Y-m-d H:i:s', strtotime('+24 hours'));
    error_log("Scadenza verifica: $scadenzaVerifica");

    // Inserisci utente
    $sql = "
        INSERT INTO utenti 
        (cf, nome, cognome, email, password, data_nascita, sesso, comune_nascita, 
         token, email_verificata, scadenza_verifica, consenso_privacy)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 1)
    ";

    error_log("Query preparata");
    error_log("SQL: $sql");

    $stmt = $db->prepare($sql);

    $params = [
        $codiceFiscale,
        $nome,
        $cognome,
        $email,
        $passwordHash,
        $dataNascita,
        $sesso,
        $comune,
        $token,
        $scadenzaVerifica
    ];

    error_log("Parametri: " . print_r($params, true));

    $stmt->execute($params);

    $userId = $db->lastInsertId();
    error_log("Utente inserito con ID: $userId");

    // 5. Assegna ruolo "Studente"
    $stmtRuolo = $db->prepare("SELECT id_ruolo FROM ruoli WHERE nome = 'Studente' LIMIT 1");
    $stmtRuolo->execute();
    $ruoloStudente = $stmtRuolo->fetch();

    if (!$ruoloStudente) {
        throw new Exception("Ruolo Studente non trovato nel database. Esegui lo script install.sql");
    }

    error_log("Ruolo Studente trovato: " . $ruoloStudente['id_ruolo']);

    $stmtAssegna = $db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, ?)");
    $stmtAssegna->execute([$userId, $ruoloStudente['id_ruolo']]);
    error_log("Ruolo assegnato");

    // INSERISCI LOG REGISTRAZIONE
    $db->prepare("
        INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address)
        VALUES (?, 'CREAZIONE_UTENTE', ?, INET_ATON(?))
    ")->execute([
        $userId,
        "Nuovo utente registrato: " . $email,
        $_SERVER['REMOTE_ADDR']
    ]);

    // Invia email di verifica (se il servizio esiste)
    if (function_exists('getEmailService')) {
        try {
            $emailService = getEmailService();
            $emailInviata = $emailService->sendVerificationEmail($email, $nome, $token);

            if (!$emailInviata) {
                error_log("Errore invio email di verifica per utente ID: $userId");
            } else {
                error_log("Email inviata con successo");
            }
        } catch (Exception $e) {
            error_log("Errore servizio email: " . $e->getMessage());
        }
    }

    $db->commit();
    error_log("=== TRANSAZIONE COMPLETATA ===");

    // Successo
    Session::setFlash('success', "Registrazione completata! Controlla la tua email ($email) per verificare l'account. Il link scade tra 24 ore.");
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    $db->rollBack();
    error_log("=== ERRORE PDO ===");
    error_log("Messaggio: " . $e->getMessage());
    error_log("Codice: " . $e->getCode());
    error_log("File: " . $e->getFile() . " linea " . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());

    // Mostra errore specifico in sviluppo
    Session::setFlash('error', "Errore database: " . $e->getMessage());
    header('Location: register.php');
    exit;

} catch (Exception $e) {
    $db->rollBack();
    error_log("=== ERRORE GENERICO ===");
    error_log("Messaggio: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " linea " . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());

    Session::setFlash('error', "Errore durante la registrazione: " . $e->getMessage());
    header('Location: register.php');
    exit;
}