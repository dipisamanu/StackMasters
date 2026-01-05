<?php
/**
 * AJAX Endpoint - Recupera dati libro da Google Books (Debug Version)
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
        '../../src/Services/GoogleBooksService.php'
    ];

    foreach ($paths as $path) {
        if (!file_exists($path)) {
            throw new Exception("File di sistema mancante: $path");
        }
        require_once $path;
    }

    // 1. Controllo Permessi
    // Gestione flessibile del ruolo (Array o Stringa)
    $roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? null;
    $roleName = is_array($roleData) ? ($roleData['nome'] ?? '') : $roleData;

    if (!isset($_SESSION['user_id']) || $roleName !== 'Bibliotecario') {
        throw new Exception("Accesso negato. Ruolo rilevato: " . htmlspecialchars((string)$roleName));
    }

    // 2. Recupero Input
    $isbn = $_GET['isbn'] ?? '';
    if (empty($isbn)) {
        throw new Exception("ISBN non fornito.");
    }

    // 3. Chiamata al Servizio
    $service = new GoogleBooksService();
    $bookData = $service->fetchByIsbn($isbn);

    ob_clean();

    if ($bookData) {
        echo json_encode(['success' => true, 'data' => $bookData]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "Nessun risultato trovato per ISBN: $isbn"
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