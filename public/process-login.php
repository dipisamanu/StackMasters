<?php
/**
 * Backend Login
 * File: public/process-login.php
 */

require_once '../src/config/database.php';
require_once '../src/config/session.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Verifica CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    die("Token CSRF non valido");
}

$db = getDB();
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validazione base
if (empty($email) || empty($password)) {
    Session::setFlash('error', 'Email e password sono obbligatorie');
    header('Location: login.php');
    exit;
}

try {
    // 1. Cerca utente
    $stmt = $db->prepare("
        SELECT 
            id_utente, 
            username, 
            email, 
            password, 
            email_verificata,
            tentativi_login_falliti,
            blocco_account_fino_al
        FROM Utenti 
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 2. Verifica esistenza utente
    if (!$user) {
        logFailedAttempt($db, null, $email);
        Session::setFlash('error', 'Credenziali non valide');
        header('Location: login.php');
        exit;
    }

    // 3. Verifica blocco account
    if ($user['blocco_account_fino_al'] && strtotime($user['blocco_account_fino_al']) > time()) {
        $minutiRimasti = ceil((strtotime($user['blocco_account_fino_al']) - time()) / 60);
        Session::setFlash('error', "Account temporaneamente bloccato per troppi tentativi falliti. Riprova tra $minutiRimasti minuti.");
        header('Location: login.php');
        exit;
    }

    // 4. Verifica password
    if (!password_verify($password, $user['password'])) {
        incrementFailedAttempts($db, $user['id_utente']);
        logFailedAttempt($db, $user['id_utente'], $email);

        $tentativiRimasti = 5 - ($user['tentativi_login_falliti'] + 1);
        if ($tentativiRimasti <= 0) {
            Session::setFlash('error', 'Troppi tentativi falliti. Account bloccato per 15 minuti.');
        } else {
            Session::setFlash('error', "Credenziali non valide. Tentativi rimasti: $tentativiRimasti");
        }

        header('Location: login.php');
        exit;
    }

    // 5. Verifica email verificata
    if (!$user['email_verificata']) {
        Session::setFlash('warning', 'Devi verificare la tua email prima di accedere. Controlla la tua casella di posta.');
        header('Location: login.php');
        exit;
    }

    // 6. Tutto OK - Recupera ruoli utente
    $stmtRuoli = $db->prepare("
        SELECT r.id_ruolo, r.nome, r.priorita, r.durata_prestito, r.limite_prestiti
        FROM Ruoli r
        INNER JOIN Utenti_Ruoli ur ON r.id_ruolo = ur.id_ruolo
        WHERE ur.id_utente = ?
    ");
    $stmtRuoli->execute([$user['id_utente']]);
    $ruoli = $stmtRuoli->fetchAll();

    if (empty($ruoli)) {
        // Assegna ruolo studente di default se non ha ruoli
        $stmtDefaultRole = $db->prepare("SELECT id_ruolo FROM Ruoli WHERE nome = 'Studente' LIMIT 1");
        $stmtDefaultRole->execute();
        $defaultRole = $stmtDefaultRole->fetch();

        if ($defaultRole) {
            $db->prepare("INSERT INTO Utenti_Ruoli (id_utente, id_ruolo) VALUES (?, ?)")
                ->execute([$user['id_utente'], $defaultRole['id_ruolo']]);

            $stmtRuoli->execute([$user['id_utente']]);
            $ruoli = $stmtRuoli->fetchAll();
        }
    }

    // 7. Reset tentativi falliti
    $db->prepare("UPDATE Utenti SET tentativi_login_falliti = 0, blocco_account_fino_al = NULL WHERE id_utente = ?")
        ->execute([$user['id_utente']]);

    // 8. Log successo
    $db->prepare("
        INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address) 
        VALUES (?, 'LOGIN_SUCCESS', ?, INET_ATON(?))
    ")->execute([
        $user['id_utente'],
        "Login effettuato da: " . $email,
        $_SERVER['REMOTE_ADDR']
    ]);

    // 9. Crea sessione
    Session::login(
        $user['id_utente'],
        $user['username'],
        $user['email'],
        $ruoli
    );

    // 10. Remember me (cookie persistente)
    if ($remember) {
        $rememberToken = bin2hex(random_bytes(32));
        $hashedToken = password_hash($rememberToken, PASSWORD_BCRYPT);

        // Salva token nel DB (dovresti creare una tabella Remember_Tokens)
        // Per ora usiamo solo il cookie
        setcookie('remember_token', $rememberToken, time() + (86400 * 30), '/', '', false, true);
    }

    // 11. Reindirizza
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirect");
    } else {
        Session::redirectToDashboard();
    }

} catch (Exception $e) {
    error_log("Errore login: " . $e->getMessage());
    Session::setFlash('error', 'Errore durante il login. Riprova più tardi.');
    header('Location: login.php');
    exit;
}

/**
 * Incrementa tentativi falliti e blocca se necessario
 */
function incrementFailedAttempts($db, $userId) {
    $stmt = $db->prepare("
        UPDATE Utenti 
        SET tentativi_login_falliti = tentativi_login_falliti + 1
        WHERE id_utente = ?
    ");
    $stmt->execute([$userId]);

    // Controlla se superato limite
    $stmt = $db->prepare("SELECT tentativi_login_falliti FROM Utenti WHERE id_utente = ?");
    $stmt->execute([$userId]);
    $tentativi = $stmt->fetchColumn();

    if ($tentativi >= 5) {
        // Blocca per 15 minuti
        $bloccoFino = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $db->prepare("UPDATE Utenti SET blocco_account_fino_al = ? WHERE id_utente = ?")
            ->execute([$bloccoFino, $userId]);
    }
}

/**
 * Log tentativo fallito
 */
function logFailedAttempt($db, $userId, $email) {
    $db->prepare("
        INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address) 
        VALUES (?, 'LOGIN_FALLITO', ?, INET_ATON(?))
    ")->execute([
        $userId,
        "Tentativo fallito per: $email",
        $_SERVER['REMOTE_ADDR']
    ]);
}