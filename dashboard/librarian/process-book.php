<?php
/**
 * Processo Gestione Libri (Conserva i trattini in input, ma valida checksum)
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
        $bookModel->create($_POST);
        $_SESSION['flash_success'] = "Libro aggiunto con successo!";

    } elseif ($action === 'update') {
        $id = $_POST['id_libro'] ?? 0;
        if (!$id) throw new Exception("ID mancante.");

        $bookModel->update($id, $_POST);
        $_SESSION['flash_success'] = "Libro aggiornato!";

    } elseif ($action === 'delete') {
        $id = $_POST['id_libro'] ?? 0;
        $bookModel->delete($id);
        $_SESSION['flash_success'] = "Libro eliminato.";
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
    if (mb_strlen($data['titolo']) > 100) throw new Exception("Titolo troppo lungo (max 100).");

    if (empty(trim($data['autore']))) throw new Exception("Autore obbligatorio.");

    $anno = $data['anno'] ?? '';
    if (!empty($anno)) {
        if (!is_numeric($anno) || $anno < 1000 || $anno > date('Y')+2) {
            throw new Exception("Anno non valido.");
        }
    }

    // VALIDAZIONE ISBN CHECKSUM (User Input raw)
    $isbn = $data['isbn'] ?? '';
    if (!empty($isbn)) {
        // IsbnValidator::validate() pulisce da sola i trattini prima di calcolare
        if (!IsbnValidator::validate($isbn)) {
            throw new Exception("Codice ISBN non valido (Checksum errato).");
        }
    }
}
?>