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
    $code = trim($_GET['id'] ?? $_GET['isbn'] ?? '');
    if (empty($code)) {
        throw new Exception("Codice identificativo mancante.");
    }

    // 4. Connessione al DB
    $db = Database::getInstance()->getConnection();

    // Se è un ISBN (lunghezza 10 o 13), cerchiamo il libro generico
    // Se è un ID Inventario (numerico o stringa breve), cerchiamo la copia specifica
    
    // Logica migliorata:
    // Se cerchiamo per ISBN (parametro isbn o id lungo), restituiamo i dati del libro (per form creazione)
    // Se cerchiamo per ID Inventario (parametro id), restituiamo i dati della copia (per prestito)

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