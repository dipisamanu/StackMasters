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

    public function addCopy(int $bookId, string $rfidCode, string $collocazione, string $condizione = 'BUONO'): bool
    {
        try {
            $this->db->beginTransaction();

            $rfidCode = trim($rfidCode);
            $collocazione = strtoupper(trim($collocazione));

            // Validazione Base
            if (strlen($rfidCode) < 3) throw new Exception("Il codice RFID è troppo corto.");
            if (strlen($collocazione) < 2) throw new Exception("La collocazione non è valida.");

            // CONTROLLO UNICITA' COLLOCAZIONE
            // Verifica se esiste già un libro (qualsiasi) in quella posizione
            $checkColl = $this->db->prepare("SELECT id_inventario FROM inventari WHERE collocazione = ?");
            $checkColl->execute([$collocazione]);
            if ($checkColl->fetch()) {
                throw new Exception("La collocazione '$collocazione' è già occupata da un altro libro.");
            }

            // 1. Gestione RFID
            $stmt = $this->db->prepare("SELECT id_rfid FROM rfid WHERE rfid = ? AND tipo = 'LIBRO'");
            $stmt->execute([$rfidCode]);
            $rfid = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rfid) {
                $idRfid = $rfid['id_rfid'];
                // Controllo se già usato
                $check = $this->db->prepare("SELECT id_inventario FROM inventari WHERE id_rfid = ?");
                $check->execute([$idRfid]);
                if ($check->fetch()) {
                    throw new Exception("Questo codice RFID è già assegnato a un'altra copia fisica!");
                }
            } else {
                // Crea nuovo RFID
                $ins = $this->db->prepare("INSERT INTO rfid (rfid, tipo) VALUES (?, 'LIBRO')");
                $ins->execute([$rfidCode]);
                $idRfid = $this->db->lastInsertId();
            }

            // 2. Inserisci in Inventario
            $sql = "INSERT INTO inventari (id_libro, id_rfid, collocazione, condizione, stato) 
                    VALUES (:id_libro, :id_rfid, :collocazione, :condizione, 'DISPONIBILE')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_libro' => $bookId,
                ':id_rfid' => $idRfid,
                ':collocazione' => $collocazione,
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

    public function updateCopy(int $copyId, string $collocazione, string $condizione, string $stato): bool
    {
        $collocazione = strtoupper(trim($collocazione));

        if (strlen($collocazione) < 2) throw new Exception("Collocazione non valida.");

        // CONTROLLO UNICITA' COLLOCAZIONE (Escludendo se stesso)
        $checkColl = $this->db->prepare("SELECT id_inventario FROM inventari WHERE collocazione = ? AND id_inventario != ?");
        $checkColl->execute([$collocazione, $copyId]);
        if ($checkColl->fetch()) {
            throw new Exception("La collocazione '$collocazione' è già occupata.");
        }

        $sql = "UPDATE inventari SET 
                collocazione = :coll, 
                condizione = :cond, 
                stato = :stato 
                WHERE id_inventario = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':coll' => $collocazione,
            ':cond' => $condizione,
            ':stato' => $stato,
            ':id' => $copyId
        ]);
    }

    public function deleteCopy(int $copyId): bool
    {
        $check = $this->db->prepare("SELECT stato FROM inventari WHERE id_inventario = ?");
        $check->execute([$copyId]);
        $row = $check->fetch();

        if ($row && $row['stato'] === 'IN_PRESTITO') {
            throw new Exception("Impossibile eliminare: la copia è attualmente in prestito.");
        }

        $stmt = $this->db->prepare("DELETE FROM inventari WHERE id_inventario = ?");
        return $stmt->execute([$copyId]);
    }
}