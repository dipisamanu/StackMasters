<?php
/**
 * BookModel - Gestione Libri (Versione Fix Seeder)
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
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato != 'SCARTATO' AND stato != 'SMARRITO') as copie_totali,
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato = 'DISPONIBILE') as copie_disponibili
                FROM libri l
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE l.id_libro = :id AND l.cancellato = 0
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
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato != 'SCARTATO' AND stato != 'SMARRITO') as copie_totali,
                       (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato = 'DISPONIBILE') as copie_disponibili
                FROM libri l
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE l.cancellato = 0";

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
                WHERE l.cancellato = 0";

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

    /**
     * MODIFICATO: Restituisce int (ID del libro creato) invece di bool.
     */
    public function create(array $data, array $files = []): int
    {
        try {
            $this->db->beginTransaction();

            $coverPath = null;
            if (isset($files['copertina']) && $files['copertina']['error'] === 0) {
                $coverPath = $this->uploadCover($files['copertina']);
            } elseif (!empty($data['copertina_url'])) {
                $coverPath = $data['copertina_url'];
            }

            $sql = "INSERT INTO libri (titolo, isbn, editore, anno_uscita, descrizione, numero_pagine, immagine_copertina, cancellato) 
                    VALUES (:titolo, :isbn, :editore, :anno, :descrizione, :pagine, :img, 0)";

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

            // CATTURIAMO L'ID PRIMA DEL COMMIT
            $idLibro = (int)$this->db->lastInsertId();

            if (!empty($data['autore'])) {
                $this->linkAuthor($idLibro, $this->clean($data['autore']));
            }

            $this->db->commit();
            return $idLibro; // RITORNA L'ID

        } catch (Exception $e) {
            $this->db->rollBack();
            // Rilanciamo l'errore per vederlo nel seeder
            throw $e;
        }
    }

    public function update(int $id, array $data, array $files = []): bool
    {
        try {
            $this->db->beginTransaction();

            $oldBook = $this->getById($id);
            if (!$oldBook) throw new Exception("Libro non trovato o cancellato.");

            $coverPath = $oldBook['immagine_copertina'];

            if (isset($files['copertina']) && $files['copertina']['error'] === 0) {
                $coverPath = $this->uploadCover($files['copertina']);
            } elseif (!empty($data['copertina_url'])) {
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

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $idLibro): bool
    {
        try {
            $check = $this->db->prepare("
                SELECT COUNT(*) 
                FROM prestiti p 
                JOIN inventari i ON p.id_inventario = i.id_inventario 
                WHERE i.id_libro = ? AND p.data_restituzione IS NULL
            ");
            $check->execute([$idLibro]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Impossibile archiviare: ci sono copie attualmente in prestito.");
            }

            $this->db->prepare("UPDATE libri SET cancellato = 1 WHERE id_libro = ?")->execute([$idLibro]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function uploadCover($file) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) throw new Exception("Formato immagine non valido.");
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
    // ... aggiungi questo metodo alla classe BookModel esistente ...

    /**
     * Recupera i dettagli di un libro partendo dall'ID di una copia fisica (inventario).
     * Utilizzato per il feedback visivo durante la scansione al bancone.
     */
    public function getByInventarioId(int $inventarioId)
    {
        $sql = "SELECT l.*, i.id_inventario, i.stato, i.condizione, i.collocazione,
                   GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi
            FROM inventari i
            JOIN libri l ON i.id_libro = l.id_libro
            LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
            LEFT JOIN autori a ON la.id_autore = a.id
            WHERE i.id_inventario = :iid
            GROUP BY i.id_inventario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':iid' => $inventarioId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}