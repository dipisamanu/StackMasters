<?php
require_once __DIR__ . '/../src/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Recupera un utente specifico (es. ID 5 o un altro ID valido)
    // Modifica l'ID qui sotto se vuoi un utente diverso
    $targetUserId = 5; 
    
    $stmt = $db->prepare("SELECT id_utente, nome, cognome FROM utenti WHERE id_utente = ?");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Se l'utente 5 non esiste, prendine uno a caso
        $stmt = $db->query("SELECT id_utente, nome, cognome FROM utenti ORDER BY RAND() LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        die("Nessun utente trovato nel database.");
    }

    echo "<h3>Query SQL generate per l'utente: {$user['nome']} {$user['cognome']} (ID: {$user['id_utente']})</h3>";
    echo "<textarea style='width: 100%; height: 400px; font-family: monospace;'>";

    $userId = $user['id_utente'];
    $numFines = 5; // Genera 5 multe per questo utente

    for ($i = 0; $i < $numFines; $i++) {
        $amount = rand(10, 100) + (rand(0, 99) / 100); // Importo casuale
        
        $causes = ['RITARDO', 'DANNI'];
        $cause = $causes[array_rand($causes)];
        
        $desc = ($cause === 'RITARDO') ? "Ritardo restituzione libro importante" : "Danneggiamento copertina";
        $daysAgo = rand(1, 30);
        
        echo "INSERT INTO multe (id_utente, importo, causa, commento, data_creazione) VALUES ($userId, $amount, '$cause', '$desc', DATE_SUB(NOW(), INTERVAL $daysAgo DAY));\n";
    }
    
    echo "</textarea>";

} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}
?>