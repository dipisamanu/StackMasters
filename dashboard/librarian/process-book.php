<?php
/**
 * Processo Gestione Libri
 * File: dashboard/librarian/process-book.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';

Session::requireRole('Bibliotecario');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit;
}

$action = $_POST['action'] ?? '';
$bookModel = new BookModel();

try {
    // Validazione preventiva
    if (in_array($action, ['create', 'update'])) {
        validateBookData($_POST);
    }

    if ($action === 'create') {
        // create ora lancia eccezione se fallisce, non restituisce solo false
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
    // Cattura l'errore SQL esatto lanciato dal Model
    $_SESSION['flash_error'] = "⚠️ " . $e->getMessage();
    $_SESSION['form_data'] = $_POST;
}

header('Location: books.php');
exit;

function validateBookData(array $data) {
    // Encoding
    foreach ($data as $key => $value) {
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            throw new Exception("Caratteri non validi in $key");
        }
    }

    // Titolo (Max 100 come da DB)
    if (empty(trim($data['titolo']))) throw new Exception("Titolo obbligatorio.");
    if (mb_strlen($data['titolo']) > 100) throw new Exception("Titolo troppo lungo (max 100 caratteri).");

    // Autore
    if (empty(trim($data['autore']))) throw new Exception("Autore obbligatorio.");

    // Anno
    $anno = $data['anno'] ?? '';
    if (!empty($anno)) {
        if (!is_numeric($anno) || $anno < 1000 || $anno > date('Y')+2) {
            throw new Exception("Anno non valido.");
        }
    }

    // ISBN
    $isbn = preg_replace('/[^0-9X]/i', '', $data['isbn'] ?? '');
    if (!empty($data['isbn']) && strlen($isbn) !== 10 && strlen($isbn) !== 13) {
        throw new Exception("ISBN deve avere 10 o 13 cifre.");
    }
}
?>