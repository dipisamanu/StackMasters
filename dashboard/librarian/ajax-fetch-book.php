<?php
/**
 * AJAX Endpoint - Recupera dati libro dal Database locale (Versione Semplificata)
 * File: dashboard/librarian/ajax-fetch-book.php
 * Supporta: Scansione Barcode (ID Inventario o ISBN)
 */

// Silenzia output HTML per evitare errori JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
header('Content-Type: application/json');

try {
    // 1. Inclusione Configurazione e Sessione
    require_once '../../src/config/session.php';
    require_once '../../src/config/database.php';

    // 2. Controllo Permessi
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Sessione scaduta o non valida.");
    }

    // 3. Recupero Input (ID Inventario o ISBN)
    $code = trim($_GET['id'] ?? '');
    if (empty($code)) {
        throw new Exception("Codice identificativo mancante.");
    }

    // 4. Connessione al DB
    $db = Database::getInstance()->getConnection();

    /**
     * QUERY CORRETTA:
     * Usiamo due segnaposti diversi (:id e :isbn) per lo stesso valore $code
     * per evitare l'errore SQLSTATE[HY093] su configurazioni PDO con emulazione disattivata.
     */
    $sql = "SELECT 
                i.id_inventario, 
                i.stato, 
                i.collocazione, 
                i.condizione,
                l.titolo, 
                l.immagine_copertina,
                l.isbn,
                GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
            FROM inventari i
            JOIN libri l ON i.id_libro = l.id_libro
            LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
            LEFT JOIN autori a ON la.id_autore = a.id
            WHERE i.id_inventario = :id 
               OR l.isbn = :isbn
            GROUP BY i.id_inventario
            LIMIT 1";

    $stmt = $db->prepare($sql);
    // Passiamo lo stesso valore a entrambi i parametri
    $stmt->execute([
        'id'   => $code,
        'isbn' => $code
    ]);

    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    ob_clean();

    if ($book) {
        echo json_encode([
            'success' => true,
            'id_inventario' => $book['id_inventario'],
            'titolo' => $book['titolo'],
            'autori' => $book['autori'],
            'stato' => $book['stato'],
            'collocazione' => $book['collocazione'],
            'immagine_copertina' => $book['immagine_copertina'] ?: '../../public/assets/img/placeholder.png',
            'isbn' => $book['isbn']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "Nessun libro trovato con codice: $code"
        ]);
    }

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;