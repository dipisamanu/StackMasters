<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Carica .env (se presente) dalla root del progetto
$projectRoot = dirname(__DIR__, 2);
if (file_exists($projectRoot . '/.env')) {
    try {
        Dotenv::createImmutable($projectRoot)->load();
    } catch (\Throwable $e) {
        throw new \Exception("Errore caricamento .env: " . $e->getMessage());
    }
}

// Configurazione database da .env (senza fallback - obbligatorio)
if (empty($_ENV['DB_HOST'])) {
    throw new \Exception("Variabile DB_HOST non definita in .env");
}
if (empty($_ENV['DB_DATABASE'])) {
    throw new \Exception("Variabile DB_DATABASE non definita in .env");
}
if (empty($_ENV['DB_USERNAME'])) {
    throw new \Exception("Variabile DB_USERNAME non definita in .env");
}

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_DATABASE'];
$dbUser = $_ENV['DB_USERNAME'];
$dbPass = $_ENV['DB_PASSWORD'] ?? '';
$dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// Classe Database con PDO
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dsn = "mysql:host=" . $GLOBALS['dbHost'] . ";dbname=" . $GLOBALS['dbName'] . ";charset=" . $GLOBALS['dbCharset'];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $GLOBALS['dbUser'], $GLOBALS['dbPass'], $options);
        } catch (PDOException $e) {
            // Log dell'errore in un file invece di mostrarlo a video in produzione
            error_log("Errore connessione database: " . $e->getMessage());

            // In sviluppo mostra l'errore, in produzione mostra messaggio generico
            if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
                throw new Exception("Errore SQL specifico: " . $e->getMessage());
            } else {
                throw new Exception("Impossibile connettersi al database. Riprova più tardi.");
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}
function getDB() {
    return Database::getInstance()->getConnection();
}