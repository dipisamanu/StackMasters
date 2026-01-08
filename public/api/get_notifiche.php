<?php
// FILE: public/api/get_notifiche.php
header('Content-Type: application/json');

// Carichiamo l'autoloader anche qui per usare la classe Database
require_once __DIR__ . '/../../vendor/autoload.php';
use Ottaviodipisa\StackMasters\Core\Database;
use Dotenv\Dotenv;

session_start();

// Verifica Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

// Carica .env
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

try {
    $pdo = Database::getInstance()->getConnection();

    // Prendi notifiche NON lette
    $stmt = $pdo->prepare("SELECT * FROM Notifiche_Web WHERE id_utente = ? AND letto = 0 ORDER BY data_creazione DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'count' => count($data), 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}