<?php
/**
 * Logout
 * File: public/logout.php
 */

require_once '../src/config/session.php';
Session::logout();
header('Location: login.php');
exit;