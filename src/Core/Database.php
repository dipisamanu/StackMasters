<?php
// FILE: Database.php

namespace Ottaviodipisa\StackMasters\Core;

/**
 * Singleton per la gestione della connessione al database tramite PDO.
 * Assume che le variabili d'ambiente (da .env) siano state caricate.
 */
class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;

    /**
     * Costruttore privato (per Singleton).
     * Inizializza la connessione PDO utilizzando le variabili d'ambiente ($_ENV).
     * @throws \PDOException| \Exception
     */
    private function __construct()
    {
        try {
            // Assumiamo che il file esterno abbia popolato $_ENV prima della chiamata al Singleton.

            $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
            $dbName = $_ENV['DB_NAME'] ?? 'biblioteca_db';
            $dbUser = $_ENV['DB_USER'] ?? 'root';
            $dbPass = $_ENV['DB_PASS'] ?? '';

            // La stringa DSN include tutti i parametri essenziali
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // 3. Stabilisce la connessione PDO (4 argomenti totali: DSN, User, Pass, Options)
            $this->pdo = new \PDO($dsn, $dbUser, $dbPass, $options);

        } catch (\PDOException $e) {
            // Lancia l'eccezione PDO
            throw new \PDOException("Errore di connessione al DB: " . $e->getMessage(), (int)$e->getCode());
        } catch (\Exception $e) {
            // Rilancia altre eccezioni
            throw $e;
        }
    }

    /**
     * Restituisce l'istanza Singleton della classe Database.
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Restituisce l'oggetto connessione PDO.
     * @return \PDO
     */
    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

}