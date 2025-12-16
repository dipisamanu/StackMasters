<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Accedi - Biblioteca ITIS Rossi</title>
    <link rel="stylesheet" href="/StackMasters/public/assets/css/auth.css">
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
</head>

<body>

<div class="container">
    <h2>Accedi al tuo account</h2>

    <?php if (isset($error) && !empty($error)): ?>
        <div style="background-color: #fff5f5; border: 1px solid #dc3545; color: #dc3545; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form id="loginForm" action="/StackMasters/public/index.php/login" method="POST" novalidate>

        <div class="form-row">
            <div class="form-group full">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>"
                       placeholder="esempio@istituto.it"
                       required>
                <div class="error" id="errEmail">Inserisci la tua email</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group full">
                <label for="password">Password *</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-pass" id="togglePassword" tabindex="-1">Mostra</button>
                </div>
                <div class="error" id="errPassword">Inserisci la password</div>
            </div>
        </div>

        <button type="submit" id="btnLogin">Accedi</button>

    </form>

    <div class="auth-footer" style="margin-top: 20px; text-align: center; font-size: 0.9rem; color: #666;">
        <p>Non hai un account? <a href="/StackMasters/public/index.php/registrazione"
                                  style="color: #bf2121; font-weight: 600; text-decoration: none;">Registrati qui</a>
        </p>
    </div>
</div>

<script src="/StackMasters/public/assets/js/login_validation.js"></script>

</body>
</html>