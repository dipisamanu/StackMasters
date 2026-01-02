<?php
/**
 * Processo Gestione Libri
 * File: dashboard/librarian/process-book.php
 */

// I percorsi includono ../../ perché siamo in dashboard/librarian
require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';

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
        if (empty($_POST['titolo']) || empty($_POST['autore'])) {
            throw new Exception("Titolo e Autore sono obbligatori.");
        }

        if ($bookModel->create($_POST)) {
            $_SESSION['flash_success'] = "Libro inserito correttamente!";
        } else {
            throw new Exception("Errore nel salvataggio.");
        }

    } elseif ($action === 'update') {
        $id = $_POST['id_libro'] ?? 0;
        if (!$id) throw new Exception("ID Libro mancante per la modifica.");

        if ($bookModel->update($id, $_POST)) {
            $_SESSION['flash_success'] = "Dati del libro aggiornati!";
        } else {
            throw new Exception("Nessuna modifica effettuata o errore.");
        }

    } elseif ($action === 'delete') {
        $id = $_POST['id_libro'] ?? 0;

        // delete() lancia eccezione se ci sono prestiti attivi
        if ($bookModel->delete($id)) {
            $_SESSION['flash_success'] = "Libro eliminato.";
        }
    }

} catch (Exception $e) {
    $_SESSION['flash_error'] = "Errore: " . $e->getMessage();
}

// Redirect alla pagina principale
header('Location: books.php');
exit;