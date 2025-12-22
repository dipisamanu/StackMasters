<?php
require_once __DIR__ . '/../../src/config/database.php'; // Assumi che configuri $pdo
require_once __DIR__ . '/../../src/Controllers/LoanController.php';
require_once __DIR__ . '/../../src/Helpers/RicevutaPrestitoPDF.php';
use Dompdf\Dompdf;

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
    $stmt = $db->prepare("SELECT id_utente, nome, cognome FROM Utenti WHERE cf = ? OR id_rfid = ?");
    $stmt->execute([$userBarcode, $userBarcode]);
    $utente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$utente) {
        throw new Exception("Utente non trovato per codice: $userBarcode");
    }

    // 2. Trova copia libro
    $stmt = $db->prepare("SELECT id_libro FROM Inventari WHERE id_libro =?  OR id_rfid = ?");
    $stmt->execute([$bookBarcode, $bookBarcode]);
    $copia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$copia) {
        throw new Exception("Copia libro non trovata per codice: $bookBarcode");
    }

    // 3. Registra prestito
    $result = $controller->registraPrestito((int)$utente['id_utente'], (int)$copia['id_inventario']);

    // 4. Genera PDF
    $dompdf = new Dompdf();
    $html = "
        <h1>Prestito Registrato</h1>
        <p><strong>Utente:</strong> {$utente['nome']} {$utente['cognome']} (ID: {$utente['id_utente']})</p>
        <p><strong>Copia Libro:</strong> ID {$copia['id_inventario']}</p>
        <p><strong>Data Scadenza:</strong> {$result['details']['data_scadenza']}</p>
        <p>{$result['details']['messaggio_prenotazione']}</p>
    ";
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Scarica PDF
    $filename = "prestito_{$utente['id_utente']}_{$copia['id_inventario']}.pdf";
    $dompdf->stream($filename, ["Attachment" => true]);

} catch (Exception $e) {
    echo "<h2>Errore:</h2><p>" . $e->getMessage() . "</p>";
}
