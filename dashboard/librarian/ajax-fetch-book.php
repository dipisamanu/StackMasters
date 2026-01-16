<?php
/**
 * AJAX Endpoint - Recupera dati libro dal Database locale o da API esterne
 * File: dashboard/librarian/ajax-fetch-book.php
 * Supporta: Scansione Barcode (ID Inventario o ISBN)
 */

// Silenzia output HTML per evitare errori JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
header('Content-Type: application/json');

try {
    // 1. Inclusione Configurazione e Servizi
    require_once '../../src/config/session.php';
    require_once '../../src/config/database.php';
    require_once '../../src/Services/GoogleBooksService.php';
    require_once '../../src/Services/OpenLibraryService.php';

    // 2. Controllo Permessi
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Sessione scaduta o non valida.");
    }

    // 3. Recupero Input (ID Inventario o ISBN)
    $code = trim($_GET['id'] ?? $_GET['isbn'] ?? '');
    if (empty($code)) {
        throw new Exception("Codice identificativo mancante.");
    }

    // 4. Connessione al DB
    $db = Database::getInstance()->getConnection();

    // Se è un ISBN (lunghezza 10 o 13), cerchiamo il libro generico
    // Se è un ID Inventario (numerico o stringa breve), cerchiamo la copia specifica
    if (isset($_GET['isbn']) || strlen($code) >= 10) {
        // Ricerca Libro (Generica)
        $sql = "SELECT 
                    l.id_libro,
                    l.titolo, 
                    l.descrizione,
                    l.anno_uscita as anno,
                    l.editore,
                    l.numero_pagine as pagine,
                    l.immagine_copertina as copertina,
                    l.isbn,
                    GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autore
                FROM libri l
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE l.isbn = :code
                GROUP BY l.id_libro
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['code' => $code]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($book) {
             // Formatta anno
             if($book['anno']) $book['anno'] = date('Y', strtotime($book['anno']));
             
             // Formatta copertina
             if ($book['copertina'] && !str_starts_with($book['copertina'], 'http')) {
                 $book['copertina'] = '../../public/' . $book['copertina'];
             }

             ob_clean();
             echo json_encode(['success' => true, 'data' => $book]);
             exit;
        } else {
            // Se non trovato nel DB, prova con le API esterne
            $googleBooks = new GoogleBooksService();
            $bookData = $googleBooks->fetchByIsbn($code);

            if (!$bookData) {
                $openLibrary = new OpenLibraryService();
                $bookData = $openLibrary->fetchByIsbn($code);
            }

            if ($bookData) {
                ob_clean();
                echo json_encode(['success' => true, 'data' => $bookData]);
                exit;
            }
        }
    }

    // Ricerca Copia (Specifica per ID Inventario)
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
            GROUP BY i.id_inventario
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $code]);

    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    ob_clean();

    if ($book) {
        // Rinomino per coerenza con la risposta JSON precedente
        $book['id_inventario'] = $book['id_inventario'];
        $book['autori'] = $book['autori'];
        $book['immagine_copertina'] = $book['immagine_copertina'] ?: '../../public/assets/img/placeholder.png';
        
        // Aggiungo la condizione alla risposta
        echo json_encode([
            'success' => true,
            'data' => $book
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