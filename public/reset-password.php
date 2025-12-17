<?php
/**
 * Pagina Reset Password
 * File: public/reset-password.php
 *
 * EPIC 2.5 - Feature: Flow recupero password (Step 3)
 * Form per impostare nuova password usando il token ricevuto via email
 */

session_start();

require_once '../src/config/database.php';

// Se già loggato, reindirizza
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: ../dashboard/student/index.php');
    exit;
}

// Verifica presenza token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $_SESSION['login_error'] = 'Token mancante. Richiedi un nuovo link di reset.';
    header('Location: forgot-password.php');
    exit;
}

// Verifica validità token (senza consumarlo)
try {
    $db = getDB();
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare("
        SELECT id_utente, nome, cognome, email, scadenza_verifica
        FROM Utenti 
        WHERE token = ? AND scadenza_verifica > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['login_error'] = 'Token scaduto o non valido. Richiedi un nuovo link di reset.';
        header('Location: forgot-password.php');
        exit;
    }

    $expiryTime = strtotime($user['scadenza_verifica']);
    $remainingMinutes = round(($expiryTime - time()) / 60);

} catch (Exception $e) {
    error_log("ERRORE VERIFICA TOKEN: " . $e->getMessage());
    $_SESSION['login_error'] = 'Errore del sistema. Riprova più tardi.';
    header('Location: forgot-password.php');
    exit;
}

// Gestione messaggi
$error = $_SESSION['reset_error'] ?? '';
if (isset($_SESSION['reset_error'])) unset($_SESSION['reset_error']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imposta Nuova Password - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #9f3232 0%, #b57070 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .header h1 {
            color: #bf2121;
            font-size: 26px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .user-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .user-info strong {
            color: #004085;
        }

        .expiry-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #856404;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            animation: slideIn 0.3s ease;
            border: 1px solid;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .password-wrapper {
            position: relative;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 14px 45px 14px 15px;
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

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 20px;
            padding: 5px;
        }

        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 13px;
        }

        .password-requirements h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .requirement {
            padding: 8px 0;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirement .icon {
            font-size: 16px;
            width: 20px;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
        }

        .strength-meter {
            margin-top: 15px;
        }

        .strength-meter label {
            font-size: 13px;
            margin-bottom: 5px;
        }

        .strength-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
            border-radius: 4px;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            font-weight: 600;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: #bf2121;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn:hover:not(:disabled) {
            background: #931b1b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(191, 33, 33, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #bf2121;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="reset-container">
    <div class="header">
        <div class="icon">🔑</div>
        <h1>Imposta Nuova Password</h1>
        <p>Scegli una password sicura per il tuo account</p>
    </div>

    <div class="user-info">
        👤 Stai reimpostando la password per:<br>
        <strong><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></strong><br>
        <small><?= htmlspecialchars($user['email']) ?></small>
    </div>

    <?php if ($remainingMinutes <= 60): ?>
        <div class="expiry-warning">
            ⏰ <strong>Attenzione:</strong> Questo link scadrà tra <?= $remainingMinutes ?> minuti. Completa il reset ora!
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="process-reset-password.php" method="POST" id="resetForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
            <label for="password">Nuova Password</label>
            <div class="password-wrapper">
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                >
                <button type="button" class="toggle-password" id="togglePassword">👁️</button>
            </div>

            <!-- Strength meter -->
            <div class="strength-meter">
                <label>Robustezza Password:</label>
                <div class="strength-bar">
                    <div class="strength-fill" id="strengthFill"></div>
                </div>
                <div class="strength-text" id="strengthText"></div>
            </div>

            <!-- Requirements -->
            <div class="password-requirements">
                <h4>La password deve contenere:</h4>
                <div class="requirement" id="req-length">
                    <span class="icon">❌</span>
                    <span>Almeno 8 caratteri</span>
                </div>
                <div class="requirement" id="req-uppercase">
                    <span class="icon">❌</span>
                    <span>Almeno una lettera maiuscola (A-Z)</span>
                </div>
                <div class="requirement" id="req-lowercase">
                    <span class="icon">❌</span>
                    <span>Almeno una lettera minuscola (a-z)</span>
                </div>
                <div class="requirement" id="req-number">
                    <span class="icon">❌</span>
                    <span>Almeno un numero (0-9)</span>
                </div>
                <div class="requirement" id="req-special">
                    <span class="icon">❌</span>
                    <span>Almeno un carattere speciale (!@#$%^&*...)</span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="password_confirm">Conferma Password</label>
            <div class="password-wrapper">
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    required
                    autocomplete="new-password"
                >
                <button type="button" class="toggle-password" id="toggleConfirm">👁️</button>
            </div>
            <div id="match-message" style="margin-top: 8px; font-size: 13px;"></div>
        </div>

        <button type="submit" class="btn" id="submitBtn" disabled>
            🔒 Reimposta Password
        </button>
    </form>

    <div class="back-link">
        <a href="login.php">← Torna al Login</a>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirm');
    const submitBtn = document.getElementById('submitBtn');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    const matchMessage = document.getElementById('match-message');

    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        this.textContent = type === 'password' ? '👁️' : '🙈';
    });

    document.getElementById('toggleConfirm').addEventListener('click', function() {
        const type = confirmInput.type === 'password' ? 'text' : 'password';
        confirmInput.type = type;
        this.textContent = type === 'password' ? '👁️' : '🙈';
    });

    // Password validation
    function validatePassword() {
        const password = passwordInput.value;
        let isValid = true;

        // Length
        const hasLength = password.length >= 8;
        updateRequirement('req-length', hasLength);
        if (!hasLength) isValid = false;

        // Uppercase
        const hasUppercase = /[A-Z]/.test(password);
        updateRequirement('req-uppercase', hasUppercase);
        if (!hasUppercase) isValid = false;

        // Lowercase
        const hasLowercase = /[a-z]/.test(password);
        updateRequirement('req-lowercase', hasLowercase);
        if (!hasLowercase) isValid = false;

        // Number
        const hasNumber = /[0-9]/.test(password);
        updateRequirement('req-number', hasNumber);
        if (!hasNumber) isValid = false;

        // Special character
        const hasSpecial = /[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password);
        updateRequirement('req-special', hasSpecial);
        if (!hasSpecial) isValid = false;

        // Calculate strength
        let strength = 0;
        if (hasLength) strength += 20;
        if (hasUppercase) strength += 20;
        if (hasLowercase) strength += 20;
        if (hasNumber) strength += 20;
        if (hasSpecial) strength += 20;

        // Update strength meter
        updateStrengthMeter(strength);

        return isValid;
    }

    function updateRequirement(id, valid) {
        const element = document.getElementById(id);
        if (valid) {
            element.classList.add('valid');
            element.classList.remove('invalid');
            element.querySelector('.icon').textContent = '✅';
        } else {
            element.classList.add('invalid');
            element.classList.remove('valid');
            element.querySelector('.icon').textContent = '❌';
        }
    }

    function updateStrengthMeter(strength) {
        strengthFill.style.width = strength + '%';

        if (strength >= 80) {
            strengthFill.style.background = '#28a745';
            strengthText.textContent = 'Molto Forte';
            strengthText.style.color = '#28a745';
        } else if (strength >= 60) {
            strengthFill.style.background = '#5cb85c';
            strengthText.textContent = 'Forte';
            strengthText.style.color = '#5cb85c';
        } else if (strength >= 40) {
            strengthFill.style.background = '#ffc107';
            strengthText.textContent = 'Media';
            strengthText.style.color = '#ffc107';
        } else if (strength >= 20) {
            strengthFill.style.background = '#ff8c00';
            strengthText.textContent = 'Debole';
            strengthText.style.color = '#ff8c00';
        } else {
            strengthFill.style.background = '#dc3545';
            strengthText.textContent = 'Molto Debole';
            strengthText.style.color = '#dc3545';
        }
    }

    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;

        if (confirm === '') {
            matchMessage.textContent = '';
            return false;
        }

        if (password === confirm) {
            matchMessage.innerHTML = '✅ <span style="color: #28a745;">Le password corrispondono</span>';
            return true;
        } else {
            matchMessage.innerHTML = '❌ <span style="color: #dc3545;">Le password non corrispondono</span>';
            return false;
        }
    }

    function updateSubmitButton() {
        const passwordValid = validatePassword();
        const passwordsMatch = checkPasswordMatch();

        submitBtn.disabled = !(passwordValid && passwordsMatch && confirmInput.value !== '');
    }

    // Event listeners
    passwordInput.addEventListener('input', updateSubmitButton);
    confirmInput.addEventListener('input', updateSubmitButton);

    // Initial check
    updateSubmitButton();
</script>
</body>
</html>