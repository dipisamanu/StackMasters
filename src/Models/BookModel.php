<?php
/**
 * Gestione logica dei libri con Query Builder Dinamico
 * File: src/Models/BookModel.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Helpers/IsbnValidator.php';

class BookModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAllGenres(): array
    {
        return $this->pdo->query("SELECT * FROM generi ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recupera l'anno minimo di pubblicazione presente nel database
     */
    public function getMinYear(): int
    {
        $stmt = $this->pdo->query("SELECT MIN(YEAR(anno_uscita)) as min_year FROM libri WHERE cancellato = 0 AND anno_uscita IS NOT NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['min_year'] ? (int)$result['min_year'] : 1900;
    }

    /**
     * Recupera un libro per ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, l.rating as rating_medio,
                   GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi,
                   (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato != 'FUORI_CATALOGO' AND stato != 'SMARRITO') AS copie_totali,
                   (SELECT COUNT(*) FROM inventari WHERE id_libro = l.id_libro AND stato = 'DISPONIBILE') AS copie_disponibili
            FROM libri l
            LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
            LEFT JOIN autori a ON la.id_autore = a.id
            WHERE l.id_libro = ? AND l.cancellato = 0
            GROUP BY l.id_libro
        ");
        $stmt->execute([$id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        return $book ?: null;
    }

    /**
     * Crea un nuovo libro
     */
    public function create(array $data, array $authorIds = []): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO libri (titolo, descrizione, isbn, anno_uscita, editore, numero_pagine, immagine_copertina)
                VALUES (:titolo, :descrizione, :isbn, :anno, :editore, :pagine, :copertina)
            ");

            $anno = !empty($data['anno']) ? $data['anno'] . "-01-01" : null;

            $stmt->execute([
                ':titolo' => $data['titolo'],
                ':descrizione' => $data['descrizione'] ?? null,
                ':isbn' => $data['isbn'] ?? null,
                ':anno' => $anno,
                ':editore' => $data['editore'] ?? null,
                ':pagine' => $data['pagine'] ?? null,
                ':copertina' => $data['copertina_url'] ?? null
            ]);

            $idLibro = (int)$this->pdo->lastInsertId();

            if (!empty($authorIds)) {
                $stmtAuth = $this->pdo->prepare("INSERT INTO libri_autori (id_libro, id_autore) VALUES (?, ?)");
                foreach ($authorIds as $aid) {
                    $stmtAuth->execute([$idLibro, $aid]);
                }
            } elseif (!empty($data['autore'])) {
                $this->linkAuthorByName($idLibro, $data['autore']);
            }

            $this->pdo->commit();
            return $idLibro;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Collega un autore a un libro cercando per nome/cognome o creandolo
     */
    private function linkAuthorByName(int $idLibro, string $fullAuthorName): void
    {
        $fullAuthorName = trim($fullAuthorName);
        $parts = explode(' ', $fullAuthorName);
        $nome = $parts[0];
        $cognome = (count($parts) > 1) ? implode(' ', array_slice($parts, 1)) : 'Ignoto';

        // Cerca se l'autore esiste già
        $stmtSearch = $this->pdo->prepare("SELECT id FROM autori WHERE nome = ? AND cognome = ?");
        $stmtSearch->execute([$nome, $cognome]);
        $author = $stmtSearch->fetch(PDO::FETCH_ASSOC);

        if ($author) {
            $idAutore = $author['id'];
        } else {
            // Crea nuovo autore
            $stmtInsAuth = $this->pdo->prepare("INSERT INTO autori (nome, cognome) VALUES (?, ?)");
            $stmtInsAuth->execute([$nome, $cognome]);
            $idAutore = (int)$this->pdo->lastInsertId();
        }

        // Collega libro e autore
        $stmtLink = $this->pdo->prepare("INSERT IGNORE INTO libri_autori (id_libro, id_autore) VALUES (?, ?)");
        $stmtLink->execute([$idLibro, $idAutore]);
    }

    /**
     * Aggiorna un libro esistente
     * @throws Exception
     */
    public function update(int $id, array $data, array $authorIds = []): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE libri 
                SET titolo = :titolo, descrizione = :descrizione, isbn = :isbn, 
                    anno_uscita = :anno, editore = :editore, numero_pagine = :pagine, 
                    immagine_copertina = :copertina
                WHERE id_libro = :id
            ");

            $anno = !empty($data['anno']) ? $data['anno'] . "-01-01" : null;

            $stmt->execute([
                ':id' => $id,
                ':titolo' => $data['titolo'],
                ':descrizione' => $data['descrizione'] ?? null,
                ':isbn' => $data['isbn'] ?? null,
                ':anno' => $anno,
                ':editore' => $data['editore'] ?? null,
                ':pagine' => $data['pagine'] ?? null,
                ':copertina' => $data['copertina_url'] ?? null
            ]);

            // Aggiorna Autori
            if (!empty($authorIds)) {
                $this->pdo->prepare("DELETE FROM libri_autori WHERE id_libro = ?")->execute([$id]);
                $stmtAuth = $this->pdo->prepare("INSERT INTO libri_autori (id_libro, id_autore) VALUES (?, ?)");
                foreach ($authorIds as $aid) {
                    $stmtAuth->execute([$id, $aid]);
                }
            } elseif (!empty($data['autore'])) {
                $this->pdo->prepare("DELETE FROM libri_autori WHERE id_libro = ?")->execute([$id]);
                $this->linkAuthorByName($id, $data['autore']);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina (logicamente) un libro
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE libri SET cancellato = 1 WHERE id_libro = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Motore di ricerca principale (Utilizza Stored Procedure CercaLibri)
     */
    public function searchBooks(int $page, int $perPage, array $filters): array
    {
        $offset = ($page - 1) * $perPage;

        $q_original = trim($filters['q'] ?? '');
        $q_full = '';

        if (!empty($q_original)) {
            if (!IsbnValidator::validate($q_original)) {
                $words = explode(' ', $q_original);
                $formatted = [];
                foreach ($words as $w) {
                    if (trim($w)) $formatted[] = '+' . trim($w) . '*';
                }
                $q_full = implode(' ', $formatted);
            }
        }

        try {
            $stmt = $this->pdo->prepare("CALL CercaLibri(:q, :orig, :avail, :ymin, :ymax, :rate, :cond, :genre, :sort, :lim, :off)");

            $stmt->bindValue(':q', $q_full);
            $stmt->bindValue(':orig', $q_original);
            $stmt->bindValue(':avail', (($filters['available'] ?? '') === 'on' || ($filters['available'] ?? false) === true), PDO::PARAM_BOOL);
            $stmt->bindValue(':ymin', !empty($filters['year_min']) ? (int)$filters['year_min'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':ymax', !empty($filters['year_max']) ? (int)$filters['year_max'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':rate', !empty($filters['rating']) ? (float)$filters['rating'] : null);
            $stmt->bindValue(':cond', $filters['condition'] ?? null);
            $stmt->bindValue(':genre', !empty($filters['genre']) ? (int)$filters['genre'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':sort', $filters['sort'] ?? 'relevance');
            $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);

            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $total = 0;
            if (!empty($data)) {
                $total = (int)$data[0]['totale'];
            }

            return ['data' => $data, 'total' => $total];

        } catch (PDOException $e) {
            error_log("Errore SearchBooks SP: " . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }

    /**
     * Alias per searchBooks
     */
    public function paginateWithCount(int $page, int $perPage, string $search = '', array $extraFilters = []): array
    {
        $filters = $extraFilters;
        $filters['q'] = $search;
        return $this->searchBooks($page, $perPage, $filters);
    }

    /**
     * Recupera dati per la Homepage usando la Stored Procedure GetHomepageData
     * @param string $section 'NOVITA', 'TRENDING', 'TOP_MESE', 'TOP_ALL'
     * @param int $limit
     * @return array
     */
    public function getHomeSection(string $section, int $limit = 6): array
    {
        try {
            $pdo = $this->pdo;
            $stmt = $pdo->prepare("CALL GetHomepageData(:section, :limit)");
            $stmt->bindParam(':section', $section, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $results;
        } catch (PDOException $e) {
            error_log("Errore getHomeSection ($section): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Recupera raccomandazioni personali
     */
    public function getPersonalRecommendations(int $userId, int $limit = 6): array
    {
        try {
            $pdo = $this->pdo;
            $stmt = $pdo->prepare("CALL GetRaccomandazioniPersonali(:uid, :limit)");
            $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $results;
        } catch (PDOException $e) {
            error_log("Errore getPersonalRecommendations: " . $e->getMessage());
            return [];
        }
    }

    public function getSimilarBooks(int $bookId, int $limit = 6): array
    {
        try {
            $pdo = $this->pdo;
            $stmt = $pdo->prepare("CALL GetLibriSimili(:id, :limit)");
            $stmt->bindParam(':id', $bookId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $results;
        } catch (PDOException $e) {
            error_log("Errore getSimilarBooks: " . $e->getMessage());
            return [];
        }
    }

    public function getReviews(int $bookId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.nome, u.cognome 
            FROM recensioni r
            JOIN utenti u ON r.id_utente = u.id_utente
            WHERE r.id_libro = ?
            ORDER BY r.data_creazione DESC
        ");
        $stmt->execute([$bookId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se un utente ha mai preso in prestito E restituito un libro
     */
    public function hasUserReadBook(int $userId, int $bookId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM prestiti p
            JOIN inventari i ON p.id_inventario = i.id_inventario
            WHERE p.id_utente = ? 
              AND i.id_libro = ? 
              AND p.data_restituzione IS NOT NULL
        ");
        $stmt->execute([$userId, $bookId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Recupera la recensione specifica di un utente per un libro
     */
    public function getUserReview(int $userId, int $bookId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM recensioni WHERE id_utente = ? AND id_libro = ?");
        $stmt->execute([$userId, $bookId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Salva o Aggiorna una recensione
     */
    public function saveReview(int $userId, int $bookId, int $voto, string $commento): bool
    {
        // INSERT ... ON DUPLICATE KEY UPDATE permette di creare o aggiornare con una sola query
        $stmt = $this->pdo->prepare("
            INSERT INTO recensioni (id_utente, id_libro, voto, descrizione, data_creazione) 
            VALUES (:uid, :bid, :voto, :desc, NOW())
            ON DUPLICATE KEY UPDATE 
                voto = VALUES(voto), 
                descrizione = VALUES(descrizione),
                data_update = NOW()
        ");

        $success = $stmt->execute([
            ':uid' => $userId,
            ':bid' => $bookId,
            ':voto' => $voto,
            ':desc' => $commento
        ]);

        // Aggiorna il rating medio del libro
        if ($success) {
            $this->updateBookRating($bookId);
        }
        return $success;
    }

    /**
     * Elimina una recensione
     */
    public function deleteReview(int $userId, int $bookId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM recensioni WHERE id_utente = ? AND id_libro = ?");
        $success = $stmt->execute([$userId, $bookId]);

        if ($success) {
            $this->updateBookRating($bookId);
        }
        return $success;
    }

    /**
     * Ricalcola e aggiorna il rating medio nella tabella libri
     */
    private function updateBookRating(int $bookId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE libri 
            SET rating = (SELECT COALESCE(AVG(voto), 0) FROM recensioni WHERE id_libro = ?)
            WHERE id_libro = ?
        ");
        $stmt->execute([$bookId, $bookId]);
    }
}