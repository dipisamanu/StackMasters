<?php
/**
 * BookModel - Gestione Libri
 * File: src/Models/BookModel.php
 */

require_once __DIR__ . '/../config/database.php';

class BookModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Recupera lista libri (Fix SQL Parameter Error)
     */
    public function getAll(string $search = ''): array
    {
        // Query Base
        $sql = "SELECT l.*, 
                       GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi,
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro) as copie_totali,
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato = 'DISPONIBILE') as copie_disponibili
                FROM libri l
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE 1=1";

        $params = [];

        if (!empty($search)) {
            $trimSearch = trim($search);
            $likeTerm = "%$trimSearch%";

            // FIX: Usiamo nomi di parametri DIVERSI per ogni campo (:q1, :q2...)
            // Alcuni database non accettano di ripetere :q più volte.
            $sql .= " AND (
                        l.titolo LIKE :q1 
                        OR l.isbn LIKE :q2 
                        OR l.editore LIKE :q3 
                        OR CAST(l.anno_uscita AS CHAR) LIKE :q4
                        OR CONCAT(a.nome, ' ', a.cognome) LIKE :q5
                        OR a.cognome LIKE :q6
                      )";

            // Assegniamo lo stesso valore a tutti i parametri
            $params[':q1'] = $likeTerm;
            $params[':q2'] = $likeTerm;
            $params[':q3'] = $likeTerm;
            $params[':q4'] = $likeTerm;
            $params[':q5'] = $likeTerm;
            $params[':q6'] = $likeTerm;
        }

        $sql .= " GROUP BY l.id_libro ORDER BY l.ultimo_aggiornamento DESC";

        // Esecuzione diretta per vedere eventuali errori se ci sono ancora
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO libri (titolo, isbn, editore, anno_uscita, descrizione, numero_pagine) 
                    VALUES (:titolo, :isbn, :editore, :anno, :descrizione, :pagine)";

            $stmt = $this->db->prepare($sql);

            $annoDate = !empty($data['anno']) ? $data['anno'] . '-01-01 00:00:00' : null;

            $stmt->execute([
                ':titolo' => strip_tags($data['titolo']),
                ':isbn' => strip_tags($data['isbn'] ?? ''),
                ':editore' => strip_tags($data['editore'] ?? ''),
                ':anno' => $annoDate,
                ':descrizione' => strip_tags($data['descrizione'] ?? ''),
                ':pagine' => !empty($data['pagine']) ? (int)$data['pagine'] : null
            ]);

            $idLibro = $this->db->lastInsertId();

            if (!empty($data['autore'])) {
                $this->linkAuthor($idLibro, $data['autore']);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Errore Create: " . $e->getMessage());
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE libri SET 
                    titolo = :titolo, 
                    isbn = :isbn, 
                    editore = :editore, 
                    anno_uscita = :anno, 
                    descrizione = :descrizione, 
                    numero_pagine = :pagine 
                    WHERE id_libro = :id";

            $stmt = $this->db->prepare($sql);
            $annoDate = !empty($data['anno']) ? $data['anno'] . '-01-01 00:00:00' : null;

            $stmt->execute([
                ':titolo' => strip_tags($data['titolo']),
                ':isbn' => strip_tags($data['isbn'] ?? ''),
                ':editore' => strip_tags($data['editore'] ?? ''),
                ':anno' => $annoDate,
                ':descrizione' => strip_tags($data['descrizione'] ?? ''),
                ':pagine' => !empty($data['pagine']) ? (int)$data['pagine'] : null,
                ':id' => $id
            ]);

            if (!empty($data['autore'])) {
                $this->db->prepare("DELETE FROM libri_autori WHERE id_libro = ?")->execute([$id]);
                $this->linkAuthor($id, $data['autore']);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $idLibro): bool
    {
        try {
            $check = $this->db->prepare("
                SELECT COUNT(*) FROM prestiti p
                JOIN inventari i ON p.id_inventario = i.id_inventario
                WHERE i.id_libro = ? AND p.data_restituzione IS NULL
            ");
            $check->execute([$idLibro]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Impossibile eliminare: ci sono copie in prestito.");
            }

            $this->db->beginTransaction();

            $this->db->prepare("DELETE FROM recensioni WHERE id_libro = ?")->execute([$idLibro]);
            $this->db->prepare("DELETE FROM prenotazioni WHERE id_libro = ?")->execute([$idLibro]);
            $this->db->prepare("DELETE FROM libri_autori WHERE id_libro = ?")->execute([$idLibro]);
            $this->db->prepare("DELETE FROM libri_generi WHERE id_libro = ?")->execute([$idLibro]);
            $this->db->prepare("DELETE FROM inventari WHERE id_libro = ?")->execute([$idLibro]);

            $stmt = $this->db->prepare("DELETE FROM libri WHERE id_libro = ?");
            $stmt->execute([$idLibro]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function linkAuthor(int $idLibro, string $fullName)
    {
        $parts = explode(' ', trim($fullName), 2);
        $nome = $parts[0];
        $cognome = $parts[1] ?? '';

        $stmt = $this->db->prepare("SELECT id FROM autori WHERE nome LIKE ? AND cognome LIKE ? LIMIT 1");
        $stmt->execute([$nome, $cognome]);
        $autore = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($autore) {
            $idAutore = $autore['id'];
        } else {
            $ins = $this->db->prepare("INSERT INTO autori (nome, cognome) VALUES (?, ?)");
            $ins->execute([$nome, $cognome]);
            $idAutore = $this->db->lastInsertId();
        }

        $this->db->prepare("INSERT INTO libri_autori (id_libro, id_autore) VALUES (?, ?)")
            ->execute([$idLibro, $idAutore]);
    }
}