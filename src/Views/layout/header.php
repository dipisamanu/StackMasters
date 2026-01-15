<?php
/**
 * Layout Header - Navbar di Sistema Full Width
 * File: src/Views/layout/header.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calcolo path dinamico per assets in base a dove ci troviamo
$scriptPath = $_SERVER['SCRIPT_NAME'];
$basePath = 'assets/';
$rootUrl = './';

if (strpos($scriptPath, '/dashboard/') !== false) {
    $basePath = '../../public/assets/';
    $rootUrl = '../../public/';
} elseif (strpos($scriptPath, '/public/') !== false) {
    $basePath = 'assets/';
    $rootUrl = './';
} else {
    $basePath = 'public/assets/';
    $rootUrl = 'public/';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiblioSystem</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?>css/style.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        .content-wrapper {
            flex: 1;
            width: 100%;
            /* Manteniamo il contenuto su un livello basso */
            position: relative;
            z-index: 1;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
        }

        /* FIX Z-INDEX PER MENU A TENDINA */
        .navbar {
            width: 100%;
            padding-left: 0;
            padding-right: 0;
            border-radius: 0;

            /* Queste righe sono fondamentali per far vedere il menu sopra tutto */
            position: relative;
            z-index: 9999 !important;
            overflow: visible !important;
        }

        /* Assicuriamo che il dropdown sia sopra ogni altra cosa */
        .dropdown-menu {
            z-index: 10000 !important;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid px-4">

        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $rootUrl ?>index.php">
            <i class="fas fa-book-reader text-danger"></i>
            <span>BiblioSystem</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse d-lg-flex justify-content-between w-100" id="mainNav">

            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $rootUrl ?>index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $rootUrl ?>catalog.php">
                        <i class="fas fa-search me-1"></i>Catalogo
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav align-items-center gap-3">
                <?php if (isset($_SESSION['user_id'])): ?>

                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell fa-lg"></i>
                            <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none; font-size: 0.6rem;"></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" id="notification-list" style="min-width: 300px;">
                            <li><h6 class="dropdown-header text-uppercase text-muted small fw-bold">Notifiche</h6></li>
                            <li><hr class="dropdown-divider my-0"></li>
                            <li class="text-center py-3 text-muted small">Caricamento...</li>
                        </ul>
                    </li>

                    <script>
                        const WEB_ROOT = "/StackMasters";
                        const NOTIFICATION_API_PATH = "/StackMasters/public/api/get_notifiche.php";
                    </script>
                    <script src="<?= $rootUrl ?>assets/js/notification.js"></script>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['nome_completo'] ?? 'Utente') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <?php
                            $roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? '';
                            $role = is_array($roleData) ? ($roleData['nome'] ?? '') : $roleData;

                            $dash = match($role) {
                                'Bibliotecario' => 'dashboard/librarian/index.php',
                                'Amministratore', 'Admin' => 'dashboard/admin/index.php',
                                default => 'dashboard/student/index.php'
                            };

                            // LOGICA CORRETTA PER IL LINK DASHBOARD
                            // rootUrl punta a 'public/', quindi dobbiamo uscire da public con '../' per trovare 'dashboard/'
                            $dashboardUrl = $rootUrl . '../' . $dash;
                            ?>

                            <li><h6 class="dropdown-header"><?= htmlspecialchars($role) ?></h6></li>
                            <li><a class="dropdown-item" href="<?= $dashboardUrl ?>"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= $rootUrl ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Esci</a></li>
                        </ul>
                    </li>

                <?php else: ?>
                    <li class="nav-item">
                        <a href="<?= $rootUrl ?>login.php" class="btn btn-outline-light rounded-pill px-4 btn-sm">Accedi</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $rootUrl ?>register.php" class="btn btn-warning rounded-pill px-4 fw-bold text-dark btn-sm">Registrati</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="content-wrapper">