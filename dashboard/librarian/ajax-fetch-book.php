<?php
/**
 * AJAX Endpoint - Recupera dati libro (Google Books + Open Library Fallback)
 * File: dashboard/librarian/ajax-fetch-book.php
 */

// Silenzia output HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
header('Content-Type: application/json');

try {
    // Percorsi relativi corretti
    $paths = [
        '../../src/config/session.php',
        '../../src/Services/GoogleBooksService.php',
        '../../src/Services/OpenLibraryService.php'
    ];

    foreach ($paths as $path) {
        if (!file_exists($path)) {
            throw new Exception("File di sistema mancante: $path");
        }
        require_once $path;
    }

    // 1. Controllo Permessi
    $roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? null;
    $roleName = is_array($roleData) ? ($roleData['nome'] ?? '') : $roleData;

    if (!isset($_SESSION['user_id']) || $roleName !== 'Bibliotecario') {
        throw new Exception("Accesso negato.");
    }

    // 2. Recupero Input
    $isbn = $_GET['isbn'] ?? '';
    if (empty($isbn)) {
        throw new Exception("ISBN non fornito.");
    }

    // 3. TENTATIVO 1: Google Books API
    $gbService = new GoogleBooksService();
    $bookData = $gbService->fetchByIsbn($isbn);
    $source = 'Google Books';

    // 4. TENTATIVO 2: Open Library API (Fallback)
    if (!$bookData) {
        $olService = new OpenLibraryService();
        $bookData = $olService->fetchByIsbn($isbn);
        $source = 'Open Library';
    }

    ob_clean();

    if ($bookData) {
        // Aggiungiamo la fonte ai dati per debug (opzionale, ma utile)
        $bookData['_source'] = $source;
        echo json_encode(['success' => true, 'data' => $bookData]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "Nessun risultato trovato per ISBN: $isbn (Cercato su Google e Open Library)"
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