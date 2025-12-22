<?php

class LibrarianController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Mostra la pagina "Nuovo Prestito"
     */
    public function nuovoPrestito()
    {
        $data = [
            'message' => $_SESSION['message'] ?? null,
            'message_type' => $_SESSION['message_type'] ?? null,
            'scanned_user' => $_SESSION['scanned_user'] ?? null
        ];

        unset($_SESSION['message'], $_SESSION['message_type'], $_SESSION['scanned_user']);

        require 'src/Controllers/LoanController.php';
    }

    /**
     * POST /bibliotecario/registra-prestito
     * → Registra il prestito
     * → Genera PDF
     */
    public function registraPrestito()
    {
        try {
            // ===============================
            // 1️⃣ INPUT
            // ===============================
            $cfUtente = trim($_POST['user_barcode'] ?? '');
            $codiceLibro = trim($_POST['book_barcode'] ?? '');

            if (!$cfUtente || !$codiceLibro) {
                throw new Exception("Dati mancanti");
            }

            // ===============================
            // 2️⃣ RISOLUZIONE BARCODE
            // ===============================
            $utenteId = $this->getUtenteIdByCodiceFiscale($cfUtente);
            $inventarioId = $this->getInventarioIdByCodice($codiceLibro);

            if (!$utenteId) {
                throw new Exception("Utente non trovato");
            }

            if (!$inventarioId) {
                throw new Exception("Libro non trovato o non disponibile");
            }

            // ===============================
            // 3️⃣ BUSINESS LOGIC
            // ===============================
            $loanController = new LoanController();
            $result = $loanController->registraPrestito($utenteId, $inventarioId);

            // ===============================
            // 4️⃣ PDF RICEVUTA
            // ===============================
            require_once 'src/Helpers/RicevutaPrestitoPDF.php';

            $pdf = new RicevutaPrestitoPDF($this->db);
            $pdf->generaPerUtente($utenteId);

            exit; // IMPORTANTISSIMO: ferma output HTML

        } catch (Exception $e) {
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['message_type'] = 'error';
            $_SESSION['scanned_user'] = $_POST['user_barcode'] ?? null;

            header('Location: /bibliotecario/nuovo-prestito');
            exit;
        }
    }

    // ===================================================
    // 🔧 METODI PRIVATI DI SUPPORTO
    // ===================================================

    private function getUtenteIdByCodiceFiscale(string $cf): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id_utente
            FROM Utenti
            WHERE codice_fiscale = ?
            LIMIT 1
        ");
        $stmt->execute([$cf]);
        $id = $stmt->fetchColumn();

        return $id ? (int)$id : null;
    }

    private function getInventarioIdByCodice(string $codice): ?int
    {
        // supporta EAN13 / inventario interno
        $stmt = $this->db->prepare("
            SELECT i.id_inventario
            FROM Inventari i
            LEFT JOIN Libri l ON i.id_libro = l.id_libro
            WHERE i.codice_inventario = ?
               OR l.isbn = ?
            LIMIT 1
        ");
        $stmt->execute([$codice, $codice]);
        $id = $stmt->fetchColumn();

        return $id ? (int)$id : null;
    }
}
