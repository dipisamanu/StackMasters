<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

$db = Database::getInstance()->getConnection();

$stmt = $db->query("select * from libri order by rand() limit 10;");

$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

$singleBooks = [];
foreach ($books as $book) {
    $singleBooks[] = [
        'id' => $book['id_libro'],
        'title' => $book['titolo'],
        'cover_image' => $book['immagine_copertina']
    ];
}

?>
<table id="gameBoard"></table>

<script>
    const gameBoard = document.getElementById("gameBoard");
    const boardW = 5;
    const boardH = 4;
    const matrix = [];
    let flippedCards = [];
    let matchedPairs = 0;
    let canFlip = true;

    const initialBooks = <?= json_encode($singleBooks) ?>;

    function createBooksMatrix(booksData) {
        const gameBooks = [];
        booksData.forEach((book) => {
            gameBooks.push(book, book);
        });

        for (let i = 0; i < gameBooks.length - 1; i++) {
            const j = i + Math.floor(Math.random() * (gameBooks.length - i));
            [gameBooks[i], gameBooks[j]] = [gameBooks[j], gameBooks[i]];
        }

        for (let i = 0; i < boardH; i++) {
            matrix[i] = [];
            for (let j = 0; j < boardW; j++) {
                matrix[i][j] = gameBooks[i * boardW + j];
            }
        }
    }

    function renderBoard() {
        gameBoard.innerHTML = '';

        for (let i = 0; i < boardH; i++) {
            const row = document.createElement('tr');
            for (let j = 0; j < boardW; j++) {
                const book = matrix[i][j];
                const cell = document.createElement('td');

                const card = document.createElement('div');
                card.classList.add('card');
                card.dataset.id = book.id;
                card.dataset.row = i;
                card.dataset.col = j;

                const cardInner = document.createElement('div');

                const cardFront = document.createElement('div');
                cardFront.classList.add('card-front');

                const cardBack = document.createElement('div');
                cardBack.classList.add('card-back');
                const img = document.createElement('img');
                img.src = book.cover_image;
                img.alt = book.title;
                cardBack.appendChild(img);

                cardInner.appendChild(cardFront);
                cardInner.appendChild(cardBack);
                card.appendChild(cardInner);

                // card.addEventListener('click', handleCardClick);
                cell.appendChild(card);
                row.appendChild(cell);
            }
            gameBoard.appendChild(row);
        }
    }

    function handleCardClick(event) {
        if (!canFlip) return;
        const clickedCard = event.currentTarget;

        if (clickedCard.classList.contains('flipped') || clickedCard.classList.contains('matched')) return;

        clickedCard.classList.add('flipped');
        flippedCards.push(clickedCard);

        if (flippedCards.length === 2) {
            canFlip = false;
            const [card1, card2] = flippedCards;

            if (card1.dataset.id === card2.dataset.id) {
                card1.classList.add('matched');
                card2.classList.add('matched');
                matchedPairs++;
                flippedCards = [];
                canFlip = true;

                if (matchedPairs === (boardW * boardH) / 2) {
                    setTimeout(() => alert('Hai vinto!'), 500);
                }
            } else {
                setTimeout(() => {
                    card1.classList.remove('flipped');
                    card2.classList.remove('flipped');
                    flippedCards = [];
                    canFlip = true;
                }, 1000);
            }
        }
    }

    // function printMatrix(matr) {
    //     for (let i = 0; i < boardH; i++) {
    //         let line = "";
    //         for (let j = 0; j < boardW; j++) {
    //             line += matr[i][j] + " ";
    //         }
    //         console.log(line);
    //     }
    // }

    createBooksMatrix(initialBooks);
    renderBoard();
</script>