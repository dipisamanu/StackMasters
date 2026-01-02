<?php
/**
 * BookModel - Gestione Libri (Struttura DB Definitiva)
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

    public function getAll(string $search = ''): array
    {
        // Unisce Libri e Autori in una sola lista leggibile
        $sql = "SELECT l.*, 
                       GROUP_CONCAT(CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi
                FROM libri l
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE 1=1";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (l.titolo LIKE :q OR l.isbn LIKE :q OR CONCAT(a.nome, ' ', a.cognome) LIKE :q)";
            $params[':q'] = "%$search%";
        }

        $sql .= " GROUP BY l.id_libro ORDER BY l.ultimo_aggiornamento DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Inserisci il Libro nella tabella 'libri'
            $sql = "INSERT INTO libri (titolo, isbn, editore, anno_uscita, descrizione, numero_pagine) 
                    VALUES (:titolo, :isbn, :editore, :anno, :descrizione, :pagine)";

            $stmt = $this->db->prepare($sql);

            // Formatta anno per DATETIME (YYYY-01-01)
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

            // 2. Gestisci Autore (Se non esiste lo crea, poi collega)
            if (!empty($data['autore'])) {
                $this->linkAuthor($idLibro, $data['autore']);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Errore creazione libro: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM libri WHERE id_libro = ?");
        return $stmt->execute([$id]);
    }

    // Funzione privata per gestire la logica autore
    private function linkAuthor(int $idLibro, string $fullName)
    {
        $parts = explode(' ', trim($fullName), 2);
        $nome = $parts[0];
        $cognome = $parts[1] ?? '';

        // Cerca ID autore esistente
        $stmt = $this->db->prepare("SELECT id FROM autori WHERE nome LIKE ? AND cognome LIKE ? LIMIT 1");
        $stmt->execute([$nome, $cognome]);
        $autore = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($autore) {
            $idAutore = $autore['id'];
        } else {
            // Crea nuovo autore
            $ins = $this->db->prepare("INSERT INTO autori (nome, cognome) VALUES (?, ?)");
            $ins->execute([$nome, $cognome]);
            $idAutore = $this->db->lastInsertId();
        }

        // Collega libro e autore
        $link = $this->db->prepare("INSERT INTO libri_autori (id_libro, id_autore) VALUES (?, ?)");
        $link->execute([$idLibro, $idAutore]);
    }
}