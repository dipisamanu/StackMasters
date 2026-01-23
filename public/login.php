<?php
/**
 * Pagina Login "Clean & Centered"
 * File: public/login.php
 */

require_once __DIR__ . '/../src/config/session.php';

// Redirect se già loggato
if (Session::isLoggedIn()) {
    $role = Session::getMainRole();
    if ($role === 'Admin') header('Location: ../dashboard/admin/');
    elseif ($role === 'Bibliotecario') header('Location: ../dashboard/librarian/');
    else header('Location: ../dashboard/student/');
    exit;
}

// Recupero messaggi
$flash = $_SESSION['flash'] ?? null;
$loginError = $_SESSION['login_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['login_error']);

// Personalizzazione Messaggi Flash
$flashTitle = '';
$flashIcon = '';
if ($flash) {
    if ($flash['type'] === 'success') {
        $flashIcon = 'fa-check-circle';
        if (stripos($flash['message'], 'registrazione') !== false || stripos($flash['message'], 'account creato') !== false) {
            $flashTitle = 'Registrazione Completata!';
            $flash['message'] = 'Il tuo account è stato creato con successo. Ti abbiamo inviato un\'email di conferma: clicca sul link al suo interno per attivare il profilo e iniziare a navigare.';
        } else {
            $flashTitle = 'Operazione Riuscita';
        }
    } else {
        $flashIcon = 'fa-exclamation-circle';
        $flashTitle = 'Attenzione';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accedi - BiblioSystem</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f0f2f5;
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 20px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 480px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .brand-icon {
            width: 60px;
            height: 60px;
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.8rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            background-color: #f8fafc;
            font-size: 0.95rem;
        }

        .form-control:focus {
            background-color: white;
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .btn-login {
            background-color: #0d6efd;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.2s;
            margin-top: 1rem;
        }

        .btn-login:hover {
            background-color: #0b5ed7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
        }

        .input-group-text {
            background-color: #f8fafc;
            border: 1px solid #dee2e6;
            border-left: none;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
            cursor: pointer;
            color: #6c757d;
        }

        .input-group-text:hover {
            color: #0d6efd;
        }

        .input-group:focus-within .form-control {
            border-color: #0d6efd;
            border-right: none;
        }

        .input-group:focus-within .input-group-text {
            border-color: #0d6efd;
            background-color: white;
        }

        a.link-primary {
            text-decoration: none;
            font-weight: 600;
        }

        a.link-primary:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-card animate-fade-up">
    <div class="text-center mb-4">
        <div class="brand-icon">
            <i class="fas fa-book-open"></i>
        </div>
        <h3 class="fw-bold text-dark mb-1">Bentornato</h3>
        <p class="text-muted small">Inserisci le tue credenziali per accedere</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> border-0 shadow-sm rounded-3 mb-4"
             role="alert">
            <div class="d-flex">
                <div class="me-3">
                    <i class="fas <?= $flashIcon ?> fs-4 mt-1"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1"><?= htmlspecialchars($flashTitle) ?></h6>
                    <p class="mb-0 small opacity-75"><?= htmlspecialchars($flash['message']) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($loginError): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
            <div>
                <strong class="d-block small">Errore di Accesso</strong>
                <div class="small"><?= htmlspecialchars($loginError) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <form action="process-login.php" method="POST">

        <div class="mb-3">
            <label for="email" class="form-label small fw-bold text-muted text-uppercase">Email</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="nome@esempio.it" required
                   autofocus>
        </div>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="password" class="form-label small fw-bold text-muted text-uppercase m-0">Password</label>
                <a href="forgot-password.php" class="small link-primary" tabindex="-1">Password dimenticata?</a>
            </div>
            <div class="input-group">
                <input type="password" class="form-control border-end-0" id="password" name="password"
                       placeholder="Password" required>
                <span class="input-group-text" onclick="togglePassword()">
                        <i class="far fa-eye" id="toggleIcon"></i>
                    </span>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-login text-white shadow-sm">
                ACCEDI <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>

        <div class="text-center mt-4 pt-3 border-top">
            <p class="text-muted small mb-0">
                Non hai un account? <a href="register.php" class="link-primary ms-1">Registrati gratis</a>
            </p>
        </div>

        <div class="text-center mt-3">
            <a href="index.php" class="text-secondary small text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> Torna alla Home
            </a>
        </div>
    </form>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>