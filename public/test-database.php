<?php
/**
 * Test Database - Verifica che il database sia stato creato correttamente
 * File: public/test-database.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../src/config/database.php';

$results = [];

try {
    $db = getDB();
    $results[] = ['status' => 'success', 'message' => '✅ Connessione al database riuscita'];

    // Conta utenti
    $stmt = $db->query("SELECT COUNT(*) FROM Utenti");
    $countUtenti = $stmt->fetchColumn();
    $results[] = ['status' => 'info', 'message' => "✅ Utenti nel database: $countUtenti"];

    // Conta libri
    $stmt = $db->query("SELECT COUNT(*) FROM Libri");
    $countLibri = $stmt->fetchColumn();
    $results[] = ['status' => 'info', 'message' => "✅ Libri nel database: $countLibri"];

    // Conta prestiti
    $stmt = $db->query("SELECT COUNT(*) FROM Prestiti");
    $countPrestiti = $stmt->fetchColumn();
    $results[] = ['status' => 'info', 'message' => "✅ Prestiti nel database: $countPrestiti"];

    // Conta ruoli
    $stmt = $db->query("SELECT COUNT(*) FROM Ruoli");
    $countRuoli = $stmt->fetchColumn();
    $results[] = ['status' => 'info', 'message' => "✅ Ruoli nel database: $countRuoli"];

    // Lista utenti
    $stmt = $db->query("SELECT id_utente, email, nome, cognome FROM Utenti");
    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = ['status' => 'success', 'message' => '<strong>Utenti nel database:</strong>'];
    foreach ($utenti as $user) {
        $results[] = ['status' => 'info', 'message' => "• {$user['nome']} {$user['cognome']} ({$user['email']})"];
    }

    // Verifica ruoli
    $stmt = $db->query("SELECT id_ruolo, nome, priorita FROM Ruoli");
    $ruoli = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = ['status' => 'success', 'message' => '<strong>Ruoli nel database:</strong>'];
    foreach ($ruoli as $ruolo) {
        $results[] = ['status' => 'info', 'message' => "• {$ruolo['nome']} (Priorità: {$ruolo['priorita']})"];
    }

    // Verifica utenti-ruoli
    $stmt = $db->query("SELECT u.email, r.nome FROM Utenti u INNER JOIN Utenti_Ruoli ur ON u.id_utente = ur.id_utente INNER JOIN Ruoli r ON ur.id_ruolo = r.id_ruolo");
    $utenteRuoli = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = ['status' => 'success', 'message' => '<strong>Assegnazioni Utenti-Ruoli:</strong>'];
    foreach ($utenteRuoli as $ur) {
        $results[] = ['status' => 'info', 'message' => "• {$ur['email']} → {$ur['nome']}"];
    }

    // Sommario finale
    $results[] = ['status' => 'success', 'message' => '<strong style="color: green;">✅ DATABASE COMPLETO E FUNZIONANTE!</strong>'];

} catch (Exception $e) {
    error_log("Errore test database: " . $e->getMessage());
    $results[] = [
        'status' => 'error',
        'message' => '❌ Errore: ' . $e->getMessage()
    ];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Database - StackMasters</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; }
        .header { background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .header h1 { color: #333; margin-bottom: 10px; }
        .result { background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .result.success { border-left-color: #28a745; background: #f0f8f4; color: #155724; }
        .result.error { border-left-color: #dc3545; background: #fff5f5; color: #721c24; }
        .result.info { border-left-color: #17a2b8; background: #f0f8fb; color: #0c5460; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🗄️ Test Database</h1>
        <p>Verifica che il database sia stato creato correttamente</p>
    </div>

    <?php foreach ($results as $result): ?>
        <div class="result <?= $result['status'] ?>">
            <?= $result['message'] ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>

