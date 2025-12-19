<?php
/**
 * LibroManager - Gestione completa CRUD Libri
 * Issue 3.1: Backend: CRUD base inserimento Libro
 * Issue 3.2: Logic: Gestione entità "Copie" multiple
 */

require_once '../config/database.php';
require_once '../utils/ISBNValidator.php';

class LibroManager {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
    }

    /**
     * CREATE - Inserisce un nuovo libro nel database
     *
     * @param array $data Dati del libro
     * @return array ['success' => bool, 'id' => int|null, 'message' => string]
     */
    public function createLibro($data) {
        // Validazione ISBN
        if (!empty($data['isbn'])) {
            $isbnCheck = ISBNValidator::validate($data['isbn']);
            if (!$isbnCheck['valid']) {
                return [
                    'success' => false,
                    'message' => 'ISBN non valido: ' . ($isbnCheck['error'] ?? 'formato errato')
                ];
            }
            $data['isbn'] = $isbnCheck['cleaned']; // Usa versione pulita
        }

        // Sanitizzazione input (protezione XSS)
        $titolo = $this->sanitize($data['titolo']);
        $descrizione = $this->sanitize($data['descrizione'] ?? null);
        $isbn = $data['isbn'] ?? null;
        $anno_uscita = $data['anno_uscita'] ?? null;
        $editore = $this->sanitize($data['editore'] ?? null);
        $lingua_id = $data['lingua_id'] ?? null;
        $numero_pagine = $data['numero_pagine'] ?? null;
        $valore_copertina = $data['valore_copertina'] ?? null;
        $copertina_url = $data['copertina_url'] ?? null;

        // Query preparata (protezione SQL Injection)
        $query = "INSERT INTO Libri (
            titolo, descrizione, isbn, anno_uscita, editore, 
            lingua_id, numero_pagine, valore_copertina, copertina_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Errore preparazione query: ' . $this->conn->error
            ];
        }

        $stmt->bind_param(
            "sssssidds",
            $titolo, $descrizione, $isbn, $anno_uscita, $editore,
            $lingua_id, $numero_pagine, $valore_copertina, $copertina_url
        );

        if ($stmt->execute()) {
            $libro_id = $stmt->insert_id;

            // Gestione autori (relazione Many-to-Many)
            if (!empty($data['autori'])) {
                $this->associateAutori($libro_id, $data['autori']);
            }

            // Gestione generi (relazione Many-to-Many)
            if (!empty($data['generi'])) {
                $this->associateGeneri($libro_id, $data['generi']);
            }

            return [
                'success' => true,
                'id' => $libro_id,
                'message' => 'Libro inserito con successo'
            ];
        }

        return [
            'success' => false,
            'message' => 'Errore inserimento: ' . $stmt->error
        ];
    }

    /**
     * READ - Legge un singolo libro con tutte le relazioni
     */
    public function getLibro($id) {
        $query = "SELECT l.*, 
                  GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
                  GROUP_CONCAT(DISTINCT g.nome SEPARATOR ', ') as generi,
                  COUNT(DISTINCT i.id_inventario) as copie_totali,
                  SUM(CASE WHEN i.stato = 'DISPONIBILE' THEN 1 ELSE 0 END) as copie_disponibili
                  FROM Libri l
                  LEFT JOIN Libri_Autori la ON l.id_libro = la.id_libro
                  LEFT JOIN Autori a ON la.id_autore = a.id
                  LEFT JOIN Libri_Generi lg ON l.id_libro = lg.id_libro
                  LEFT JOIN Generi g ON lg.id_genere = g.id
                  LEFT JOIN Inventari i ON l.id_libro = i.id_libro
                  WHERE l.id_libro = ?
                  GROUP BY l.id_libro";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * READ ALL - Lista tutti i libri con paginazione
     */
    public function getAllLibri($page = 1, $perPage = 12) {
        $offset = ($page - 1) * $perPage;

        $query = "SELECT l.*, 
                  GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
                  COUNT(DISTINCT i.id_inventario) as copie_totali,
                  SUM(CASE WHEN i.stato = 'DISPONIBILE' THEN 1 ELSE 0 END) as copie_disponibili
                  FROM Libri l
                  LEFT JOIN Libri_Autori la ON l.id_libro = la.id_libro
                  LEFT JOIN Autori a ON la.id_autore = a.id
                  LEFT JOIN Inventari i ON l.id_libro = i.id_libro
                  GROUP BY l.id_libro
                  ORDER BY l.titolo ASC
                  LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $libri = [];
        while ($row = $result->fetch_assoc()) {
            $libri[] = $row;
        }

        // Conta totale per paginazione
        $countQuery = "SELECT COUNT(*) as total FROM Libri";
        $total = $this->conn->query($countQuery)->fetch_assoc()['total'];

        return [
            'libri' => $libri,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $perPage)
        ];
    }

    /**
     * UPDATE - Aggiorna un libro esistente
     */
    public function updateLibro($id, $data) {
        // Validazione ISBN se presente
        if (!empty($data['isbn'])) {
            $isbnCheck = ISBNValidator::validate($data['isbn']);
            if (!$isbnCheck['valid']) {
                return ['success' => false, 'message' => 'ISBN non valido'];
            }
            $data['isbn'] = $isbnCheck['cleaned'];
        }

        $query = "UPDATE Libri SET 
                  titolo = ?, descrizione = ?, isbn = ?, anno_uscita = ?, 
                  editore = ?, lingua_id = ?, numero_pagine = ?, 
                  valore_copertina = ?, copertina_url = ?
                  WHERE id_libro = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssssiddsi",
            $data['titolo'], $data['descrizione'], $data['isbn'],
            $data['anno_uscita'], $data['editore'], $data['lingua_id'],
            $data['numero_pagine'], $data['valore_copertina'],
            $data['copertina_url'], $id
        );

        if ($stmt->execute()) {
            // Aggiorna relazioni
            if (isset($data['autori'])) {
                $this->updateAutori($id, $data['autori']);
            }
            if (isset($data['generi'])) {
                $this->updateGeneri($id, $data['generi']);
            }

            return ['success' => true, 'message' => 'Libro aggiornato'];
        }

        return ['success' => false, 'message' => $stmt->error];
    }

    /**
     * DELETE - Elimina un libro (solo se nessuna copia esiste)
     */
    public function deleteLibro($id) {
        // Verifica copie esistenti
        $checkQuery = "SELECT COUNT(*) as count FROM Inventari WHERE id_libro = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];

        if ($count > 0) {
            return [
                'success' => false,
                'message' => "Impossibile eliminare: esistono $count copie collegate"
            ];
        }

        // Elimina libro (CASCADE elimina automaticamente relazioni)
        $query = "DELETE FROM Libri WHERE id_libro = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Libro eliminato'];
        }

        return ['success' => false, 'message' => $stmt->error];
    }

    /**
     * COPIE - Crea una nuova copia fisica di un libro
     * Issue 3.2: Gestione entità copie multiple
     */
    public function createCopia($libro_id, $data) {
        $query = "INSERT INTO Inventari (id_libro, id_rfid, collocazione, stato, condizione) 
                  VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iisss",
            $libro_id,
            $data['id_rfid'] ?? null,
            $data['collocazione'] ?? null,
            $data['stato'] ?? 'DISPONIBILE',
            $data['condizione'] ?? 'BUONO'
        );

        if ($stmt->execute()) {
            return [
                'success' => true,
                'copia_id' => $stmt->insert_id,
                'message' => 'Copia aggiunta'
            ];
        }

        return ['success' => false, 'message' => $stmt->error];
    }

    /**
     * COPIE - Ottiene tutte le copie di un libro
     */
    public function getCopieLibro($libro_id) {
        $query = "SELECT i.*, r.rfid 
                  FROM Inventari i
                  LEFT JOIN RFID r ON i.id_rfid = r.id_rfid
                  WHERE i.id_libro = ?
                  ORDER BY i.collocazione";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $libro_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $copie = [];
        while ($row = $result->fetch_assoc()) {
            $copie[] = $row;
        }

        return $copie;
    }

    /**
     * COPIE - Aggiorna stato di una copia
     */
    public function updateStatoCopia($copia_id, $stato, $condizione = null) {
        if ($condizione) {
            $query = "UPDATE Inventari SET stato = ?, condizione = ? WHERE id_inventario = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ssi", $stato, $condizione, $copia_id);
        } else {
            $query = "UPDATE Inventari SET stato = ? WHERE id_inventario = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("si", $stato, $copia_id);
        }

        return $stmt->execute();
    }

    // === UTILITY PRIVATE ===

    private function sanitize($string) {
        if ($string === null) return null;
        return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
    }

    private function associateAutori($libro_id, $autori_ids) {
        foreach ($autori_ids as $autore_id) {
            $query = "INSERT IGNORE INTO Libri_Autori (id_libro, id_autore) VALUES (?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $libro_id, $autore_id);
            $stmt->execute();
        }
    }

    private function associateGeneri($libro_id, $generi_ids) {
        foreach ($generi_ids as $genere_id) {
            $query = "INSERT IGNORE INTO Libri_Generi (id_libro, id_genere) VALUES (?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $libro_id, $genere_id);
            $stmt->execute();
        }
    }

    private function updateAutori($libro_id, $autori_ids) {
        // Rimuovi vecchie associazioni
        $query = "DELETE FROM Libri_Autori WHERE id_libro = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $libro_id);
        $stmt->execute();

        // Aggiungi nuove
        $this->associateAutori($libro_id, $autori_ids);
    }

    private function updateGeneri($libro_id, $generi_ids) {
        $query = "DELETE FROM Libri_Generi WHERE id_libro = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $libro_id);
        $stmt->execute();

        $this->associateGeneri($libro_id, $generi_ids);
    }
}