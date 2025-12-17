<?php
/**
 * DEBUG VERSION - Process Forgot Password
 * File: public/process-forgot-password-debug.php
 *
 * Usa questo file per fare debug degli errori
 * RIMUOVERE IN PRODUZIONE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>🔍 DEBUG: Forgot Password Process</h2>";
echo "<pre>";

session_start();

echo "1️⃣ Session started\n";

// Check POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Metodo non POST. Metodo ricevuto: " . $_SERVER['REQUEST_METHOD']);
}
echo "✅ Metodo POST\n";

// Check database connection
try {
    require_once '../src/config/database.php';
    echo "2️⃣ Database config caricato\n";

    $db = getDB();
    echo "✅ Connessione database OK\n";

    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM Utenti");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Test query OK - Utenti nel DB: " . $count['count'] . "\n\n";

} catch (Exception $e) {
    die("❌ ERRORE DATABASE: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

// Get email
$email = trim($_POST['email'] ?? '');
echo "3️⃣ Email ricevuta: " . htmlspecialchars($email) . "\n";

if (empty($email)) {
    die("❌ Email vuota");
}
echo "✅ Email non vuota\n";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("❌ Email non valida");
}
echo "✅ Email valida\n\n";

// Search user
echo "4️⃣ Cerco utente nel database...\n";
try {
    $stmt = $db->prepare("
        SELECT id_utente, nome, cognome, email, verificato
        FROM Utenti 
        WHERE LOWER(email) = LOWER(?)
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "⚠️ Utente NON trovato con email: " . $email . "\n";
        echo "Possibili cause:\n";
        echo "  - Email non registrata\n";
        echo "  - Email con maiuscole/minuscole diverse\n";
        echo "  - Spazi extra nella email\n";
        die();
    }

    echo "✅ Utente trovato:\n";
    echo "  - ID: " . $user['id_utente'] . "\n";
    echo "  - Nome: " . $user['nome'] . " " . $user['cognome'] . "\n";
    echo "  - Email: " . $user['email'] . "\n";
    echo "  - Verificato: " . ($user['verificato'] ? 'SI' : 'NO') . "\n\n";

    if (!$user['verificato']) {
        die("❌ Account non verificato. L'utente deve prima verificare l'email.");
    }

} catch (Exception $e) {
    die("❌ ERRORE QUERY UTENTE: " . $e->getMessage());
}

// Check rate limiting
echo "5️⃣ Controllo rate limiting...\n";
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM Logs_Audit
        WHERE id_utente = ?
        AND azione = 'PASSWORD_RESET_REQUEST'
        AND timestamp > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$user['id_utente']]);
    $recentAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo "  - Tentativi ultimi 15 min: " . $recentAttempts . "\n";

    if ($recentAttempts >= 3) {
        die("❌ RATE LIMIT EXCEEDED: Troppi tentativi. Attendi 15 minuti.");
    }
    echo "✅ Rate limit OK\n\n";

} catch (Exception $e) {
    echo "⚠️ ERRORE CHECK RATE LIMIT (tabella Logs_Audit potrebbe non esistere): " . $e->getMessage() . "\n";
    echo "Continuo comunque...\n\n";
}

// Generate token
echo "6️⃣ Generazione token...\n";
try {
    $token = bin2hex(random_bytes(32));
    echo "✅ Token generato (lunghezza: " . strlen($token) . ")\n";

    $tokenHash = hash('sha256', $token);
    echo "✅ Token hash generato (lunghezza: " . strlen($tokenHash) . ")\n\n";

} catch (Exception $e) {
    die("❌ ERRORE GENERAZIONE TOKEN: " . $e->getMessage());
}

// Update database
echo "7️⃣ Aggiornamento database...\n";
try {
    $expiryTime = date('Y-m-d H:i:s', strtotime('+24 hours'));
    echo "  - Scadenza: " . $expiryTime . "\n";

    $stmt = $db->prepare("
        UPDATE Utenti 
        SET 
            token = ?,
            scadenza_verifica = ?
        WHERE id_utente = ?
    ");
    $result = $stmt->execute([$tokenHash, $expiryTime, $user['id_utente']]);

    if (!$result) {
        die("❌ ERRORE UPDATE: Query fallita");
    }

    $rowsAffected = $stmt->rowCount();
    echo "✅ Database aggiornato (righe: " . $rowsAffected . ")\n\n";

} catch (Exception $e) {
    die("❌ ERRORE UPDATE DATABASE: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

// Log audit (optional)
echo "8️⃣ Log audit...\n";
try {
    $db->prepare("
        INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address) 
        VALUES (?, 'PASSWORD_RESET_REQUEST', ?, INET_ATON(?))
    ")->execute([
        $user['id_utente'],
        "Richiesta reset password (DEBUG)",
        $_SERVER['REMOTE_ADDR']
    ]);
    echo "✅ Log audit creato\n\n";
} catch (Exception $e) {
    echo "⚠️ ERRORE LOG AUDIT: " . $e->getMessage() . "\n";
    echo "Continuo comunque...\n\n";
}

// Build reset link
echo "9️⃣ Costruzione link reset...\n";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$resetLink = $protocol . '://' . $host . '/StackMasters/public/reset-password.php?token=' . $token;
echo "✅ Link: " . htmlspecialchars($resetLink) . "\n\n";

// Email simulation (non invia)
echo "🔟 Email (SIMULATA - non inviata)...\n";
echo "  - A: " . $user['email'] . "\n";
echo "  - Oggetto: Reset Password - Biblioteca ITIS Rossi\n";
echo "  - Contiene link: " . htmlspecialchars($resetLink) . "\n";
echo "✅ Email preparata (in produzione verrebbe inviata)\n\n";

echo "</pre>";

echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3 style='color: #155724;'>✅ PROCESSO COMPLETATO CON SUCCESSO!</h3>";
echo "<p><strong>Token generato correttamente.</strong></p>";
echo "<p>Per testare, vai a questo link:</p>";
echo "<p><a href='" . htmlspecialchars($resetLink) . "' style='color: #155724; font-weight: bold;'>" . htmlspecialchars($resetLink) . "</a></p>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px;'>";
echo "<strong>⚠️ IMPORTANTE:</strong> Questo è un file di DEBUG. Rimuovilo prima di andare in produzione!";
echo "</div>";