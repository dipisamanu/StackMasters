<?php
/**
 * CRON JOB: Controllo Scadenze
 * Esecuzione: php scripts/cron_scadenze.php
 */

use Ottaviodipisa\StackMasters\Models\NotificationManager;
use Dotenv\Dotenv;

// 1. Caricamento Autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Caricamento Variabili Ambiente
if (file_exists(__DIR__ . '/../.env')) {
    try {
        $dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();
    } catch (Exception $e) {
        echo "Warning .env: " . $e->getMessage() . "\n";
    }
}

// 3. IMPORTANTE: Inclusione manuale della classe Database
// Dato che è globale e fuori dallo standard PSR-4, va richiesta esplicitamente.
require_once __DIR__ . '/../src/config/database.php';

try {
    echo "--- [START] Controllo scadenze: " . date('Y-m-d H:i:s') . " ---\n";

    // 4. Istanza Database (con \ davanti per indicare che è globale)
    $pdo = \Database::getInstance()->getConnection();

    // 5. Istanza NotificationManager
    $notify = new NotificationManager();

    // -----------------------------------------------------------
    // A. PREAVVISO (3 giorni alla scadenza - Epic 8.2)
    // -----------------------------------------------------------
    $sqlPre = "SELECT P.id_utente, L.titolo, P.scadenza_prestito 
               FROM prestiti P 
               JOIN inventari I ON P.id_inventario = I.id_inventario
               JOIN libri L ON I.id_libro = L.id_libro
               WHERE P.data_restituzione IS NULL 
               AND DATE(P.scadenza_prestito) = DATE(NOW() + INTERVAL 3 DAY)";

    foreach ($pdo->query($sqlPre) as $row) {
        $notify->send(
            $row['id_utente'],
            NotificationManager::TYPE_REMINDER,
            NotificationManager::URGENCY_LOW,
            "Scadenza Imminente",
            "Il libro '{$row['titolo']}' scade il " . date('d/m/Y', strtotime($row['scadenza_prestito'])),
            "/dashboard/student/index.php"
        );
        echo " > Preavviso inviato a Utente ID: {$row['id_utente']}\n";
    }

    // -----------------------------------------------------------
    // B. RITARDO (Scaduto ieri - Epic 8.3)
    // -----------------------------------------------------------
    $sqlLate = "SELECT P.id_utente, L.titolo 
                FROM prestiti P 
                JOIN inventari I ON P.id_inventario = I.id_inventario
                JOIN libri L ON I.id_libro = L.id_libro
                WHERE P.data_restituzione IS NULL 
                AND DATE(P.scadenza_prestito) = DATE(NOW() - INTERVAL 1 DAY)";

    foreach ($pdo->query($sqlLate) as $row) {
        $notify->send(
            $row['id_utente'],
            NotificationManager::TYPE_REMINDER,
            NotificationManager::URGENCY_HIGH,
            "PRESTITO SCADUTO",
            "Il prestito di '{$row['titolo']}' è scaduto ieri. Restituiscilo subito per evitare multe.",
            "/dashboard/student/index.php"
        );
        echo " > Avviso ritardo inviato a Utente ID: {$row['id_utente']}\n";
    }

    echo "--- [END] Controllo completato ---\n";

} catch (Exception $e) {
    echo "ERRORE CRITICO: " . $e->getMessage() . "\n";
}