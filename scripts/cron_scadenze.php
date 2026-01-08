<?php
/**
 * CRON JOB: Controllo Scadenze (Esecuzione giornaliera)
 * Invia preavvisi (3 giorni prima) e avvisi di ritardo (scaduto ieri).
 */

use Ottaviodipisa\StackMasters\Core\Database;
use Ottaviodipisa\StackMasters\Models\NotificationManager;
use Dotenv\Dotenv;

// 1. Caricamento dipendenze
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Caricamento variabili d'ambiente (.env)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

try {
    echo "--- [START] Controllo scadenze: " . date('Y-m-d H:i:s') . " ---\n";

    $notify = new NotificationManager();
    $pdo = Database::getInstance()->getConnection();

    // -----------------------------------------------------------
    // A. PREAVVISO (3 giorni alla scadenza - Epic 8.2)
    // -----------------------------------------------------------
    $sqlPre = "SELECT P.id_utente, L.titolo, P.scadenza_prestito 
               FROM Prestiti P 
               JOIN Inventari I ON P.id_inventario = I.id_inventario
               JOIN Libri L ON I.id_libro = L.id_libro
               WHERE P.data_restituzione IS NULL 
               AND DATE(P.scadenza_prestito) = DATE(NOW() + INTERVAL 3 DAY)";

    foreach ($pdo->query($sqlPre) as $row) {
        $notify->send(
            $row['id_utente'],
            NotificationManager::TYPE_REMINDER,
            NotificationManager::URGENCY_LOW, // Bassa urgenza (rispetta quiet hours)
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
                FROM Prestiti P 
                JOIN Inventari I ON P.id_inventario = I.id_inventario
                JOIN Libri L ON I.id_libro = L.id_libro
                WHERE P.data_restituzione IS NULL 
                AND DATE(P.scadenza_prestito) = DATE(NOW() - INTERVAL 1 DAY)";

    foreach ($pdo->query($sqlLate) as $row) {
        $notify->send(
            $row['id_utente'],
            NotificationManager::TYPE_REMINDER,
            NotificationManager::URGENCY_HIGH, // Alta urgenza (ignora quiet hours)
            "PRESTITO SCADUTO",
            "Il prestito di '{$row['titolo']}' è scaduto ieri. Restituiscilo subito per evitare multe.",
            "/dashboard/student/index.php"
        );
        echo " > Avviso ritardo inviato a Utente ID: {$row['id_utente']}\n";
    }

    // -----------------------------------------------------------
    // C. ESCALATION GRAVE (Ritardo > 14 giorni - Epic 8.5)
    // -----------------------------------------------------------
    $sqlEscalation = "SELECT P.id_utente, L.titolo 
                      FROM Prestiti P 
                      JOIN Inventari I ON P.id_inventario = I.id_inventario
                      JOIN Libri L ON I.id_libro = L.id_libro
                      WHERE P.data_restituzione IS NULL 
                      AND DATE(P.scadenza_prestito) = DATE(NOW() - INTERVAL 14 DAY)";

    foreach ($pdo->query($sqlEscalation) as $row) {
        $notify->send(
            $row['id_utente'],
            NotificationManager::TYPE_REMINDER,
            NotificationManager::URGENCY_HIGH, // Altissima priorità
            "⚠️ ULTIMO AVVISO: Grave Ritardo",
            "Il libro '{$row['titolo']}' è scaduto da 2 settimane. L'account è sospeso e sono in corso azioni amministrative. Restituisci subito il volume.",
            "/dashboard/student/index.php"
        );
        echo " > Escalation inviata a Utente ID: {$row['id_utente']}\n";
    }

    echo "--- [END] Controllo completato ---\n";

} catch (Exception $e) {
    echo "ERRORE CRITICO: " . $e->getMessage() . "\n";
}