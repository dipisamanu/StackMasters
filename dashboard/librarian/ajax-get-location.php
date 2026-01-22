<?php
/**
 * AJAX Endpoint - Trova primo scaffale libero
 * File: dashboard/librarian/ajax-get-location.php
 */

// Disabilita output HTML per non rompere il JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json');

try {
    require_once '../../src/config/session.php';
    require_once '../../src/Models/InventoryModel.php';

    // Recupera il dato grezzo dalla sessione
    $roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? null;

    // Estrai il nome del ruolo (gestisce sia Stringa che Array)
    $roleName = '';
    if (is_array($roleData)) {
        $roleName = $roleData['nome'] ?? ''; // Se è un array, prende la chiave 'nome'
    } elseif (is_string($roleData)) {
        $roleName = $roleData; // Se è già stringa
    }

    // Controllo permessi
    if (!isset($_SESSION['user_id']) || $roleName !== 'Bibliotecario') {
        $debugInfo = [
            'user_id' => $_SESSION['user_id'] ?? 'NULL',
            'role_found' => $roleName, // Mostriamo cosa abbiamo estratto
            'role_raw' => $roleData // Mostriamo il dato grezzo
        ];
        throw new Exception("Non autorizzato. Debug: " . json_encode($debugInfo));
    }

    $model = new InventoryModel();
    $freeSpot = $model->findFirstFreeLocation();

    // Pulisci e invia
    ob_clean();
    echo json_encode([
        'success' => true,
        'location' => $freeSpot
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;