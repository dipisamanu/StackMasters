<?php
/**
 * Layout Header
 * File: src/Views/layout/header.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = 'assets/';
$rootUrl = './';

// Recupero ruolo principale
$roleData = $_SESSION['ruolo_principale'] ?? $_SESSION['role'] ?? $_SESSION['main_role'] ?? '';
$currentRole = is_array($roleData) ? ($roleData['nome'] ?? '') : $roleData;

// Gestione percorsi
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

// Iniziali Avatar
$userName = $_SESSION['nome_completo'] ?? 'Utente';
$initials = 'U';
if ($userName !== 'Utente') {
    $parts = explode(' ', $userName);
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca ITIS Rossi</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <link rel="stylesheet" href="<?= $basePath ?>css/style.css">
    <link rel="icon" type="image/png" href="../../../public/assets/img/itisrossi.png">

    <style>
        :root {
            --primary-color: #0d6efd;
            --nav-height: 72px;
        }

        html, body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            width: 100%;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            width: 100vw;
            max-width: 100%;
            height: var(--nav-height);
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .navbar .container-fluid {
            padding-left: 2rem !important;
            padding-right: 2rem !important;
            max-width: 100%;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.35rem;
            color: #1e293b !important;
            letter-spacing: -0.5px;
            margin-right: 3rem;
        }

        .nav-link {
            font-weight: 600;
            color: #64748b !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color) !important;
            background-color: rgba(13, 110, 253, 0.05);
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 5px rgba(13, 110, 253, 0.3);
        }

        .notification-icon-wrapper {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .notification-icon-wrapper:hover {
            background-color: #f1f5f9;
            color: var(--primary-color);
            transform: translateY(-1px);
        }

        .badge-pulse {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 8px;
            height: 8px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            padding: 10px;
            margin-top: 10px !important;
        }

        .dropdown-item {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            color: #475569;
        }

        .dropdown-item:hover {
            background-color: #f8fafc;
            color: var(--primary-color);
        }

        .content-wrapper {
            flex: 1;
        }

        @media (max-width: 991px) {
            .navbar {
                height: auto;
                padding: 0.5rem 0;
            }

            .navbar .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
    <div class="container-fluid">

        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $rootUrl ?>index.php">
            <i class="fas fa-book-reader text-primary"></i>
            <span>BiblioSystem</span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $rootUrl ?>index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $rootUrl ?>catalog.php">
                        <i class="fas fa-search me-1 small"></i>Catalogo
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <div class="dropdown">
                        <a class="nav-link hidden-arrow notification-icon-wrapper position-relative" href="#"
                           id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span id="notification-badge" class="badge-pulse" style="display: none;"></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown"
                            id="notification-list" style="min-width: 320px; max-height: 400px; overflow-y: auto;">
                            <li><h6 class="dropdown-header text-uppercase small fw-bold text-muted ls-1">Notifiche</h6>
                            </li>
                            <li>
                                <hr class="dropdown-divider my-1">
                            </li>
                            <li id="notification-list-body" class="text-center py-4 text-muted small">
                                <i class="fas fa-spinner fa-spin me-2"></i>Caricamento...
                            </li>
                        </ul>
                    </div>

                    <script>
                        const WEB_ROOT = "/StackMasters";
                        const NOTIFICATION_API_PATH = "/StackMasters/public/api/get_notifiche.php";
                    </script>
                    <script src="<?= $rootUrl ?>assets/js/notification.js"></script>

                    <div class="dropdown">
                        <a class="nav-link d-flex align-items-center gap-2 p-0" href="#" role="button"
                           data-bs-toggle="dropdown">
                            <div class="text-end d-none d-lg-block" style="line-height: 1.2;">
                                <div class="fw-bold text-dark small lh-1"><?= htmlspecialchars($_SESSION['nome_completo']) ?></div>
                                <div class="text-muted small"
                                     style="font-size: 0.7rem;"><?= htmlspecialchars($currentRole) ?></div>
                            </div>
                            <div class="user-avatar">
                                <?= $initials ?>
                            </div>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                            <?php
                            $dash = match ($currentRole) {
                                'Bibliotecario' => 'dashboard/librarian/index.php',
                                'Admin' => 'dashboard/admin/index.php',
                                default => 'dashboard/student/index.php'
                            };
                            $profileLink = match ($currentRole) {
                                'Admin' => 'dashboard/admin/profile.php',
                                default => 'dashboard/student/profile.php'
                            };

                            if ($rootUrl == './') {
                                $finalDash = '../' . $dash;
                                $finalProfile = '../' . $profileLink;
                            } else {
                                $finalDash = '../../public/' . $dash;
                                $finalProfile = '../../public/' . $profileLink;
                            }
                            if (str_contains($scriptPath, '/dashboard/')) {
                                $finalDash = '../' . basename(dirname($dash)) . '/index.php';
                                $finalProfile = 'profile.php';
                            }
                            ?>

                            <li class="d-lg-none px-3 py-2 border-bottom mb-2 bg-light">
                                <div class="fw-bold"><?= htmlspecialchars($_SESSION['nome_completo']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($currentRole) ?></div>
                            </li>

                            <li><a class="dropdown-item" href="<?= $finalDash ?>"><i
                                            class="fas fa-tachometer-alt me-2 text-primary opacity-75"></i>Dashboard</a>
                            </li>
                            <li><a class="dropdown-item" href="<?= $finalProfile ?>"><i
                                            class="fas fa-user-cog me-2 text-secondary opacity-75"></i>Profilo</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="<?= $rootUrl ?>logout.php"><i
                                            class="fas fa-sign-out-alt me-2 opacity-75"></i>Esci</a></li>
                        </ul>
                    </div>

                <?php else: ?>
                    <a href="<?= $rootUrl ?>login.php"
                       class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-sm me-2">Accedi</a>
                    <a href="<?= $rootUrl ?>register.php"
                       class="btn btn-primary rounded-pill px-4 fw-bold btn-sm shadow-sm">Registrati</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="content-wrapper">