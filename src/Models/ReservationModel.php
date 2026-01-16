<?php
/**
 * Gestione delle Prenotazioni
 * File: src/Models/ReservationModel.php
 */

require_once __DIR__ . '/../config/database.php';

class ReservationModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Verifica se l'utente ha una prenotazione attiva per questo libro.
     */
    public function hasActiveReservation(int $userId, int $bookId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM prenotazioni 
            WHERE id_utente = :uid 
              AND id_libro = :bid
              AND (
                  copia_libro IS NULL 
                  OR (copia_libro IS NOT NULL AND scadenza_ritiro > NOW())
              )
        ");
        $stmt->execute(['uid' => $userId, 'bid' => $bookId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Crea una nuova prenotazione in coda (FIFO)
     */
    public function createReservation(int $userId, int $bookId): bool
    {
        try {
            // Verifica ulteriore di sicurezza per evitare duplicati
            if ($this->hasActiveReservation($userId, $bookId)) {
                return false;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO prenotazioni (id_utente, id_libro, data_richiesta) 
                VALUES (:uid, :bid, NOW())
            ");

            return $stmt->execute([
                ':uid' => $userId,
                ':bid' => $bookId
            ]);
        } catch (PDOException $e) {
            error_log("Errore creazione prenotazione: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Conta quante persone ci sono in coda per un libro
     */
    public function getQueuePosition(int $bookId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM prenotazioni 
            WHERE id_libro = ? AND copia_libro IS NULL
        ");
        $stmt->execute([$bookId]);
        return (int)$stmt->fetchColumn();
    }
}