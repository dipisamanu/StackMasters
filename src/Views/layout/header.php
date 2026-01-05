<?php
/**
 * Layout Header - Navbar e Risorse Comuni
 * File: src/Views/layout/header.php
 */

// Avvia sessione se non presente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Calcolo Percorso Base (Assets)
// Serve per caricare CSS/IMG/JS correttamente da qualsiasi sottocartella
$scriptPath = $_SERVER['SCRIPT_NAME'];

// Default: siamo nella root o cartelle di primo livello
$basePath = 'assets/';
$rootUrl = './';

if (strpos($scriptPath, '/dashboard/') !== false) {
    // Siamo in una sottocartella dashboard (es. dashboard/librarian/)
    // Dobbiamo risalire di due livelli per tornare alla root di public
    $basePath = '../../public/assets/';
    $rootUrl = '../../public/';
} elseif (strpos($scriptPath, '/public/') !== false) {
    // Siamo nella cartella public
    $basePath = 'assets/';
    $rootUrl = './';
} else {
    // Fallback per sicurezza (es. root del progetto se configurata diversamente)
    $basePath = 'public/assets/';
    $rootUrl = 'public/';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiblioSystem - Gestione Biblioteca</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $basePath ?>css/style.css">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; min-height: 100vh; display: flex; flex-direction: column; }

        /* Navbar non sticky per evitare problemi di scroll */
        .navbar { z-index: 1030; }

        .content-wrapper { flex: 1; } /* Footer sticky in fondo */
        .navbar-brand { font-weight: 700; letter-spacing: -0.5px; }
        .nav-link { font-weight: 500; }
        .card { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="<?= $rootUrl ?>index.php">
            <i class="fas fa-book-reader me-2 text-danger"></i>BiblioSystem
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $rootUrl ?>index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $rootUrl ?>catalog.php"><i class="fas fa-search me-1"></i>Catalogo</a>
                </li>
            </ul>

            <ul class="navbar-nav align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['nome'] ?? 'Utente') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <?php
                            // Link Dashboard Dinamico
                            $roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? '';
                            $roleName = is_array($roleData) ? ($roleData['nome'] ?? '') : $roleData;

                            $dashboardLink = 'dashboard/student/index.php'; // Default
                            if($roleName === 'Bibliotecario') $dashboardLink = 'dashboard/librarian/index.php';
                            if($roleName === 'Admin') $dashboardLink = 'dashboard/admin/index.php';

                            // Fix link relativo se siamo già in dashboard
                            $finalDashLink = (strpos($scriptPath, '/dashboard/') !== false)
                                    ? $rootUrl . '../' . $dashboardLink
                                    : $rootUrl . '../' . $dashboardLink;

                            // Se siamo in root locale pulita
                            if($rootUrl == './') $finalDashLink = '../' . $dashboardLink;
                            ?>

                            <li><span class="dropdown-item-text small text-muted text-uppercase fw-bold"><?= htmlspecialchars($roleName) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= $finalDashLink ?>"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard</a></li>
                            <li><a class="dropdown-item text-danger" href="<?= $rootUrl ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Esci</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-2">
                        <a href="<?= $rootUrl ?>login.php" class="btn btn-outline-light btn-sm px-4 rounded-pill">Accedi</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="<?= $rootUrl ?>register.php" class="btn btn-danger btn-sm px-4 rounded-pill">Registrati</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="content-wrapper">