<?php
/**
 * Processo Gestione Inventario
 * File: dashboard/librarian/process-inventory.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/InventoryModel.php';

Session::requireRole('Bibliotecario');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit;
}

$invModel = new InventoryModel();
$action = $_POST['action'] ?? '';
$bookId = $_POST['id_libro'] ?? 0;

$redirectUrl = "inventory.php?id_libro=" . $bookId;

try {
    if ($action === 'add_copy') {
        $rfid = trim($_POST['rfid'] ?? '');
        $collocazione = trim($_POST['collocazione'] ?? '');
        $condizione = $_POST['condizione'] ?? 'BUONO';

        if (empty($rfid)) throw new Exception("Devi inserire o generare un codice RFID.");
        if (empty($collocazione)) throw new Exception("La collocazione (scaffale) è obbligatoria.");

        $invModel->addCopy($bookId, $rfid, $collocazione, $condizione);
        $_SESSION['flash_success'] = "Nuova copia registrata correttamente.";

    } elseif ($action === 'update_copy') {
        $copyId = $_POST['id_inventario'] ?? 0;
        $collocazione = trim($_POST['collocazione'] ?? '');
        $condizione = $_POST['condizione'] ?? 'BUONO';
        $stato = $_POST['stato'] ?? 'DISPONIBILE';

        if (empty($collocazione)) throw new Exception("La collocazione non può essere vuota.");

        $invModel->updateCopy($copyId, $collocazione, $condizione, $stato);
        $_SESSION['flash_success'] = "Dati copia aggiornati.";

    } elseif ($action === 'delete_copy') {
        $copyId = $_POST['id_inventario'] ?? 0;
        $invModel->deleteCopy($copyId);
        $_SESSION['flash_success'] = "Copia rimossa dall'inventario.";
    }

} catch (Exception $e) {
    // Salviamo l'errore
    $_SESSION['flash_error'] = $e->getMessage();
    // Salviamo i dati inseriti per non farli riscrivere
    $_SESSION['form_data'] = $_POST;
}

header("Location: $redirectUrl");
exit;