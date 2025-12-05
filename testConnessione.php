<?php


// Abilitare l'autoloader se stai usando Composer (Raccomandato in un progetto MVC)
// Se non usi Composer, dovrai includere manualmente la classe Database.
require __DIR__ . '/vendor/autoload.php';

// 1. Includi il tuo file che carica il .env
// Assumiamo che config/database.php carichi le variabili in $_ENV.
// Includiamo il file database.php.
$dbConfig = require __DIR__ . '/config/database.php';
// Nota: Se il tuo file database.php non popola $_ENV ma solo $dbConfig,
// la classe Database dovrà essere modificata per accettare l'array $dbConfig.

// 2. Definisci l'uso del tuo Namespace e carica la tua classe Database
use Ottaviodipisa\StackMasters\Core\Database;

try {
    echo "Tentativo di connessione al database...\n";

    // Ottiene l'istanza Singleton e la connessione PDO
    // Database::getInstance() è ora disponibile tramite l'autoloader
    $dbInstance = Database::getInstance();
    $pdo = $dbInstance->getConnection();

    // Esegue una query semplice per verificare che la connessione sia attiva
    $stmt = $pdo->query("SELECT NOW() as curr_time");
    $result = $stmt->fetch();

    echo "\n------------------------------------------------\n";
    echo "✅ SUCCESSO: Connessione al database stabilita!\n";
    echo "Ora corrente dal server MySQL: " . $result['curr_time'] . "\n";
    echo "------------------------------------------------\n";

} catch (\PDOException $e) {
    echo "\n❌ ERRORE DI CONNESSIONE (PDO):\n";
    echo "Codice errore: " . $e->getCode() . "\n";
    echo "Messaggio: " . $e->getMessage() . "\n";
    echo "\nControlla: 1. Server MySQL attivo. 2. Credenziali in .env. 3. Nome DB corretto.\n";

} catch (\Exception $e) {
    echo "\n❌ ERRORE GENERICO (Configurazione/Namespace):\n";
    echo "Messaggio: " . $e->getMessage() . "\n";
    echo "\nControlla: 1. L'esecuzione del file config/database.php è avvenuta correttamente.\n";
    echo "2. L'autoloader (vendor/autoload.php) è attivo e funziona.\n";
}