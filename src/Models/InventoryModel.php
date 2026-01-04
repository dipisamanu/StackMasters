<?php
/**
 * InventoryModel - Gestione Copie Fisiche e RFID
 * File: src/Models/InventoryModel.php
 */

require_once __DIR__ . '/../config/database.php';

class InventoryModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Recupera tutte le copie di un libro specifico
     */
    public function getCopiesByBookId(int $bookId): array
    {
        $sql = "SELECT i.*, r.rfid as codice_rfid
                FROM inventari i
                LEFT JOIN rfid r ON i.id_rfid = r.id_rfid
                WHERE i.id_libro = ?
                ORDER BY i.collocazione ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bookId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aggiunge una singola copia manuale gestendo l'RFID
     */
    public function addCopy(int $bookId, string $rfidCode, string $collocazione, string $condizione = 'BUONO'): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Gestione RFID (Cerca o Crea)
            $rfidCode = trim($rfidCode);

            // Cerca se l'RFID esiste gia
            $stmt = $this->db->prepare("SELECT id_rfid FROM rfid WHERE rfid = ? AND tipo = 'LIBRO'");
            $stmt->execute([$rfidCode]);
            $rfid = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rfid) {
                $idRfid = $rfid['id_rfid'];
                // Controlla se questo RFID e gia assegnato a una copia nell'inventario
                $check = $this->db->prepare("SELECT id_inventario FROM inventari WHERE id_rfid = ?");
                $check->execute([$idRfid]);
                if ($check->fetch()) {
                    throw new Exception("Questo codice RFID e gia associato a un altro libro fisico.");
                }
            } else {
                // Crea nuovo RFID se non esiste
                $ins = $this->db->prepare("INSERT INTO rfid (rfid, tipo) VALUES (?, 'LIBRO')");
                $ins->execute([$rfidCode]);
                $idRfid = $this->db->lastInsertId();
            }

            // 2. Inserisci la copia in Inventario
            $sql = "INSERT INTO inventari (id_libro, id_rfid, collocazione, condizione, stato) 
                    VALUES (:id_libro, :id_rfid, :collocazione, :condizione, 'DISPONIBILE')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_libro' => $bookId,
                ':id_rfid' => $idRfid,
                ':collocazione' => strtoupper(trim($collocazione)),
                ':condizione' => $condizione
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Aggiorna i dati di una copia esistente
     */
    public function updateCopy(int $copyId, string $collocazione, string $condizione, string $stato): bool
    {
        // Nota: L'RFID non si modifica qui per integrita. Se cambia l'RFID, meglio eliminare e rifare la copia.
        $sql = "UPDATE inventari SET 
                collocazione = :coll, 
                condizione = :cond, 
                stato = :stato 
                WHERE id_inventario = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':coll' => strtoupper(trim($collocazione)),
            ':cond' => $condizione,
            ':stato' => $stato,
            ':id' => $copyId
        ]);
    }

    /**
     * Elimina una copia dall'inventario
     */
    public function deleteCopy(int $copyId): bool
    {
        // Controlla se la copia e attualmente in prestito
        $check = $this->db->prepare("SELECT stato FROM inventari WHERE id_inventario = ?");
        $check->execute([$copyId]);
        $row = $check->fetch();

        if ($row && $row['stato'] === 'IN_PRESTITO') {
            throw new Exception("Impossibile eliminare: la copia e attualmente in prestito.");
        }

        $stmt = $this->db->prepare("DELETE FROM inventari WHERE id_inventario = ?");
        return $stmt->execute([$copyId]);
    }
}

