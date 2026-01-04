<?php
/**
 * Processore per la gestione dell'inventario
 * File: dashboard/librarian/process-inventory.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/InventoryModel.php';

// Protezione accesso
Session::requireRole('Bibliotecario');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit;
}

$invModel = new InventoryModel();
$action = $_POST['action'] ?? '';
$bookId = $_POST['id_libro'] ?? 0;

// URL di reindirizzamento: torna sempre alla pagina inventario di quel libro
$redirectUrl = "inventory.php?id_libro=" . $bookId;

try {
    if ($action === 'add_copy') {
        $rfid = $_POST['rfid'] ?? '';
        $collocazione = $_POST['collocazione'] ?? '';
        $condizione = $_POST['condizione'] ?? 'BUONO';

        if (empty($rfid) || empty($collocazione)) {
            throw new Exception("RFID e Collocazione sono campi obbligatori.");
        }

        $invModel->addCopy($bookId, $rfid, $collocazione, $condizione);
        $_SESSION['flash_success'] = "Copia aggiunta all'inventario con successo.";

    } elseif ($action === 'update_copy') {
        $copyId = $_POST['id_inventario'] ?? 0;
        $collocazione = $_POST['collocazione'] ?? '';
        $condizione = $_POST['condizione'] ?? 'BUONO';
        $stato = $_POST['stato'] ?? 'DISPONIBILE';

        $invModel->updateCopy($copyId, $collocazione, $condizione, $stato);
        $_SESSION['flash_success'] = "Dati della copia aggiornati.";

    } elseif ($action === 'delete_copy') {
        $copyId = $_POST['id_inventario'] ?? 0;
        $invModel->deleteCopy($copyId);
        $_SESSION['flash_success'] = "Copia rimossa dall'inventario.";
    }

} catch (Exception $e) {
    $_SESSION['flash_error'] = "Errore: " . $e->getMessage();
}

header("Location: $redirectUrl");
exit;