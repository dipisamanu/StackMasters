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
     * Verifica se l'utente ha una prenotazione attiva.
     * Una prenotazione è attiva se:
     * 1. È in coda (copia_libro NULL)
     * 2. È assegnata ma non ancora scaduta (scadenza_ritiro > NOW)
     */
    public function hasActiveReservation(int $userId, int $bookId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM prenotazioni 
            WHERE id_utente = :uid 
              AND id_libro = :bid
              AND (copia_libro IS NULL OR scadenza_ritiro > NOW())
        ");
        $stmt->execute(['uid' => $userId, 'bid' => $bookId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Crea una nuova prenotazione
     */
    public function createReservation(int $userId, int $bookId): bool
    {
        if ($this->hasActiveReservation($userId, $bookId)) return false;

        // Rimossa la colonna 'stato' che non esiste nel DB
        $stmt = $this->pdo->prepare("
            INSERT INTO prenotazioni (id_utente, id_libro, data_richiesta) 
            VALUES (:uid, :bid, NOW())
        ");
        return $stmt->execute([':uid' => $userId, ':bid' => $bookId]);
    }

    /**
     * Conta quante persone sono in coda per un libro
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

    /**
     * Recupera le prenotazioni dell'utente con la posizione in coda
     */
    public function getUserReservations(int $userId): array
    {
        // Rimossa la condizione su 'stato' e migliorata la logica della posizione
        $sql = "
            SELECT p.*, l.titolo, l.immagine_copertina,
                   (SELECT COUNT(*) 
                    FROM prenotazioni p2 
                    WHERE p2.id_libro = p.id_libro 
                      AND p2.copia_libro IS NULL 
                      AND p2.data_richiesta <= p.data_richiesta) as posizione_reale
            FROM prenotazioni p
            JOIN libri l ON p.id_libro = l.id_libro
            WHERE p.id_utente = ? 
              AND (p.copia_libro IS NULL OR p.scadenza_ritiro > NOW())
            ORDER BY p.copia_libro DESC, p.data_richiesta ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}