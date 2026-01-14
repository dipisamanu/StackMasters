<?php
/**
 * BookModel - Gestione Libri (Ottimizzato con Stored Procedure)
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

    /**
     * Helper privato per trasformare l'input utente in sintassi Boolean Mode
     * Esempio: "Harry Potter" -> "+Harry* +Potter*"
     */
    private function prepareFulltextSearch(string $search): string
    {
        $search = trim($search);
        if (empty($search)) return '';

        // Rimuove caratteri che potrebbero rompere la sintassi SQL
        $search = str_replace(['+', '-', '<', '>', '(', ')', '~', '*', '"', '@'], ' ', $search);

        $words = explode(' ', $search);
        $formattedWords = [];

        foreach ($words as $word) {
            if (!empty($word)) {
                $formattedWords[] = '+' . $word . '*';
            }
        }

        return implode(' ', $formattedWords);
    }

    /**
     * Recupera lista libri paginata e il conteggio totale.
     * Utilizza la Stored Procedure 'CercaLibri'.
     * * @return array ['data' => array_libri, 'total' => int]
     */
    public function paginateWithCount(int $page = 1, int $perPage = 12, string $search = '', array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;

        $searchQuery = $this->prepareFulltextSearch($search);

        if (IsbnValidator::validate($search)) {
            $searchQuery = IsbnValidator::clean($search);
        }

        $soloDisponibili = !empty($filters['solo_disponibili']) ? 1 : 0;

        try {
            $stmt = $this->db->prepare("CALL CercaLibri(:query, :soloDisp, :limit, :offset)");

            $stmt->bindValue(':query', $searchQuery, PDO::PARAM_STR);
            $stmt->bindValue(':soloDisp', $soloDisponibili, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $total = 0;
            if (!empty($results)) {
                $total = (int)$results[0]['totale'];
            }

            return [
                'data' => $results,
                'total' => $total
            ];

        } catch (PDOException $e) {
            error_log("Errore CercaLibri: " . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }

    // --- METODI CRUD STANDARD ---

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

    /**
     * @throws Exception
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

            $idLibro = (int)$this->db->lastInsertId();

            if (!empty($data['autore'])) {
                $this->linkAuthor($idLibro, $this->clean($data['autore']));
            }

            $this->db->commit();
            return $idLibro;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
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
                // Rimuovi vecchi autori e aggiungi il nuovo
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

    /**
     * @throws Exception
     */
    public function delete(int $idLibro): bool
    {
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
    }

    // --- METODI PRIVATI ---

    /**
     * @throws Exception
     */
    private function uploadCover($file): string
    {
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

    private function linkAuthor(int $idLibro, string $fullName): void
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

    private function clean($str): string
    {
        return strip_tags(trim($str ?? ''));
    }

    /**
     * Recupera i dettagli di un libro partendo dall'ID di una copia fisica (inventario).
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