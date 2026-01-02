<?php
/**
 * Processo Gestione Libri
 * File: dashboard/librarian/process-book.php
 */

require_once '../../../src/config/session.php';
require_once '../../../src/Models/BookModel.php';

// Sicurezza: Solo Bibliotecari
Session::requireRole('Bibliotecario');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit;
}

$action = $_POST['action'] ?? '';
$bookModel = new BookModel();

try {
    if ($action === 'create') {
        // Validazione minima
        if (empty($_POST['titolo']) || empty($_POST['autore'])) {
            throw new Exception("Titolo e Autore sono obbligatori.");
        }

        if ($bookModel->create($_POST)) {
            $_SESSION['flash_success'] = "Libro inserito nel catalogo!";
        } else {
            throw new Exception("Errore generico durante il salvataggio.");
        }

    } elseif ($action === 'delete') {
        $id = $_POST['id_libro'] ?? 0;
        if ($bookModel->delete($id)) {
            $_SESSION['flash_success'] = "Libro eliminato.";
        } else {
            throw new Exception("Impossibile eliminare il libro.");
        }
    }

} catch (Exception $e) {
    $_SESSION['flash_error'] = "Errore: " . $e->getMessage();
}

header('Location: books.php');
exit;