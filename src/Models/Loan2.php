<?php

/**
 * Loan2 Model - Gestione entità Prestiti (versione semplificata)
 * File: src/Models/Loan2.php
 */

class Loan2
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crea un nuovo prestito
     *
     * @param int $idInventario ID della copia
     * @param int $idUtente ID dell'utente
     * @param int $durataGiorni Durata del prestito in giorni
     * @return bool Successo dell'operazione
     */
    public function creaPrestitio(int $idInventario, int $idUtente, int $durataGiorni): bool
    {
        $dataScadenza = date('Y-m-d H:i:s', strtotime("+$durataGiorni days"));

        $sql = "INSERT INTO Prestiti (id_inventario, id_utente, scadenza_prestito) 
                VALUES (?, ?, ?)";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$idInventario, $idUtente, $dataScadenza]);
        } catch (\PDOException $e) {
            error_log("Errore creazione prestito: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restituisce un prestito
     *
     * @param int $idPrestito ID del prestito
     * @return bool Successo dell'operazione
     */
    public function restituisciPrestito(int $idPrestito): bool
    {
        $sql = "UPDATE Prestiti 
                SET data_restituzione = NOW() 
                WHERE id_prestito = ?";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$idPrestito]);
        } catch (\PDOException $e) {
            error_log("Errore restituzione prestito: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recupera prestiti attivi di un utente
     *
     * @param int $idUtente ID dell'utente
     * @return array Elenco prestiti attivi
     */
    public function getPrestitiAttivi(int $idUtente): array
    {
        $sql = "SELECT p.id_prestito, p.data_prestito, p.scadenza_prestito, 
                       l.titolo, i.id_inventario
                FROM Prestiti p
                JOIN Inventari i ON p.id_inventario = i.id_inventario
                JOIN Libri l ON i.id_libro = l.id_libro
                WHERE p.id_utente = ? AND p.data_restituzione IS NULL
                ORDER BY p.scadenza_prestito ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idUtente]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Errore recupero prestiti attivi: " . $e->getMessage());
            return [];
        }
    }
}

