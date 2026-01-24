<?php
/**
 * Pagina Gioco Memory
 * File: dashboard/student/game.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';


?>


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