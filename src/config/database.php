<?php

namespace Ottaviodipisa\StackMasters\Config;

// Configurazione database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'biblioteca_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Cambia con la tua password MySQL
define('DB_CHARSET', 'utf8mb4');

// Classe Database con PDO
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
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

// Funzione helper per ottenere la connessione rapidamente
function getDB() {
    return Database::getInstance()->getConnection();
}