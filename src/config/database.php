<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Carica .env (se presente) dalla root del progetto
$projectRoot = dirname(__DIR__, 2);
if (file_exists($projectRoot . '/.env')) {
    try {
        Dotenv::createImmutable($projectRoot)->load();
    } catch (Exception $e) {
        throw new Exception("Errore caricamento .env: " . $e->getMessage());
    }
}
function getEnvVar(string $key): string
{
    if (!isset($_ENV[$key])) {
        throw new Exception("Variabile di ambiente '$key' non definita in .env");
    }
    return $_ENV[$key];
}

// Configurazione database da .env
$dbHost = getEnvVar('DB_HOST');
$dbName = getEnvVar('DB_NAME');
$dbUser = getEnvVar('DB_USER');
$dbPass = getEnvVar('DB_PASS');

// Classe Database con PDO
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = "mysql:host=" . $GLOBALS['dbHost'] . ";dbname=" . $GLOBALS['dbName'] . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];

        try {
            $this->pdo = new PDO($dsn, $GLOBALS['dbUser'], $GLOBALS['dbPass'], $options);
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

    public static function getInstance(): ?Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}

function getDB(): PDO
{
    return Database::getInstance()->getConnection();
}