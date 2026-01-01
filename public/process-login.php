<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../src/config/database.php';
require_once '../src/config/session.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

try {
    $db = getDB();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Validazione base
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Email e password sono obbligatorie';
        header('Location: login.php');
        exit;
    }

    // 1. Cerca utente
    $stmt = $db->prepare("
        SELECT 
            id_utente, 
            nome,      
            cognome,
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
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Verifica esistenza utente
    if (!$user) {
        $_SESSION['login_error'] = 'Credenziali non valide';
        header('Location: login.php');
        exit;
    }

    // 3. Verifica blocco account
    if ($user['blocco_account_fino_al'] && strtotime($user['blocco_account_fino_al']) > time()) {
        $minutiRimasti = ceil((strtotime($user['blocco_account_fino_al']) - time()) / 60);
        $_SESSION['login_error'] = "Account temporaneamente bloccato. Riprova tra $minutiRimasti minuti.";
        header('Location: login.php');
        exit;
    }

    // 4. Verifica password
    if (!password_verify($password, $user['password'])) {
        $newAttempts = ($user['tentativi_login_falliti'] ?? 0) + 1;
        if ($newAttempts >= 5) {
            $blockUntil = date('Y-m-d H:i:s', time() + 900); // 15 minuti
            $stmt = $db->prepare("UPDATE Utenti SET tentativi_login_falliti = ?, blocco_account_fino_al = ? WHERE id_utente = ?");
            $stmt->execute([$newAttempts, $blockUntil, $user['id_utente']]);
            $_SESSION['login_error'] = 'Troppi tentativi falliti. Account bloccato per 15 minuti.';
        } else {
            $stmt = $db->prepare("UPDATE Utenti SET tentativi_login_falliti = ? WHERE id_utente = ?");
            $stmt->execute([$newAttempts, $user['id_utente']]);
            $tentativiRimasti = 5 - $newAttempts;
            $_SESSION['login_error'] = "Credenziali non valide. Tentativi rimasti: $tentativiRimasti";
        }
        header('Location: login.php');
        exit;
    }

    // 5. Verifica email verificata
    if (!$user['email_verificata']) {
        $_SESSION['login_error'] = 'Devi verificare la tua email prima di accedere.';
        header('Location: login.php');
        exit;
    }

    // 6. Reset tentativi falliti
    $stmt = $db->prepare("UPDATE Utenti SET tentativi_login_falliti = 0, blocco_account_fino_al = NULL WHERE id_utente = ?");
    $stmt->execute([$user['id_utente']]);

    // 7. Recupera ruoli utente
    $stmtRuoli = $db->prepare("
        SELECT r.id_ruolo, r.nome, r.priorita, r.durata_prestito, r.limite_prestiti
        FROM Ruoli r
        INNER JOIN Utenti_Ruoli ur ON r.id_ruolo = ur.id_ruolo
        WHERE ur.id_utente = ?
        ORDER BY r.priorita ASC
    ");
    $stmtRuoli->execute([$user['id_utente']]);
    $ruoli = $stmtRuoli->fetchAll(PDO::FETCH_ASSOC);

    // Se non ha ruoli, assegna "Studente" di default
    if (empty($ruoli)) {
        $stmtDefaultRole = $db->prepare("SELECT id_ruolo FROM Ruoli WHERE nome = 'Studente' LIMIT 1");
        $stmtDefaultRole->execute();
        $defaultRole = $stmtDefaultRole->fetch(PDO::FETCH_ASSOC);

        if ($defaultRole) {
            $db->prepare("INSERT INTO Utenti_Ruoli (id_utente, id_ruolo) VALUES (?, ?)")
                ->execute([$user['id_utente'], $defaultRole['id_ruolo']]);
            $ruoli = [$defaultRole];
        }
    }

    // Log successo
    try {
        $db->prepare("
            INSERT INTO Logs_Audit (id_utente, azione, dettagli) 
            VALUES (?, 'LOGIN_SUCCESS', ?)
        ")->execute([
            $user['id_utente'],
            "Login effettuato da: " . $email
        ]);
    } catch (Exception $e) {
        // Non blocca se il log fallisce
    }

    // Crea sessione usando la classe centralizzata
    $nomeCompleto = $user['nome'] . ' ' . $user['cognome'];
    Session::login($user['id_utente'], $nomeCompleto, $user['email'], $ruoli);

    // Reindirizza alla dashboard appropriata
    $mainRole = $ruoli[0]['nome'] ?? 'Studente';

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

} catch (PDOException $e) {
    error_log("ERRORE PDO LOGIN: " . $e->getMessage());
    $_SESSION['login_error'] = 'Errore del database. Riprova più tardi.';
    header('Location: login.php');
    exit;

} catch (Exception $e) {
    error_log("ERRORE GENERICO LOGIN: " . $e->getMessage());
    $_SESSION['login_error'] = 'Errore durante il login. Riprova più tardi.';
    header('Location: login.php');
    exit;
}
?>
