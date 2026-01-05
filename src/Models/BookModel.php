<?php
/**
 * BookModel - Gestione Libri con Immagini
 * File: src/Models/BookModel.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Helpers/IsbnValidator.php';

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
                       GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi,
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro) as copie_totali,
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato = 'DISPONIBILE') as copie_disponibili
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
        return $this->paginate(1, 1000, $search, $filters);
    }

    public function paginate(int $page = 1, int $perPage = 12, string $search = '', array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;

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
            $cleanSearchIsbn = IsbnValidator::clean($trimSearch);
            $likeIsbn = "%$cleanSearchIsbn%";

            $sql .= " AND (
                        l.titolo LIKE :q1 OR l.isbn LIKE :q2 
                        OR CONCAT(a.nome, ' ', a.cognome) LIKE :q3
                      )";
            $params[':q1'] = $like;
            $params[':q2'] = $likeIsbn;
            $params[':q3'] = $like;
        }

        $sql .= " GROUP BY l.id_libro";

        if (!empty($filters['solo_disponibili'])) {
            $sql .= " HAVING copie_disponibili > 0";
        }

        $sql .= " ORDER BY l.ultimo_aggiornamento DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $search = '', array $filters = []): int
    {
        $sql = "SELECT COUNT(DISTINCT l.id_libro) as totale 
                FROM libri l
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE 1=1";

        $params = [];

        if (!empty($search)) {
            $trimSearch = trim($search);
            $like = "%$trimSearch%";
            $cleanSearchIsbn = IsbnValidator::clean($trimSearch);
            $likeIsbn = "%$cleanSearchIsbn%";

            $sql .= " AND (l.titolo LIKE :q1 OR l.isbn LIKE :q2 OR CONCAT(a.nome, ' ', a.cognome) LIKE :q3)";
            $params[':q1'] = $like;
            $params[':q2'] = $likeIsbn;
            $params[':q3'] = $like;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function create(array $data, array $files = []): bool
    {
        try {
            $this->db->beginTransaction();

            // GESTIONE IMMAGINE
            $coverPath = null;
            if (isset($files['copertina']) && $files['copertina']['error'] === 0) {
                $coverPath = $this->uploadCover($files['copertina']);
            } elseif (!empty($data['copertina_url'])) {
                $coverPath = $data['copertina_url'];
            }

            $sql = "INSERT INTO libri (titolo, isbn, editore, anno_uscita, descrizione, numero_pagine, immagine_copertina) 
                    VALUES (:titolo, :isbn, :editore, :anno, :descrizione, :pagine, :img)";

            $stmt = $this->db->prepare($sql);

            $cleanIsbn = IsbnValidator::clean($data['isbn'] ?? '');
            $annoDate = !empty($data['anno']) ? $data['anno'] . '-01-01 00:00:00' : null;

            $stmt->execute([
                ':titolo' => $this->clean($data['titolo']),
                ':isbn' => $cleanIsbn,
                ':editore' => $this->clean($data['editore'] ?? ''),
                ':anno' => $annoDate,
                ':descrizione' => $this->clean($data['descrizione'] ?? ''),
                ':pagine' => !empty($data['pagine']) ? (int)$data['pagine'] : null,
                ':img' => $coverPath
            ]);

            $idLibro = $this->db->lastInsertId();

            if (!empty($data['autore'])) {
                $this->linkAuthor($idLibro, $this->clean($data['autore']));
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            if ($e->errorInfo[1] == 1062) {
                throw new Exception("Un libro con questo ISBN è già presente.");
            }
            throw new Exception("Errore Database: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, array $files = []): bool
    {
        try {
            $this->db->beginTransaction();

            // Recupera immagine vecchia
            $oldBook = $this->getById($id);
            $coverPath = $oldBook['immagine_copertina'];

            // Se c'è un nuovo file, caricalo e sostituisci
            if (isset($files['copertina']) && $files['copertina']['error'] === 0) {
                $coverPath = $this->uploadCover($files['copertina']);
            }
            // Se non c'è file ma c'è un URL nuovo dall'API, usa quello
            elseif (!empty($data['copertina_url'])) {
                $coverPath = $data['copertina_url'];
            }

            $sql = "UPDATE libri SET 
                    titolo = :titolo, isbn = :isbn, editore = :editore, 
                    anno_uscita = :anno, descrizione = :descrizione, 
                    numero_pagine = :pagine, immagine_copertina = :img
                    WHERE id_libro = :id";

            $stmt = $this->db->prepare($sql);

            $cleanIsbn = IsbnValidator::clean($data['isbn'] ?? '');
            $annoDate = !empty($data['anno']) ? $data['anno'] . '-01-01 00:00:00' : null;

            $stmt->execute([
                ':titolo' => $this->clean($data['titolo']),
                ':isbn' => $cleanIsbn,
                ':editore' => $this->clean($data['editore'] ?? ''),
                ':anno' => $annoDate,
                ':descrizione' => $this->clean($data['descrizione'] ?? ''),
                ':pagine' => !empty($data['pagine']) ? (int)$data['pagine'] : null,
                ':img' => $coverPath,
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
            if ($e->errorInfo[1] == 1062) {
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
            $check = $this->db->prepare("SELECT COUNT(*) FROM prestiti p JOIN inventari i ON p.id_inventario = i.id_inventario WHERE i.id_libro = ? AND p.data_restituzione IS NULL");
            $check->execute([$idLibro]);
            if ($check->fetchColumn() > 0) throw new Exception("Impossibile eliminare: copie in prestito.");

            $this->db->beginTransaction();
            foreach(['recensioni', 'prenotazioni', 'libri_autori', 'libri_generi', 'inventari'] as $t) {
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

    private function uploadCover($file) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) throw new Exception("Formato immagine non valido (solo JPG, PNG, WEBP).");
        if ($file['size'] > 2 * 1024 * 1024) throw new Exception("Immagine troppo pesante (max 2MB).");

        $targetDir = __DIR__ . '/../../public/uploads/covers/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $filename = uniqid('cover_') . '.' . $ext;

        if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
            return 'uploads/covers/' . $filename;
        }
        throw new Exception("Errore durante il caricamento dell'immagine.");
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