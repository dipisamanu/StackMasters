<?php
/**
 * Processo Gestione Libri (Con Validazione ISBN Checksum)
 * File: dashboard/librarian/process-book.php
 */

use Ottaviodipisa\StackMasters\Helpers\IsbnValidator;

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';
// Includi il nuovo helper (aggiusta il percorso se necessario)
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
    // 1. Encoding
    foreach ($data as $key => $value) {
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            throw new Exception("Caratteri non validi in $key");
        }
    }

    // 2. Titolo
    if (empty(trim($data['titolo']))) throw new Exception("Titolo obbligatorio.");
    if (mb_strlen($data['titolo']) > 100) throw new Exception("Titolo troppo lungo (max 100).");

    // 3. Autore
    if (empty(trim($data['autore']))) throw new Exception("Autore obbligatorio.");

    // 4. Anno
    $anno = $data['anno'] ?? '';
    if (!empty($anno)) {
        if (!is_numeric($anno) || $anno < 1000 || $anno > date('Y')+2) {
            throw new Exception("Anno non valido.");
        }
    }

    // 5. VALIDAZIONE ISBN, 3.3
    $isbn = $data['isbn'] ?? '';
    if (!empty($isbn)) {
        // Se l'utente ha inserito qualcosa nel campo ISBN, lo validiamo seriamente
        if (!IsbnValidator::validate($isbn)) {
            throw new Exception("Codice ISBN non valido (Checksum errato). Controlla di averlo digitato bene.");
        }
    }
}
?>