<?php
/**
 * BookModel - Gestione Libri (Versione Pulita)
 * File: src/Models/BookModel.php
 */

require_once __DIR__ . '/../config/database.php';

class BookModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->db->exec("SET NAMES 'utf8mb4'");
    }

    public function getById(int $id)
    {
        $sql = "SELECT l.*, 
                       GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi
                FROM libri l
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE l.id_libro = :id
                GROUP BY l.id_libro";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll(string $search = '', array $filters = []): array
    {
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
            $like = "%$trimSearch%";
            $sql .= " AND (
                        l.titolo LIKE :q1 OR l.isbn LIKE :q2 OR l.editore LIKE :q3 
                        OR CAST(l.anno_uscita AS CHAR) LIKE :q4
                        OR CONCAT(a.nome, ' ', a.cognome) LIKE :q5 OR a.cognome LIKE :q6
                      )";
            $params = array_fill_keys([':q1',':q2',':q3',':q4',':q5',':q6'], $like);
        }

        $sql .= " GROUP BY l.id_libro";

        if (!empty($filters['solo_disponibili'])) {
            $sql .= " HAVING copie_disponibili > 0";
        }

        $sql .= " ORDER BY l.ultimo_aggiornamento DESC";

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
                ':titolo' => $this->clean($data['titolo']),
                ':isbn' => $this->clean($data['isbn'] ?? ''),
                ':editore' => $this->clean($data['editore'] ?? ''),
                ':anno' => $annoDate,
                ':descrizione' => $this->clean($data['descrizione'] ?? ''),
                ':pagine' => !empty($data['pagine']) ? (int)$data['pagine'] : null
            ]);

            $idLibro = $this->db->lastInsertId();

            if (!empty($data['autore'])) {
                $this->linkAuthor($idLibro, $this->clean($data['autore']));
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            if ($e->errorInfo[1] == 1062 && strpos(strtolower($e->getMessage()), 'isbn') !== false) {
                throw new Exception("Un libro con questo ISBN è già presente nella libreria!");
            }
            throw new Exception("Errore Database: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE libri SET 
                    titolo = :titolo, isbn = :isbn, editore = :editore, 
                    anno_uscita = :anno, descrizione = :descrizione, numero_pagine = :pagine 
                    WHERE id_libro = :id";

            $stmt = $this->db->prepare($sql);
            $annoDate = !empty($data['anno']) ? $data['anno'] . '-01-01 00:00:00' : null;

            $stmt->execute([
                ':titolo' => $this->clean($data['titolo']),
                ':isbn' => $this->clean($data['isbn'] ?? ''),
                ':editore' => $this->clean($data['editore'] ?? ''),
                ':anno' => $annoDate,
                ':descrizione' => $this->clean($data['descrizione'] ?? ''),
                ':pagine' => !empty($data['pagine']) ? (int)$data['pagine'] : null,
                ':id' => $id
            ]);

            if (!empty($data['autore'])) {
                $this->db->prepare("DELETE FROM libri_autori WHERE id_libro = ?")->execute([$id]);
                $this->linkAuthor($id, $this->clean($data['autore']));
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            if ($e->errorInfo[1] == 1062 && strpos(strtolower($e->getMessage()), 'isbn') !== false) {
                throw new Exception("Impossibile salvare: ISBN già assegnato.");
            }
            throw new Exception("Errore Database: " . $e->getMessage());
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
            if ($check->fetchColumn() > 0) throw new Exception("Impossibile eliminare: copie in prestito.");

            $this->db->beginTransaction();

            $tables = ['recensioni', 'prenotazioni', 'libri_autori', 'libri_generi', 'inventari'];
            foreach($tables as $t) {
                $this->db->prepare("DELETE FROM $t WHERE id_libro = ?")->execute([$idLibro]);
            }
            $this->db->prepare("DELETE FROM libri WHERE id_libro = ?")->execute([$idLibro]);

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
        $cognome = $parts[1] ?? '.';

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

    private function clean($str) {
        return strip_tags(trim($str ?? ''));
    }
}