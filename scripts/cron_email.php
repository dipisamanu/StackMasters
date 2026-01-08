
<?php
/**
 * FILE: scripts/cron_email.php
 * DESCRIZIONE: Legge le notifiche 'DA_INVIARE' e simula l'invio email scrivendo su file log.
 */

use Ottaviodipisa\StackMasters\Core\Database;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

try {
    $pdo = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Errore DB: " . $e->getMessage());
}

// Percorso del file di log dove simuliamo l'invio
$logFile = __DIR__ . '/../logs/email_simulate.txt';

// Query: Prendi le email da inviare (Limit 20 per sicurezza)
$sql = "SELECT N.*, U.email, U.nome FROM Notifiche_Web N
        JOIN Utenti U ON N.id_utente = U.id_utente
        WHERE N.stato_email = 'DA_INVIARE' LIMIT 20";

$stmt = $pdo->query($sql);
$count = 0;

while ($row = $stmt->fetch()) {
    // --- SIMULAZIONE INVIO ---
    $txt = "=== EMAIL LOG " . date('Y-m-d H:i:s') . " ===\n";
    $txt .= "To: {$row['email']} ({$row['nome']})\n";
    $txt .= "Subject: {$row['titolo']}\n";
    $txt .= "Body: {$row['messaggio']}\n";
    $txt .= "=======================================\n\n";

    // Scrittura su file (FILE_APPEND aggiunge in coda)
    file_put_contents($logFile, $txt, FILE_APPEND);

    // --- AGGIORNAMENTO DB ---
    // Segniamo come INVIATA per non spedirla due volte
    $upd = $pdo->prepare("UPDATE Notifiche_Web SET stato_email = 'INVIATA', data_invio_email = NOW() WHERE id_notifica = ?");
    $upd->execute([$row['id_notifica']]);

    $count++;
}

echo "Inviate $count email. Controlla la cartella logs.";
