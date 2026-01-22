<?php

// install.php

// 1. Carica le dipendenze e le configurazioni
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../db/add_fines_sql.php'; // Carica la funzione

try {
    // 2. Connessione al Database
    $pdo = Database::getInstance()->getConnection();
    echo "Connessione al database stabilita.<br>";

    // 3. Esecuzione di install.sql
    $sqlFile = __DIR__ . '/../db/install.sql';
    if (file_exists($sqlFile)) {
        $sqlContent = file_get_contents($sqlFile);
        
        $lines = explode("\n", $sqlContent);
        $buffer = '';
        $delimiter = ';';
        
        foreach ($lines as $line) {
            $trimLine = trim($line);
            
            // Skip comments (lines starting with --)
            if (strpos($trimLine, '--') === 0) {
                continue;
            }
            
            // Skip empty lines if buffer is empty
            if (empty($trimLine) && empty(trim($buffer))) {
                continue;
            }
            
            // Handle DELIMITER command
            if (preg_match('/^DELIMITER\s+(\S+)/i', $trimLine, $matches)) {
                $delimiter = $matches[1];
                continue;
            }
            
            $buffer .= $line . "\n";
            
            // Check if the buffer ends with the current delimiter
            if (substr($trimLine, -strlen($delimiter)) === $delimiter) {
                $sqlToRun = trim($buffer);
                // Remove the delimiter from the end
                if (substr($sqlToRun, -strlen($delimiter)) === $delimiter) {
                    $sqlToRun = substr($sqlToRun, 0, -strlen($delimiter));
                }
                
                if (!empty(trim($sqlToRun))) {
                    $pdo->exec($sqlToRun);
                }
                $buffer = '';
            }
        }
        echo "File install.sql eseguito con successo.<br>";
    } else {
        echo "Attenzione: File install.sql non trovato. Saltato.<br>";
    }

    // 4. Esecuzione del Seeder
    echo "Esecuzione di seeder_full_test.php...<br>";
    // Il seeder si connette già da solo, basta includerlo
    include __DIR__ . '/../db/seeder_full_test.php';
    echo "Seeder completato.<br>";

    // 5. Esecuzione di add_fines
    echo "Aggiunta delle multe di test...<br>";
    add_fines($pdo); // Chiama la funzione passando la connessione
    echo "Multe aggiunte.<br>";

    // 6. Reindirizzamento
    echo "Installazione completata. Reindirizzamento a public/index.php...<br>";
    // Usa JavaScript per il redirect per evitare l'errore "headers already sent"
    echo '<script>setTimeout(function(){ window.location.href = "../public/index.php"; }, 7500);</script>';
    echo '<p>Se non vieni reindirizzato automaticamente, <a href="../public/index.php">clicca qui</a>.</p>';
    exit;

} catch (PDOException $e) {
    die("Errore di connessione al database o esecuzione query: " . $e->getMessage());
} catch (Exception $e) {
    die("Errore durante l'installazione: " . $e->getMessage());
}
?>