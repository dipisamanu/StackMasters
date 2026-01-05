<?php
/**
 * Controller Stampa Etichette
 * File: dashboard/librarian/print-labels.php
 */
require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';
require_once '../../src/Models/InventoryModel.php';
require_once '../../src/Helpers/LabelPrinter.php';

Session::requireRole('Bibliotecario');

$idLibro = $_GET['id_libro'] ?? 0;
if (!$idLibro) die("ID mancante");

$bookModel = new BookModel();
$book = $bookModel->getById($idLibro);
if (!$book) die("Libro non trovato");

$invModel = new InventoryModel();
$copies = $invModel->getCopiesByBookId($idLibro);

if (empty($copies)) die("Nessuna copia da stampare.");

$printer = new LabelPrinter();
$printer->generatePdf($copies, $book['titolo']);