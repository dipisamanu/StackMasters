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

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db_name = $_ENV['DB_NAME'] ?? 'biblioteca_db';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        $port = $_ENV['DB_PORT'] ?? '3306';

        // Data Source Name
        $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";

        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // RIMOSSO: PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                // Questo causava l'errore Deprecated su PHP 8.4+
            ]);

            // Imposta il charset manualmente (Compatibile con tutte le versioni PHP)
            $this->connection->exec("SET NAMES 'utf8mb4'");

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

function getDB(): PDO
{
    return Database::getInstance()->getConnection();
}