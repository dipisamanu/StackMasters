<?php
/**
 * Pagina Login
 * File: public/login.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include la gestione della sessione per prima cosa
require_once '../src/config/session.php';
require_once '../src/config/database.php';

// Se l'utente è già loggato, reindirizza alla dashboard
if (Session::isLoggedIn()) {
    Session::redirectToDashboard();
    exit;
}

// --- GESTIONE POPUP ---
$popupType = '';
$popupTitle = '';
$popupMessage = '';

// 1. Controlla i messaggi Flash (es. dalla Registrazione)
if (Session::hasFlash()) {
    $flash = Session::getFlash();
    if ($flash['type'] === 'success') {
        $popupType = 'success';
        $popupTitle = 'Verifica Email';
        $popupMessage = $flash['message'];
    } elseif ($flash['type'] === 'error') {
        $popupType = 'error';
        $popupTitle = 'Errore';
        $popupMessage = $flash['message'];
    }
}

// 2. Controlla errori di Login (da process-login.php)
if (isset($_SESSION['login_error'])) {
    $errorMsg = $_SESSION['login_error'];
    unset($_SESSION['login_error']);

    // Se il messaggio contiene "bloccato", mostra popup specifico
    if (stripos($errorMsg, 'bloccato') !== false) {
        $popupType = 'blocked';
        $popupTitle = 'Account Bloccato';
        $popupMessage = $errorMsg;
    } else {
        $popupType = 'error';
        $popupTitle = 'Errore di Accesso';
        $popupMessage = $errorMsg;
    }
}

// 3. Controlli Legacy/Fallback
if (isset($_SESSION['login_success'])) {
    $popupType = 'success';
    $popupTitle = 'Successo';
    $popupMessage = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
if (isset($_SESSION['login_warning'])) {
    $popupType = 'warning';
    $popupTitle = 'Attenzione';
    $popupMessage = $_SESSION['login_warning'];
    unset($_SESSION['login_warning']);
}
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

        /* --- STILI DEL MODAL (POPUP) --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 30px;
            border: 1px solid #888;
            width: 90%;
            max-width: 400px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: slideInModal 0.3s;
            position: relative;
        }

        @keyframes fadeIn {
            from {opacity: 0}
            to {opacity: 1}
        }

        @keyframes slideInModal {
            from {transform: translateY(-50px); opacity: 0;}
            to {transform: translateY(0); opacity: 1;}
        }

        .close-modal {
            color: #aaa;
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close-modal:hover {
            color: #333;
        }

        .modal-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .modal-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .modal-btn {
            padding: 10px 30px;
            border-radius: 25px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
        }

        /* Stili specifici per tipo */
        .type-success .modal-icon { color: #28a745; }
        .type-success .modal-btn { background: #28a745; color: white; }
        .type-success .modal-btn:hover { background: #218838; }

        .type-error .modal-icon { color: #dc3545; }
        .type-error .modal-btn { background: #dc3545; color: white; }
        .type-error .modal-btn:hover { background: #c82333; }

        .type-blocked .modal-icon { color: #ffc107; }
        .type-blocked .modal-btn { background: #ffc107; color: #333; }
        .type-blocked .modal-btn:hover { background: #e0a800; }

        .type-warning .modal-icon { color: #ffc107; }
        .type-warning .modal-btn { background: #ffc107; color: #333; }

    </style>
</head>
<body>

<!-- MODAL -->
<div id="infoModal" class="modal">
    <div class="modal-content" id="modalContent">
        <span class="close-modal">&times;</span>
        <div class="modal-icon" id="modalIcon"></div>
        <div class="modal-title" id="modalTitle"></div>
        <div class="modal-message" id="modalMessage"></div>
        <button class="modal-btn" id="modalBtn">OK</button>
    </div>
</div>

<div class="login-container">
    <div class="logo">
        <h1>Biblioteca ITIS Rossi</h1>
        <p>Sistema Gestionale</p>
    </div>

    <form action="process-login.php" method="POST" id="loginForm">

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
    // Logica del Modal
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById("infoModal");
        const closeBtn = document.querySelector(".close-modal");
        const okBtn = document.getElementById("modalBtn");
        const modalContent = document.getElementById("modalContent");
        const modalIcon = document.getElementById("modalIcon");
        const modalTitle = document.getElementById("modalTitle");
        const modalMessage = document.getElementById("modalMessage");

        // Variabili PHP passate a JS
        const pType = <?= json_encode($popupType) ?>;
        const pTitle = <?= json_encode($popupTitle) ?>;
        const pMessage = <?= json_encode($popupMessage) ?>;

        if (pType) {
            modalTitle.textContent = pTitle;
            modalMessage.textContent = pMessage;

            // Imposta lo stile in base al tipo
            modalContent.className = 'modal-content type-' + pType;

            if (pType === 'success') {
                if (pTitle.toLowerCase().includes('verifica')) {
                     modalIcon.innerHTML = '📧';
                } else {
                     modalIcon.innerHTML = '✅';
                }
            } else if (pType === 'error') {
                modalIcon.innerHTML = '❌';
            } else if (pType === 'blocked') {
                modalIcon.innerHTML = '🔒';
            } else if (pType === 'warning') {
                modalIcon.innerHTML = '⚠️';
            }

            modal.style.display = "block";
        }

        function closeModal() {
            modal.style.display = "none";
        }

        closeBtn.onclick = closeModal;
        okBtn.onclick = closeModal;

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    });
</script>
<!-- Include script di validazione se esiste -->
<script src="assets/js/login_validation.js"></script>
</body>
</html>