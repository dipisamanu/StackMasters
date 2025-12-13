<?php
// FILE: scripts/cron_scadenze.php

use Ottaviodipisa\StackMasters\Core\Database;
use Dotenv\Dotenv;

// 1. BOOTSTRAP: Carichiamo Composer e Variabili d'Ambiente
// __DIR__ è la cartella 'scripts'. Saliamo di un livello per trovare 'vendor'
require_once __DIR__ . '/../vendor/autoload.php';

// Carichiamo il file .env dalla root del progetto (se esiste)
// La tua classe Database HA BISOGNO di $_ENV popolato
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

try {
    echo "--- [START] Controllo scadenze: " . date('Y-m-d H:i:s') . " ---\n";

    // 2. CONNESSIONE TRAMITE LA TUA CLASSE SINGLETON
    // Non usiamo più new PDO(), ma chiamiamo la tua istanza
    $pdo = Database::getInstance()->getConnection();

} catch (Exception $e) {
    die("Errore Critico: " . $e->getMessage());
}

// -----------------------------------------------------------
// DA QUI IN POI LA LOGICA È IDENTICA A PRIMA
// -----------------------------------------------------------

// A. PREAVVISO (3 giorni alla scadenza)
$sql = "SELECT P.id_utente, L.titolo, U.nome 
        FROM Prestiti P
        JOIN Inventari I ON P.id_inventario = I.id_inventario
        JOIN Libri L ON I.id_libro = L.id_libro
        JOIN Utenti U ON P.id_utente = U.id_utente
        WHERE DATEDIFF(P.scadenza_prestito, NOW()) = 3 AND P.data_restituzione IS NULL";

$stmt = $pdo->query($sql);
while ($row = $stmt->fetch()) {
    creaNotifica($pdo, $row['id_utente'], 'INFO', 'Promemoria Scadenza',
        "Ciao {$row['nome']}, il libro '{$row['titolo']}' scade tra 3 giorni.");
    echo " > Preavviso creato per utente {$row['id_utente']}\n";
}

// B. RITARDO (Scaduto ieri)
$sql = "SELECT P.id_utente, L.titolo, U.nome 
        FROM Prestiti P
        JOIN Inventari I ON P.id_inventario = I.id_inventario
        JOIN Libri L ON I.id_libro = L.id_libro
        JOIN Utenti U ON P.id_utente = U.id_utente
        WHERE DATEDIFF(NOW(), P.scadenza_prestito) = 1 AND P.data_restituzione IS NULL";

$stmt = $pdo->query($sql);
while ($row = $stmt->fetch()) {
    creaNotifica($pdo, $row['id_utente'], 'WARNING', 'Prestito Scaduto',
        "Attenzione {$row['nome']}, '{$row['titolo']}' è scaduto! Restituiscilo subito.");
    echo " > Ritardo segnalato per utente {$row['id_utente']}\n";
}

// Funzione Helper (Resta uguale)
function creaNotifica($pdo, $id_utente, $tipo, $titolo, $messaggio) {
    $check = $pdo->prepare("SELECT id_notifica FROM Notifiche_Web WHERE id_utente = ? AND titolo = ? AND DATE(data_creazione) = CURDATE()");
    $check->execute([$id_utente, $titolo]);

    if($check->rowCount() == 0) {
        $sql = "INSERT INTO Notifiche_Web (id_utente, tipo, titolo, messaggio, stato_email) VALUES (?, ?, ?, ?, 'DA_INVIARE')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_utente, $tipo, $titolo, $messaggio]);
    }
}
echo "--- [END] Controllo terminato ---\n";