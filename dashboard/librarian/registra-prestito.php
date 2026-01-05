<?php

require_once __DIR__ . '/../../src/config/database.php'; // Assumi che configuri Database::getInstance()
require_once __DIR__ . '/../../src/Helpers/RicevutaPrestitoPDF.php'; // TCPDF helper
require_once __DIR__ . '/../../vendor/autoload.php'; // eventuali altre dipendenze

use Ottaviodipisa\StackMasters\Controllers\LoanController;

$controller = new LoanController();

// Recupera dati dal form
$userBarcode = $_POST['user_barcode'] ?? '';
$bookBarcode = $_POST['book_barcode'] ?? '';

try {
    if (empty($userBarcode) || empty($bookBarcode)) {
        throw new Exception("Inserire sia il codice utente che il codice del libro.");
    }

    $db = Database::getInstance()->getConnection();

    // 1. Trova utente
    $stmt = $db->prepare("SELECT id_utente, nome, cognome FROM utenti WHERE cf = ? OR id_rfid = ?");
    $stmt->execute([$userBarcode, $userBarcode]);
    $utente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$utente) {
        throw new Exception("Utente non trovato per codice: $userBarcode");
    }

    // 2. Trova copia libro disponibile (ISBN o RFID)
    $stmt = $db->prepare("
        SELECT i.id_inventario, i.id_libro, i.stato, l.titolo
        FROM inventari i
        LEFT JOIN libri l ON l.id_libro = i.id_libro
        LEFT JOIN rfid r ON r.id_rfid = i.id_rfid
        WHERE (l.isbn = :isbn OR r.rfid = :rfid)
          AND i.stato = 'DISPONIBILE'
        LIMIT 1
    ");
    $stmt->execute([
        'isbn' => $bookBarcode,
        'rfid' => $bookBarcode
    ]);
    $copia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$copia) {
        throw new Exception("Copia libro non trovata o non disponibile per codice: $bookBarcode");
    }

    // 3. Registra prestito tramite controller
    $result = $controller->registraPrestito((int)$utente['id_utente'], (int)$copia['id_inventario']);

    // 4. Genera PDF tramite helper TCPDF
    \Ottaviodipisa\StackMasters\Helpers\RicevutaPrestitoPDF::genera($result);

} catch (Exception $e) {
    echo "<h2>Errore:</h2><p>" . $e->getMessage() . "</p>";
}
