<?php
/**
 * Pagina di Reset Password (Step finale recupero)
 * File: public/reset-password.php
 */

require_once __DIR__ . '/../src/config/session.php';

$token = $_GET['token'] ?? '';
$error = '';

if (isset($_SESSION['reset_error'])) {
    $error = $_SESSION['reset_error'];
    unset($_SESSION['reset_error']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova Password - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <style>
        /* Stile condiviso base */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #9f3232 0%, #b57070 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #bf2121; margin-bottom: 10px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        input {
            width: 100%; padding: 12px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-size: 15px; transition: 0.3s;
        }
        input:focus { border-color: #bf2121; outline: none; }

        .btn {
            width: 100%; padding: 15px; background: #bf2121; color: white;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            font-size: 16px; margin-top: 10px;
        }
        .btn:hover { background: #931b1b; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }

        /* --- STILE REQUISITI PASSWORD --- */
        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        .password-requirements p { margin-bottom: 10px; font-weight: 600; color: #555; }
        .req-list { list-style: none; }
        .req-item {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            transition: all 0.3s ease;
        }
        .req-item i { font-style: normal; font-weight: bold; width: 15px; text-align: center; }

        /* Classi dinamiche JS */
        .req-item.valid { color: #28a745; }
        .req-item.valid i::before { content: '✓'; }
        .req-item.invalid i::before { content: '•'; font-size: 1.2em; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Imposta Nuova Password</h1>
        <p>Scegli una password sicura per il tuo account.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($token)): ?>
        <div class="alert alert-danger">
            Token mancante. <a href="forgot-password.php">Richiedi nuovo link</a>.
        </div>
    <?php else: ?>
        <form action="process-reset-password.php" method="POST" id="resetForm">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label for="password">Nuova Password</label>
                <input type="password" name="password" id="password" required placeholder="Inserisci password">
            </div>

            <div class="password-requirements">
                <p>La nuova password deve contenere:</p>
                <ul class="req-list">
                    <li class="req-item invalid" id="req-len"><i></i> Minimo 8 caratteri</li>
                    <li class="req-item invalid" id="req-upper"><i></i> Una lettera Maiuscola</li>
                    <li class="req-item invalid" id="req-lower"><i></i> Una lettera minuscola</li>
                    <li class="req-item invalid" id="req-num"><i></i> Un numero</li>
                    <li class="req-item invalid" id="req-spec"><i></i> Un carattere speciale (!@#$%^&*)</li>
                </ul>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label for="confirm_password">Conferma Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required placeholder="Ripeti la password">
                <small id="match-msg" style="display:block; margin-top:5px; color:#dc3545; display:none;">Le password non coincidono</small>
            </div>

            <button type="submit" class="btn" id="submitBtn" disabled>Salva Password</button>
        </form>
    <?php endif; ?>
</div>

<script>
    const pwdInput = document.getElementById('password');
    const cfmInput = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    const matchMsg = document.getElementById('match-msg');

    // Requisiti
    const reqs = {
        len: /.{8,}/,
        upper: /[A-Z]/,
        lower: /[a-z]/,
        num: /[0-9]/,
        spec: /[^A-Za-z0-9]/
    };

    function checkPassword() {
        const val = pwdInput.value;
        let allValid = true;

        // Controlla ogni requisito
        for (const [key, regex] of Object.entries(reqs)) {
            const el = document.getElementById('req-' + key);
            if (regex.test(val)) {
                el.classList.add('valid');
                el.classList.remove('invalid');
            } else {
                el.classList.add('invalid');
                el.classList.remove('valid');
                allValid = false;
            }
        }
        return allValid;
    }

    function checkMatch() {
        const match = pwdInput.value === cfmInput.value && pwdInput.value !== '';
        if (!match && cfmInput.value !== '') {
            matchMsg.style.display = 'block';
        } else {
            matchMsg.style.display = 'none';
        }
        return match;
    }

    function updateForm() {
        const isSecure = checkPassword();
        const isMatch = checkMatch();
        submitBtn.disabled = !(isSecure && isMatch);
    }

    pwdInput.addEventListener('input', updateForm);
    cfmInput.addEventListener('input', updateForm);
</script>
</body>
</html>