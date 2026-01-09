<?php
/**
 * AJAX Endpoint - Recupera dati utente dal Database
 * File: dashboard/librarian/ajax-fetch-user.php
 */

header('Content-Type: application/json');
require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

$code = trim($_GET['code'] ?? '');

if (empty($code)) {
    echo json_encode(['success' => false, 'error' => 'Codice mancante']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Abbiamo rinominato i parametri in :id e :cf per evitare l'errore HY093
    // che impedisce il riutilizzo dello stesso segnaposto nominato.
    $sql = "SELECT u.id_utente, u.nome, u.cognome, u.email, r.nome as ruolo
            FROM utenti u
            LEFT JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente
            LEFT JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
            WHERE u.id_utente = :id OR u.cf = :cf
            LIMIT 1";

    $stmt = $db->prepare($sql);
    // Passiamo lo stesso valore a entrambi i parametri
    $stmt->execute([
        'id' => $code,
        'cf' => $code
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true,
            'id_utente' => $user['id_utente'],
            'nome' => $user['nome'],
            'cognome' => $user['cognome'],
            'ruolo' => $user['ruolo'] ?? 'Studente'
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}