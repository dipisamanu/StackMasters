<?php
/**
 * Classe Database - Gestione connessione (Singleton)
 * File: src/config/database.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

class Database
{
    private static $instance = null;
    private $connection;

    // Costruttore privato per il pattern Singleton
    private function __construct()
    {
        // Carica le variabili d'ambiente
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $db_name = $_ENV['DB_NAME'] ?? 'biblioteca_db';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        $port = $_ENV['DB_PORT'] ?? '3396';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $collation = $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci';

        // Data Source Name
        $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=$charset";

        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Imposta il charset manualmente (Compatibile con tutte le versioni PHP)
            $this->connection->exec("SET NAMES '$charset' COLLATE '$collation'");

        } catch (PDOException $e) {
            die("Errore di Connessione Database: " . $e->getMessage());
        }
    }

    // Metodo statico per ottenere l'istanza
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Restituisce la connessione PDO
    public function getConnection()
    {
        return $this->connection;
    }
}

// Funzione helper per ottenere la connessione PDO
function getDB(): PDO
{
    return Database::getInstance()->getConnection();
}
