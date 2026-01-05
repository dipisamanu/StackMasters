<?php
/**
 * Processo Gestione Libri (Aggiornato per Soft Delete)
 * File: dashboard/librarian/process-book.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';
require_once '../../src/Helpers/IsbnValidator.php';

Session::requireRole('Bibliotecario');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit;
}

$action = $_POST['action'] ?? '';
$bookModel = new BookModel();

try {
    if (in_array($action, ['create', 'update'])) {
        validateBookData($_POST);
    }

    if ($action === 'create') {
        $bookModel->create($_POST, $_FILES);
        $_SESSION['flash_success'] = "Libro aggiunto con successo!";

    } elseif ($action === 'update') {
        $id = $_POST['id_libro'] ?? 0;
        if (!$id) throw new Exception("ID mancante.");

        $bookModel->update($id, $_POST, $_FILES);
        $_SESSION['flash_success'] = "Libro aggiornato!";

    } elseif ($action === 'delete') {
        // Questa azione ora esegue un SOFT DELETE (Archiviazione)
        $id = $_POST['id_libro'] ?? 0;
        $bookModel->delete($id);
        $_SESSION['flash_success'] = "Libro archiviato/rimosso dal catalogo.";
    }

} catch (Exception $e) {
    $_SESSION['flash_error'] = "⚠️ " . $e->getMessage();
    $_SESSION['form_data'] = $_POST;
}

header('Location: books.php');
exit;

function validateBookData(array $data) {
    foreach ($data as $key => $value) {
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            throw new Exception("Caratteri non validi in $key");
        }
    }

    if (empty(trim($data['titolo']))) throw new Exception("Titolo obbligatorio.");

    $isbn = $data['isbn'] ?? '';
    if (!empty($isbn)) {
        if (!IsbnValidator::validate($isbn)) {
            throw new Exception("Codice ISBN non valido (Checksum errato).");
        }
    }
}
?>