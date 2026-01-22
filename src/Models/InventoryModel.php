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
                ORDER BY i.collocazione";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bookId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Trova la prima collocazione libera nel formato A1-01 ... Z9-99
     */
    public function findFirstFreeLocation(): string
    {
        // Prendi tutte le collocazioni occupate
        $stmt = $this->db->query("SELECT collocazione FROM inventari");
        $occupied = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Mappa per ricerca rapida
        $occupiedMap = [];
        if ($occupied) {
            foreach ($occupied as $loc) {
                if ($loc) $occupiedMap[strtoupper($loc)] = true;
            }
        }

        // Algoritmo di ricerca: Lettera(A-Z) + Ripiano(1-9) + Posizione(01-99)
        foreach (range('A', 'Z') as $letter) {
            for ($shelf = 1; $shelf <= 9; $shelf++) {
                for ($pos = 1; $pos <= 99; $pos++) {
                    // Formatta la posizione con lo zero iniziale (es. 01, 05, 10)
                    $posFormatted = str_pad($pos, 2, '0', STR_PAD_LEFT);

                    // Costruisci codice: A1-01
                    $code = $letter . $shelf . '-' . $posFormatted;

                    if (!isset($occupiedMap[$code])) {
                        return $code;
                    }
                }
            }
        }

        return "FULL";
    }

    /**
     * @throws Exception
     */
    public function addCopy(int $bookId, string $rfidCode, string $collocazione, string $condizione = 'BUONO'): bool
    {
        try {
            $this->db->beginTransaction();

            $rfidCode = trim($rfidCode);
            $collocazione = strtoupper(trim($collocazione));

            if (strlen($rfidCode) < 3) throw new Exception("Il codice RFID è troppo corto.");

            // Validazione lasca per permettere formati personalizzati se necessario,
            // ma il generatore userà sempre A1-01
            if (strlen($collocazione) < 3) throw new Exception("La collocazione non è valida.");

            // Check Unicità Collocazione
            $checkColl = $this->db->prepare("SELECT id_inventario FROM inventari WHERE collocazione = ?");
            $checkColl->execute([$collocazione]);
            if ($checkColl->fetch()) {
                throw new Exception("La collocazione '$collocazione' è già occupata.");
            }

            // Check/Insert RFID
            $stmt = $this->db->prepare("SELECT id_rfid FROM rfid WHERE rfid = ? AND tipo = 'LIBRO'");
            $stmt->execute([$rfidCode]);
            $rfid = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rfid) {
                $idRfid = $rfid['id_rfid'];
                $check = $this->db->prepare("SELECT id_inventario FROM inventari WHERE id_rfid = ?");
                $check->execute([$idRfid]);
                if ($check->fetch()) throw new Exception("RFID già assegnato.");
            } else {
                $ins = $this->db->prepare("INSERT INTO rfid (rfid, tipo) VALUES (?, 'LIBRO')");
                $ins->execute([$rfidCode]);
                $idRfid = $this->db->lastInsertId();
            }

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
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function updateCopy(int $copyId, string $collocazione, string $condizione, string $stato): bool
    {
        $collocazione = strtoupper(trim($collocazione));
        if (strlen($collocazione) < 3) throw new Exception("Collocazione non valida.");

        $checkColl = $this->db->prepare("SELECT id_inventario FROM inventari WHERE collocazione = ? AND id_inventario != ?");
        $checkColl->execute([$collocazione, $copyId]);
        if ($checkColl->fetch()) throw new Exception("La collocazione '$collocazione' è già occupata.");

        $sql = "UPDATE inventari SET collocazione = :coll, condizione = :cond, stato = :stato WHERE id_inventario = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':coll' => $collocazione, ':cond' => $condizione, ':stato' => $stato, ':id' => $copyId]);
    }

    /**
     * @throws Exception
     */
    public function deleteCopy(int $copyId): bool
    {
        $check = $this->db->prepare("SELECT stato FROM inventari WHERE id_inventario = ?");
        $check->execute([$copyId]);
        $row = $check->fetch();
        if ($row && $row['stato'] === 'IN_PRESTITO') throw new Exception("Impossibile eliminare: copia in prestito.");

        $stmt = $this->db->prepare("DELETE FROM inventari WHERE id_inventario = ?");
        return $stmt->execute([$copyId]);
    }

    public function getAllCopies(): array
    {
        $sql = "SELECT i.id_inventario, l.titolo, i.stato, r.rfid as codice_rfid
                FROM inventari i
                JOIN libri l ON i.id_libro = l.id_libro
                LEFT JOIN rfid r ON i.id_rfid = r.id_rfid
                ORDER BY i.id_inventario";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}