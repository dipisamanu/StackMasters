<?php
/**
 * Layout Header - Navbar di Sistema
 * File: src/Views/layout/header.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calcolo path dinamico per assets
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
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; min-height: 100vh; display: flex; flex-direction: column; }
        .content-wrapper { flex: 1; }
        .navbar-brand { font-weight: 700; }

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
                <li class="nav-item"><a class="nav-link" href="<?= $rootUrl ?>catalog.php"><i class="fas fa-search me-1"></i>Catalogo</a></li>
            </ul>

            <ul class="navbar-nav align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['nome'] ?? 'Utente') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <?php
                            $roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? '';
                            $role = is_array($roleData) ? ($roleData['nome'] ?? '') : $roleData;

                            $dash = match($role) {
                                'Bibliotecario' => 'dashboard/librarian/index.php',
                                'Admin' => 'dashboard/admin/index.php',
                                default => 'dashboard/student/index.php'
                            };
                            // Fix path relativo
                            $finalDash = (strpos($scriptPath, '/dashboard/') !== false) ? $rootUrl . '../' . $dash : $rootUrl . '../' . $dash;
                            if($rootUrl == './') $finalDash = '../' . $dash;
                            ?>
                            <li><h6 class="dropdown-header text-uppercase small"><?= htmlspecialchars($role) ?></h6></li>
                            <li><a class="dropdown-item" href="<?= $finalDash ?>"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= $rootUrl ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Esci</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-2"><a href="<?= $rootUrl ?>login.php" class="btn btn-outline-light btn-sm rounded-pill">Accedi</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="content-wrapper">