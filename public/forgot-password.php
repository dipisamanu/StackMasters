<?php
/**
 * Pagina Recupero Password
 * File: public/forgot-password.php
 *
 * EPIC 2.5 - Feature: Flow recupero password (Step 1)
 * Form per richiedere il link di reset password via email
 */

error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporaneo per debug

session_start();

// Gestione messaggi (leggi e cancella subito per evitare loop)
$error = '';
$success = '';

if (isset($_SESSION['forgot_error'])) {
    $error = $_SESSION['forgot_error'];
    unset($_SESSION['forgot_error']);
}

if (isset($_SESSION['forgot_success'])) {
    $success = $_SESSION['forgot_success'];
    unset($_SESSION['forgot_success']);
}

// Se già loggato, reindirizza (solo una volta)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ../dashboard/student/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recupero Password - Biblioteca ITIS Rossi</title>
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

        .forgot-container {
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
            line-height: 1.6;
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

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #004085;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
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

        input[type="email"] {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #bf2121;
            box-shadow: 0 0 0 3px rgba(191, 33, 33, 0.1);
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
            margin-bottom: 15px;
        }

        .btn:hover {
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
            color: #856404;
        }

        .steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
        }

        .steps h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .steps ol {
            padding-left: 20px;
            margin: 0;
        }

        .steps li {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        /* Loading spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn.loading .btn-text {
            display: none;
        }

        .btn.loading .spinner {
            display: block;
        }
    </style>
</head>
<body>
<div class="forgot-container">
    <div class="header">
        <div class="icon">🔐</div>
        <h1>Password Dimenticata?</h1>
        <p>Non preoccuparti! Inserisci la tua email e ti invieremo le istruzioni per reimpostare la password.</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
        <div class="steps">
            <h3>📬 Cosa fare ora:</h3>
            <ol>
                <li>Controlla la tua casella email (anche nello spam)</li>
                <li>Apri l'email di recupero password</li>
                <li>Clicca sul link di reset (valido per 24 ore)</li>
                <li>Imposta la tua nuova password</li>
            </ol>
        </div>
    <?php else: ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>ℹ️ Come funziona:</strong>
            Riceverai un'email con un link sicuro per reimpostare la password. Il link sarà valido per 24 ore.
        </div>

        <form action="process-forgot-password.php" method="POST" id="forgotForm">
            <div class="form-group">
                <label for="email">Indirizzo Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="tuo.email@esempio.it"
                    required
                    autofocus
                    pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                >
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <span class="btn-text">📧 Invia Link di Reset</span>
                <div class="spinner"></div>
            </button>
        </form>

        <div class="security-notice">
            🔒 <strong>Sicurezza:</strong> Se l'email esiste nel nostro sistema, riceverai il link di reset. Per motivi di sicurezza, non confermiamo l'esistenza degli account.
        </div>

    <?php endif; ?>

    <div class="back-link">
        <a href="login.php">← Torna al Login</a>
    </div>
</div>

<script>
    // Form submission con loading
    document.getElementById('forgotForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.classList.add('loading');
    });

    // Validazione email in tempo reale
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            if (this.validity.typeMismatch) {
                this.setCustomValidity('Inserisci un indirizzo email valido');
            } else {
                this.setCustomValidity('');
            }
        });
    }
</script>
</body>
</html>