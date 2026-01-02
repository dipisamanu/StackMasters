<?php
// src/Views/layout/header.php

// Recupera la configurazione sessione se non c'è (per avere BASE_URL)
if (session_status() === PHP_SESSION_NONE) {
    // Tenta di caricare session.php risalendo le cartelle se necessario
    $paths = [
            __DIR__ . '/../../config/session.php',
            $_SERVER['DOCUMENT_ROOT'] . '/StackMasters/src/config/session.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// Fallback di sicurezza per BASE_URL se session.php non viene caricato
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    define('BASE_URL', $protocol . '://' . $host . '/StackMasters/public');
}

// Recupera dati utente per la navbar
$userName = $_SESSION['user_name'] ?? 'Utente';
$userRole = $_SESSION['roles'][0]['nome'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca ITIS Rossi</title>

    <link rel="icon" href="<?= BASE_URL ?>/assets/img/itisrossi.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <style>
        /* Override colori per tema ITIS Rossi */
        :root {
            --primary-red: #bf2121;
            --primary-dark: #931b1b;
        }

        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar personalizzata */
        .navbar-custom {
            background: linear-gradient(135deg, #9f3232 0%, #b57070 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand { font-weight: bold; color: white !important; }
        .nav-link { color: rgba(255,255,255,0.9) !important; }
        .nav-link:hover { color: white !important; }

        /* Footer sticky */
        main { flex: 1; }
    </style>
</head>
<body>

<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="<?= BASE_URL ?>/assets/img/itisrossi.png" width="30" height="30" class="d-inline-block align-top" alt="">
                Biblioteca Rossi
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($userRole === 'Bibliotecario'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard/librarian/index.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard/librarian/books.php">Libri</a></li>
                    <?php elseif ($userRole === 'Admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard/admin/index.php">Admin Panel</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard/student/index.php">I miei Prestiti</a></li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center">
                    <span class="text-white me-3"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($userName) ?></span>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">Esci</a>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>

<main>