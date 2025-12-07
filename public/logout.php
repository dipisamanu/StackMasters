<?php

require_once '../src/config/session.php';

// Esegui logout
Session::logout();

// Reindirizza a login con messaggio
Session::setFlash('success', 'Logout effettuato con successo');
header('Location: login.php');
exit;