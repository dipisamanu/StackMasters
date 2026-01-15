<?php
/**
 * Pagina Login
 * File: public/login.php
 *
 * EPIC 2.5 - Feature: Flow recupero password
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../src/config/database.php';

// Se già loggato, reindirizza
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: ../dashboard/student/index.php');
    exit;
}

// Gestione messaggi
$login_error = $_SESSION['login_error'] ?? '';
$login_success = $_SESSION['login_success'] ?? '';
$login_warning = $_SESSION['login_warning'] ?? '';

if (isset($_SESSION['login_error'])) unset($_SESSION['login_error']);
if (isset($_SESSION['login_success'])) unset($_SESSION['login_success']);
if (isset($_SESSION['login_warning'])) unset($_SESSION['login_warning']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Biblioteca ITIS Rossi</title>
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

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #bf2121;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
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

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 45px;
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

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-forgot label {
            display: flex;
            align-items: center;
            font-weight: normal;
            margin: 0;
        }

        .remember-forgot input[type="checkbox"] {
            width: auto;
            margin-right: 5px;
        }

        .remember-forgot a {
            color: #bf2121;
            text-decoration: none;
            font-weight: 600;
        }

        .remember-forgot a:hover {
            text-decoration: underline;
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
        }

        .btn:hover {
            background: #931b1b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(191, 33, 33, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            color: #999;
            font-size: 14px;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #ddd;
        }

        .divider::before { left: 0; }
        .divider::after { right: 0; }

        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .register-link a {
            color: #bf2121;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <h1>📚 Biblioteca ITIS Rossi</h1>
        <p>Sistema Gestionale</p>
    </div>

    <?php if (!empty($login_success)): ?>
        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($login_success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($login_error)): ?>
        <div class="alert alert-danger">
            ⚠️ <?= htmlspecialchars($login_error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($login_warning)): ?>
        <div class="alert alert-warning">
            ⚠️ <?= htmlspecialchars($login_warning) ?>
        </div>
    <?php endif; ?>

    <form action="process-login.php" method="POST">

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <button type="button" class="toggle-password" id="togglePassword">👁️</button>
            </div>
        </div>

        <div class="remember-forgot">
            <!-- TODO: Implementare persistenza della sessione utente -->
<!--            <label>-->
<!--                <input type="checkbox" name="remember" value="1">-->
<!--                Ricordami-->
<!--            </label>-->
            <a href="forgot-password.php">Password dimenticata?</a>
        </div>

        <button type="submit" class="btn">Accedi</button>
    </form>

    <div class="divider">oppure</div>

    <div class="register-link">
        Non hai un account? <a href="register.php">Registrati ora</a>
    </div>
</div>

<script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        this.textContent = type === 'password' ? '👁️' : '🙈';
    });
</script>
</body>
</html>