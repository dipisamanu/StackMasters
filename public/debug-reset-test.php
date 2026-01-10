<?php
/**
 * TEST GENERATORE LINK RESET
 * File: public/debug-reset-test.php
 * USAGE: Apri nel browser, inserisci email, clicca il link generato.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/session.php'; // Uniformità

$message = '';
$link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    try {
        $db = getDB();

        // Cerca utente
        $stmt = $db->prepare("SELECT id_utente FROM utenti WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Logica identica a process-forgot-password
            $tokenRaw = bin2hex(random_bytes(16)); // Token per URL
            $tokenDb = md5($tokenRaw);             // Hash per DB
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Aggiorna DB
            $upd = $db->prepare("UPDATE utenti SET token = ?, scadenza_verifica = ? WHERE id_utente = ?");
            $upd->execute([$tokenDb, $expiry, $user['id_utente']]);

            // Genera Link
            $host = $_SERVER['HTTP_HOST'];
            // Adatta il path se necessario
            $url = "http://$host/StackMasters/public/reset-password.php?token=$tokenRaw";

            $link = $url;
            $message = "✅ Token generato e salvato nel DB!";
        } else {
            $message = "❌ Email non trovata nel DB.";
        }

    } catch (Exception $e) {
        $message = "Errore: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <title>Debug Reset Link</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f4f4f4; }
        .box { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        input { padding: 10px; width: 70%; }
        button { padding: 10px; background: #bf2121; color: white; border: none; cursor: pointer; }
        .result { margin-top: 20px; padding: 15px; background: #e8f5e9; border: 1px solid #c8e6c9; word-break: break-all; }
    </style>
</head>
<body>
<div class="box">
    <h2>🛠 Generatore Link Reset (Debug)</h2>
    <p>Simula l'invio della mail e genera un link valido.</p>

    <form method="post">
        <input type="email" name="email" placeholder="Email utente..." required value="mario.rossi@example.com">
        <button type="submit">Genera Link</button>
    </form>

    <?php if ($message): ?>
        <h3 style="margin-top:20px;"><?= $message ?></h3>
    <?php endif; ?>

    <?php if ($link): ?>
        <div class="result">
            <strong>Link simulato (clicca per testare):</strong><br><br>
            <a href="<?= $link ?>" target="_blank" style="font-size: 18px; font-weight: bold; color: #0056b3;">
                <?= $link ?>
            </a>
        </div>
        <p><small>Nota: Questo link usa il token <code><?= htmlspecialchars($tokenRaw ?? '') ?></code> (in chiaro), mentre nel DB è salvato l'MD5.</small></p>
    <?php endif; ?>
</div>
</body>
</html>