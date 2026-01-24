<?php
/**
 * Pagina Gioco Memory
 * File: dashboard/student/game.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireLogin();

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

require_once '../../src/Views/layout/header.php';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Gioco Memory</title>
</head>
<body>

</body>
</html>

<script>
    const gameBoard = document.getElementById("gameBoard");

    const books = [];



</script>