<?php
/**
 * Layout Header - Navbar di Sistema
 * File: src/Views/layout/header.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = 'assets/';
$rootUrl = './';

// Recupero ruolo pricipale
$roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? $_SESSION['main_role'] ?? '';
$currentRole = is_array($roleData) ? ($roleData['nome'] ?? '') : $roleData;
$isAdmin = ($currentRole === 'Admin');

if (str_contains($scriptPath, '/dashboard/')) {
    $basePath = '../../public/assets/';
    $rootUrl = '../../public/';
} elseif (str_contains($scriptPath, '/public/')) {
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
        }

        .content-wrapper {
            flex: 1;
        }

        .navbar-brand {
            font-weight: 700;
        }

        /* FIX Z-INDEX: position relative è fondamentale per far funzionare z-index */
        .navbar {
            position: relative;
            z-index: 9999;
        }
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
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= $rootUrl ?>index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $rootUrl ?>catalog.php"><i
                                class="fas fa-search me-1"></i>Catalogo</a></li>

                <?php if ($isAdmin && !str_contains($scriptPath, '/dashboard/admin/')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $rootUrl ?>../dashboard/admin/index.php"><i
                                    class="fas fa-crown me-1"></i>Area Admin</a></li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>

                    <li class="nav-item dropdown me-3">
                        <a class="nav-link hidden-arrow position-relative" href="#" id="notificationDropdown"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell fa-lg text-secondary"></i>
                            <span id="notification-badge"
                                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                  style="display: none; font-size: 0.6rem;">
                                0
                            </span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0"
                            aria-labelledby="notificationDropdown" id="notification-list"
                            style="min-width: 320px; max-height: 400px; overflow-y: auto;">
                            <li><h6 class="dropdown-header text-uppercase text-muted small fw-bold">Centro
                                    Notifiche</h6></li>
                            <li>
                                <hr class="dropdown-divider my-0">
                            </li>
                            <li class="text-center py-3 text-muted small">
                                <i class="fas fa-spinner fa-spin me-2"></i>Caricamento...
                            </li>
                        </ul>
                    </li>

                    <script>
                        // 1. Definiamo la radice del PROGETTO (dove ci sono le cartelle public, dashboard, src...)
                        // NOTA: Togliamo "/public" da qui perché le dashboard sono fuori da public
                        const WEB_ROOT = "/StackMasters";

                        // 2. Definiamo il percorso dell'API (che invece È dentro public)
                        const NOTIFICATION_API_PATH = "/StackMasters/public/api/get_notifiche.php";
                    </script>

                    <script src="<?= $rootUrl ?>assets/js/notification.js"></script>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['nome_completo'] ?? 'Utente') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <?php
                            $dash = match ($currentRole) {
                                'Bibliotecario' => 'dashboard/librarian/index.php',
                                'Admin' => 'dashboard/admin/index.php',
                                default => 'dashboard/student/index.php'
                            };
                            // Fix path relativo
                            // $rootUrl ci porta alla cartella public, con '../' torniamo alla root del progetto per entrare in dashboard
                            $finalDash = $rootUrl . '../' . $dash;

                            // Correzione specifica se siamo già nella root di public (es. catalog.php)
                            if ($rootUrl == './') {
                                $finalDash = '../' . $dash;
                            }
                            ?>
                            <li>
                                <h6 class="dropdown-header text-uppercase small"><?= htmlspecialchars($currentRole) ?></h6>
                            </li>
                            <li><a class="dropdown-item" href="<?= $finalDash ?>"><i
                                            class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="<?= $rootUrl ?>logout.php"><i
                                            class="fas fa-sign-out-alt me-2"></i>Esci</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-2"><a href="<?= $rootUrl ?>login.php"
                                                 class="btn btn-outline-light btn-sm rounded-pill">Accedi</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="content-wrapper">