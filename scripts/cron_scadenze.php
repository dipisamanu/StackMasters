<?php
/**
 * CRON JOB: Controllo Scadenze (Debug Mode)
 */

use Ottaviodipisa\StackMasters\Models\NotificationManager;
use Ottaviodipisa\StackMasters\Services\LoanService;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    try {
        $dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();
    } catch (Exception $e) {
        echo "Warning .env: " . $e->getMessage() . "\n";
    }
}

require_once __DIR__ . '/../src/config/database.php';

try {
    echo "--- [START] Controllo scadenze: " . date('Y-m-d H:i:s') . " ---\n";

    $pdo = \Database::getInstance()->getConnection();
    // Impostiamo PDO per lanciare eccezioni sempre, utile per il debug
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $notify = new NotificationManager();

    /**
     * Helper interno per processare le query ed isolare gli errori
     */
    $runCheck = function ($sql, $label, $urgency) use ($notify, $pdo) {
        echo "\n[INFO] Analisi: $label\n";

        $stmt = $pdo->query($sql);
        $count = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            try {
                echo "  > Invio a Utente ID {$row['id_utente']} ('{$row['titolo']}')... ";

                $notify->send(
                    $row['id_utente'],
                    NotificationManager::TYPE_REMINDER,
                    $urgency,
                    ($label === "PREAVVISO" ? "Scadenza Imminente" : "PRESTITO SCADUTO"),
                    "Il libro '{$row['titolo']}' " . ($label === "PREAVVISO" ? "scade a breve." : "è scaduto."),
                    "/dashboard/student/index.php"
                );

                echo "OK\n";
            } catch (Exception $e) {
                echo "ERRORE!\n";
                echo "    ----------------------------------------------------------\n";
                echo "    DETTAGLI ERRORE: " . $e->getMessage() . "\n";
                echo "    FILE: " . $e->getFile() . " (Linea: " . $e->getLine() . ")\n";
                echo "    TRACE: " . substr($e->getTraceAsString(), 0, 500) . "...\n";
                echo "    ----------------------------------------------------------\n";
                // Continuiamo il ciclo per gli altri utenti
            }
        }
        echo "[INFO] $label completato. Processati $count record.\n";
    };

    // PREAVVISO (3 giorni)
    $sqlPre = "SELECT P.id_utente, L.titolo, P.scadenza_prestito 
               FROM prestiti P 
               JOIN inventari I ON P.id_inventario = I.id_inventario
               JOIN libri L ON I.id_libro = L.id_libro
               WHERE P.data_restituzione IS NULL 
               AND DATE(P.scadenza_prestito) = DATE(NOW() + INTERVAL 3 DAY)";

    $runCheck($sqlPre, "PREAVVISO", NotificationManager::URGENCY_LOW);

    // RITARDO (1 giorno fa)
    $sqlLate = "SELECT P.id_utente, L.titolo 
                FROM prestiti P 
                JOIN inventari I ON P.id_inventario = I.id_inventario
                JOIN libri L ON I.id_libro = L.id_libro
                WHERE P.data_restituzione IS NULL 
                AND DATE(P.scadenza_prestito) = DATE(NOW() - INTERVAL 1 DAY)";

    $runCheck($sqlLate, "RITARDO", NotificationManager::URGENCY_HIGH);

    // GESTIONE PRENOTAZIONI SCADUTE
    echo "\n[INFO] Controllo Prenotazioni Scadute\n";
    $loanService = new LoanService();
    $logPrenotazioni = $loanService->gestisciPrenotazioniScadute();
    foreach ($logPrenotazioni as $msg) {
        echo "  > $msg\n";
    }
    echo "[INFO] Controllo Prenotazioni completato.\n";

    echo "\n--- [END] Cron job terminato ---\n";

} catch (Exception $e) {
    echo "\n!!! ERRORE CRITICO DI SISTEMA !!!\n";
    echo $e->getMessage() . "\n";
}