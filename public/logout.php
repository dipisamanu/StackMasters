<?php
/**
 * Logout
 * File: public/logout.php
 */

require_once '../src/config/session.php';

// Usa il metodo centralizzato della classe
Session::logout();

// Reindirizza al login
header('Location: login.php');
exit;