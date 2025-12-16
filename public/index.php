<?php
/**
 * Home Page - StackMasters
 * File: public/index.php
 *
 * Reindirizza automaticamente al login se non loggato,
 * altrimenti reindirizza alla dashboard appropriata
 */

require_once '../src/config/session.php';

// Se loggato, vai alla dashboard
if (Session::isLoggedIn()) {
    Session::redirectToDashboard();
}

// Se non loggato, vai al login
header('Location: login.php');
exit;

