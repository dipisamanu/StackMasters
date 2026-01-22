<?php
/**
 * Processo Gestione Libri (Versione Corretta e Ottimizzata)
 * File: dashboard/librarian/process-book.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';
require_once '../../src/Helpers/IsbnValidator.php';

// Protezione accesso: Solo i bibliotecari possono gestire il catalogo
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

        // GESTIONE UPLOAD IMMAGINE
        // Gestiamo il file qui e passiamo l'URL risultante al modello tramite $_POST
        if (isset($_FILES['copertina']) && $_FILES['copertina']['error'] === UPLOAD_ERR_OK) {
            // Definiamo il percorso di upload relativo alla cartella public
            $uploadDir = '../../public/assets/uploads/covers/';

            // Creiamo la directory se non esiste
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['copertina']['name'], PATHINFO_EXTENSION));
            // Generiamo un nome univoco per evitare sovrascritture
            $newFileName = uniqid('cover_', true) . '.' . $fileExtension;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['copertina']['tmp_name'], $destination)) {
                // Salviamo nel POST il percorso relativo che verrà memorizzato nel database
                $_POST['copertina_url'] = 'assets/uploads/covers/' . $newFileName;
            }
        }
    }

    // ESECUZIONE AZIONI
    if ($action === 'create') {
        // Il Model userà internamente linkAuthorByName() leggendo $_POST['autore']
        $bookModel->create($_POST);
        $_SESSION['flash_success'] = "Libro aggiunto con successo al catalogo!";

    } elseif ($action === 'update') {
        $id = (int)($_POST['id_libro'] ?? 0);
        if (!$id) throw new Exception("Identificativo libro mancante per l'aggiornamento.");

        // Aggiorna i dati. Se copertina_url è presente nel POST (nuova o vecchia), viene salvata
        $bookModel->update($id, $_POST);
        $_SESSION['flash_success'] = "Informazioni del libro aggiornate correttamente!";

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id_libro'] ?? 0);
        if (!$id) throw new Exception("Identificativo libro mancante per l'eliminazione.");

        // Esegue il Soft Delete come definito nel Model
        $bookModel->delete($id);
        $_SESSION['flash_success'] = "Il libro è stato rimosso dal catalogo attivo.";
    }

} catch (Exception $e) {
    // In caso di errore, salviamo il messaggio e i dati inseriti per non doverli riscrivere
    $_SESSION['flash_error'] = "<i class='fas fa-exclamation-triangle'></i> Errore durante l'operazione: " . $e->getMessage();
    $_SESSION['form_data'] = $_POST;
}

// Ritorna sempre alla pagina principale del catalogo
header('Location: books.php');
exit;

/**
 * Validazione dei dati obbligatori e dell'integrità dei campi
 * @throws Exception
 */
function validateBookData(array $data): void
{
    if (empty(trim($data['titolo']))) {
        throw new Exception("Il titolo del libro è un campo obbligatorio.");
    }

    if (empty(trim($data['autore']))) {
        throw new Exception("È necessario specificare almeno un autore.");
    }

    // Validazione ISBN se presente
    $isbn = trim($data['isbn'] ?? '');
    if (!empty($isbn)) {
        // Pulizia da eventuali trattini o spazi prima del controllo
        $isbnClean = str_replace(['-', ' '], '', $isbn);
        if (!IsbnValidator::validate($isbnClean)) {
            throw new Exception("Il codice ISBN inserito non è valido (formato o checksum errato).");
        }
    }
}