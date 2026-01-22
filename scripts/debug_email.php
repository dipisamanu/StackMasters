<?php
/**
 * Script di Debug per Testare i Template Email
 * Esegui da terminale: php scripts/debug_email.php [email_destinatario]
 * Oppure apri nel browser: http://localhost/StackMasters/scripts/debug_email.php?email=tua@email.com
 */

require_once __DIR__ . '/../src/config/email.php';
require_once __DIR__ . '/../src/Models/NotificationManager.php';
require_once __DIR__ . '/../src/config/database.php';

use Ottaviodipisa\StackMasters\Models\NotificationManager;

// Rileva ambiente (CLI o Web)
$isCli = (php_sapi_name() === 'cli');
$nl = $isCli ? "\n" : "<br>";

// Configurazione Email Destinatario
$testEmail = 'test@example.com';
if ($isCli && isset($argv[1])) {
    $testEmail = $argv[1];
} elseif (!$isCli && isset($_GET['email'])) {
    $testEmail = $_GET['email'];
}

// Funzioni di output
function printHeader($title): void
{
    global $isCli, $nl;
    if ($isCli) {
        echo "\n\033[1;34m=== $title ===\033[0m$nl";
    } else {
        echo "<h2 style='color: #0056b3; border-bottom: 2px solid #0056b3; padding-bottom: 10px;'>$title</h2>";
    }
}

function printStatus($step, $desc, $success, $error = ''): void
{
    global $isCli, $nl;
    $status = $success ? ($isCli ? "\033[1;32m[OK]\033[0m" : "<span style='color:green; font-weight:bold;'>[OK]</span>")
        : ($isCli ? "\033[1;31m[FALLITO]\033[0m" : "<span style='color:red; font-weight:bold;'>[FALLITO]</span>");

    if ($isCli) {
        echo sprintf(" %-50s %s%s", "$step. $desc", $status, $nl);
        if (!$success && $error) echo "\033[0;31m    -> Errore: $error\033[0m$nl";
    } else {
        echo "<div style='margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-left: 4px solid " . ($success ? "green" : "red") . ";'>";
        echo "<strong>$step. $desc</strong> <div style='float:right'>$status</div>";
        if (!$success && $error) echo "<div style='color: #dc3545; margin-top: 5px; font-family: monospace;'>$error</div>";
        echo "</div>";
    }
}

// Inizio Output
if (!$isCli) echo "<body style='font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";

printHeader("📧 EMAIL DEBUG TOOL");
echo "Destinatario: <strong>$testEmail</strong>$nl$nl";

try {
    // Helper per ottenere un servizio fresco ogni volta
    $getService = function () {
        return getEmailService(true);
    };

    // Test Email Verifica
    $svc1 = $getService();
    $res1 = $svc1->sendVerificationEmail($testEmail, "Mario Rossi", "token_di_prova_123");
    printStatus(1, "Email Verifica Account", $res1, $svc1->getLastError());

    // Test Conferma Prestito
    $svc2 = $getService();
    $res2 = $svc2->sendLoanConfirmation($testEmail, "Mario Rossi", "Il Signore degli Anelli", "15/02/2026");
    printStatus(2, "Conferma Prestito", $res2, $svc2->getLastError());

    // Test Prenotazione Disponibile
    $svc3 = $getService();
    $res3 = $svc3->sendReservationAvailable($testEmail, "Mario Rossi", "1984", "17/01/2026 18:30");
    printStatus(3, "Prenotazione Disponibile", $res3, $svc3->getLastError());

    // Test Notifica Generica (NotificationManager)
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id_utente FROM utenti WHERE email = ? LIMIT 1");
    $stmt->execute([$testEmail]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        if ($isCli) echo "    (Creo utente temporaneo per il test)...$nl";
        $stmtIns = $db->prepare("INSERT INTO utenti (cf, nome, cognome, email, password, notifiche_attive) VALUES (?, ?, ?, ?, ?, 1)");
        $cfFake = 'TST' . strtoupper(substr(md5(time()), 0, 13));
        $stmtIns->execute([$cfFake, 'Test', 'User', $testEmail, password_hash('password', PASSWORD_DEFAULT)]);
        $userId = $db->lastInsertId();
    }

    if ($userId) {
        $notifier = new NotificationManager();
        // Invio normale (senza forceNoEmail) -> Dovrebbe inviare l'email generica
        $res4 = $notifier->send(
            (int)$userId,
            'INFO',
            NotificationManager::URGENCY_LOW,
            'Test Notifica Generica',
            'Questa è una notifica di sistema generica inviata dallo script di debug.',
            null,
            false // forceNoEmail = false, quindi INVIA l'email
        );
        // Nota: NotificationManager non espone l'errore email direttamente, ma lo logga.
        // Assumiamo successo se il metodo ritorna true (che significa notifica salvata).
        // Per verificare l'invio email reale, bisogna controllare i log o la casella.
        printStatus(4, "Notifica Generica (NotificationManager)", $res4, "Controlla i log se l'email non arriva");
    } else {
        printStatus(4, "Notifica Generica", false, "Impossibile creare utente nel DB");
    }

    printHeader("TEST COMPLETATO");
    echo "Controlla la tua casella di posta (o Mailtrap) per verificare la ricezione e la formattazione.$nl";

} catch (Exception $e) {
    echo $nl . ($isCli ? "\033[1;41m ERRORE CRITICO \033[0m" : "<div style='background:red; color:white; padding:10px'>ERRORE CRITICO</div>") . $nl;
    echo $e->getMessage() . $nl;
}

if (!$isCli) echo "</body>";
