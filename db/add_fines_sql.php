<?php
function add_fines($pdo) {
    try {
        // Recupera un utente specifico (es. ID 5 o un altro ID valido)
        $targetUserId = 5; 
        
        $stmt = $pdo->prepare("SELECT id_utente, nome, cognome FROM utenti WHERE id_utente = ?");
        $stmt->execute([$targetUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Se l'utente 5 non esiste, prendine uno a caso
            $stmt = $pdo->query("SELECT id_utente, nome, cognome FROM utenti ORDER BY RAND() LIMIT 1");
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$user) {
            echo "Nessun utente trovato nel database per aggiungere multe.<br>";
            return;
        }

        echo "Aggiunta multe per l'utente: {$user['nome']} {$user['cognome']} (ID: {$user['id_utente']})<br>";

        $userId = $user['id_utente'];
        $numFines = 5; // Genera 5 multe per questo utente

        for ($i = 0; $i < $numFines; $i++) {
            $amount = rand(10, 100) + (rand(0, 99) / 100); // Importo casuale
            
            $causes = ['RITARDO', 'DANNI'];
            $cause = $causes[array_rand($causes)];
            
            $desc = ($cause === 'RITARDO') ? "Ritardo restituzione libro importante" : "Danneggiamento copertina";
            $daysAgo = rand(1, 30);
            
            $sql = "INSERT INTO multe (id_utente, importo, causa, commento, data_creazione) VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $amount, $cause, $desc, $daysAgo]);
        }
        
        echo "Aggiunte $numFines multe.<br>";

    } catch (Exception $e) {
        echo "Errore durante l'aggiunta delle multe: " . $e->getMessage() . "<br>";
    }
}

// Se il file viene eseguito direttamente, connettiti al DB e lancia la funzione
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    require_once __DIR__ . '/../src/config/database.php';
    try {
        $pdo = Database::getInstance()->getConnection();
        add_fines($pdo);
    } catch (Exception $e) {
        die("Errore di connessione al database: " . $e->getMessage());
    }
}
?>