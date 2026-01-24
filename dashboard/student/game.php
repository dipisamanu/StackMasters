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
require_once '../../scripts/selezione_libri_gioco.php'
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
    const boardW = 5;
    const boardH = 4;

    // const books = [<?php //= json_encode($books) ?>//];
    const matrix = [];

    createBooksMatrix();
    printMatrix();


    function createBooksMatrix() {
        const singleBooks = [...Array(10).keys()];
        const books = [...singleBooks, ...singleBooks];

        for (let i = 0; i < books.length - 1; i++) {
            const j = i + Math.floor(Math.random() * (books.length - i));
            [books[i], books[j]] = [books[j], books[i]];
        }

        for (let i = 0; i < boardH; i++) {
            matrix[i] = [];

            for (let j = 0; j < boardW; j++) {
                matrix[i][j] = books[i * boardW + j];
            }
        }
    }

    function printMatrix() {
        for (let i = 0; i < boardH; i++) {
            let line = "";
            for (let j = 0; j < boardW; j++) {
                line += matrix[i][j] + " ";
            }
            console.log(line);
        }
    }

</script>