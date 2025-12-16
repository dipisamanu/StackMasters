<?php
/**
 * Cambio Password Utente
 * File: dashboard/student/change-password.php
 */

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF non valido';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Verifica password attuale
        $stmt = $db->prepare("SELECT password FROM Utenti WHERE id_utente = ?");
        $stmt->execute([$userId]);
        $hashedPassword = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hashedPassword)) {
            $errors[] = "Password attuale non corretta";
        }

        // Validazione nuova password
        if (strlen($newPassword) < 8) {
            $errors[] = "La nuova password deve essere di almeno 8 caratteri";
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = "La password deve contenere almeno una lettera maiuscola";
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = "La password deve contenere almeno un numero";
        }
        if (!preg_match('/[\W_]/', $newPassword)) {
            $errors[] = "La password deve contenere almeno un simbolo speciale";
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = "Le due password non coincidono";
        }
        if ($currentPassword === $newPassword) {
            $errors[] = "La nuova password deve essere diversa da quella attuale";
        }

        if (empty($errors)) {
            try {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

                $stmtUpdate = $db->prepare("UPDATE Utenti SET password = ? WHERE id_utente = ?");
                $stmtUpdate->execute([$newHash, $userId]);

                // Log CORRETTO
                $db->prepare("
                    INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address)
                    VALUES (?, 'MODIFICA_PASSWORD', 'Password cambiata', INET_ATON(?))
                ")->execute([$userId, $_SERVER['REMOTE_ADDR']]);

                $success = true;

            } catch (Exception $e) {
                error_log("Errore cambio password: " . $e->getMessage());
                $errors[] = "Errore durante il cambio password. Riprova.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia Password - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert ul {
            margin: 10px 0 0 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #bf2121;
            box-shadow: 0 0 0 3px rgba(191, 33, 33, 0.1);
        }

        .pw-requirements {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pw-item {
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 12px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            color: #666;
            transition: all 0.3s ease;
        }

        .pw-item.invalid {
            border-color: #ffcccc;
            background-color: #fff5f5;
            color: #cc0000;
        }

        .pw-item.valid {
            border-color: #c3e6cb;
            background-color: #d4edda;
            color: #155724;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #bf2121;
            color: white;
        }

        .btn-primary:hover {
            background: #931b1b;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🔒 Cambia Password</h1>
        <p class="subtitle">Aggiorna la tua password per mantenere l'account sicuro</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ Password cambiata con successo! Per sicurezza, effettua nuovamente il login.
            </div>
            <div class="actions">
                <a href="../../public/logout.php" class="btn btn-primary">Vai al Login</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Errori:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="form-group">
                    <label for="current_password">Password Attuale *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">Nuova Password *</label>
                    <input type="password" id="new_password" name="new_password" required>

                    <div class="pw-requirements" id="pwHelp">
                        <div class="pw-item invalid" id="pwLen">8 caratteri</div>
                        <div class="pw-item invalid" id="pwUpper">1 maiuscola</div>
                        <div class="pw-item invalid" id="pwDigit">1 numero</div>
                        <div class="pw-item invalid" id="pwSpecial">1 simbolo</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Conferma Nuova Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">💾 Cambia Password</button>
                    <a href="profile.php" class="btn btn-secondary">← Annulla</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    const pass = document.getElementById('new_password');

    function checkPasswordRules(pw) {
        return {
            len: pw.length >= 8,
            upper: /[A-Z]/.test(pw),
            digit: /[0-9]/.test(pw),
            special: /[\W_]/.test(pw)
        };
    }

    function updatePasswordUI() {
        const val = pass.value;
        const rules = checkPasswordRules(val);

        const map = {
            pwLen: rules.len,
            pwUpper: rules.upper,
            pwDigit: rules.digit,
            pwSpecial: rules.special
        };

        for (let id in map) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.toggle("valid", map[id]);
                el.classList.toggle("invalid", !map[id]);
            }
        }
    }

    if (pass) {
        pass.addEventListener("input", updatePasswordUI);
    }
</script>
</body>
</html>