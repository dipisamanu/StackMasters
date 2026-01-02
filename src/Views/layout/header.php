<?php
// src/Views/layout/header.php

// Assicuriamoci che la sessione sia avviata
if (session_status() === PHP_SESSION_NONE) {
    // Carichiamo la configurazione sessione se non è già stata caricata
    $sessionConfigPath = __DIR__ . '/../../config/session.php';
    if (file_exists($sessionConfigPath)) {
        require_once $sessionConfigPath;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<header>
    <nav class="navbar">
        <a href="index.php" class="logo">
            <i class="fas fa-book-reader"></i> ITIS Rossi Libri
        </a>

        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="#">Catalogo</a></li> <li><a href="#">Chi Siamo</a></li>

            <?php if (class_exists('Session') && Session::isLoggedIn()): ?>
                <li>
                    <a href="
                        <?php
                    // Link dinamico alla dashboard in base al ruolo
                    $role = Session::getMainRole();
                    if ($role === 'Admin') echo 'dashboard/admin/';
                    elseif ($role === 'Bibliotecario') echo 'dashboard/librarian/';
                    else echo 'dashboard/student/';
                    ?>
                    " class="btn-nav">
                        <i class="fas fa-user-circle"></i> Dashboard
                    </a>
                </li>
            <?php else: ?>
                <li><a href="login.php">Accedi</a></li>
                <li><a href="register.php" class="btn-nav">Registrati</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main>