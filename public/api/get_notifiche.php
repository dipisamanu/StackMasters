<?php
/**
 * Endpoint API per la gestione delle notifiche utente
 * FILE: public/api/get_notifiche.php
 */
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/Models/NotificationManager.php';
// Se hai session.php, includilo, altrimenti usa session_start qui sotto
if (file_exists(__DIR__ . '/../../src/config/session.php')) {
    require_once __DIR__ . '/../../src/config/session.php';
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

header('Content-Type: application/json');

// 2. Controllo Utente Loggato
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non loggato']);
    exit;
}

try {
    // 3. Usiamo il Manager per recuperare le notifiche
    $notify = new \Ottaviodipisa\StackMasters\Models\NotificationManager();

    // SE È UNA POST (Click sulla notifica) -> Segna come letta
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['id'])) {
            $notify->markAsRead($input['id'], $_SESSION['user_id']);
            echo json_encode(['success' => true]);
        }
    } // SE È UNA GET (Polling automatico) -> Scarica lista
    else {
        // Scarica le ultime 10 notifiche
        $list = $notify->getUserNotifications($_SESSION['user_id'], 10);
        // Conta solo quelle non lette per il badge
        $count = $notify->getUnreadCount($_SESSION['user_id']);

        echo json_encode([
            'success' => true,
            'notifications' => $list,
            'unread' => $count
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}