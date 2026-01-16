<?php
/**
 * Processa la richiesta di prenotazione
 * File: dashboard/student/process-reservation.php
 */

require_once __DIR__ . '/../../src/config/session.php';
require_once __DIR__ . '/../../src/Models/ReservationModel.php';
require_once __DIR__ . '/../../src/Models/BookModel.php';

// 1. Controllo Autenticazione
if (!Session::isLoggedIn()) {
    header('Location: ../../public/login.php');
    exit;
}

// 2. Controllo Metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/catalog.php');
    exit;
}

// 3. Validazione Input
$bookId = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
$userId = Session::getUserId();

if ($bookId <= 0) {
    $_SESSION['flash_error'] = "Libro non valido.";
    header("Location: ../../public/catalog.php");
    exit;
}

try {
    $reservationModel = new ReservationModel();
    $bookModel = new BookModel();

    // 4. Verifica esistenza libro
    $book = $bookModel->getById($bookId);
    if (!$book) {
        $_SESSION['flash_error'] = "Il libro richiesto non esiste.";
        header("Location: ../../public/catalog.php");
        exit;
    }

    // 5. Verifica se è davvero necessario prenotare (Business Logic)
    // Se ci sono copie disponibili, l'utente dovrebbe andare in biblioteca, non prenotare.
    if ($book['copie_disponibili'] > 0) {
        $_SESSION['flash_error'] = "Ci sono copie disponibili! Puoi passare direttamente in biblioteca a ritirarlo.";
        header("Location: ../../public/book.php?id=$bookId");
        exit;
    }

    // 6. Verifica duplicati
    if ($reservationModel->hasActiveReservation($userId, $bookId)) {
        $_SESSION['flash_error'] = "Sei già in coda per questo libro.";
        header("Location: ../../public/book.php?id=$bookId");
        exit;
    }

    // 7. Esecuzione Prenotazione
    if ($reservationModel->createReservation($userId, $bookId)) {
        $coda = $reservationModel->getQueuePosition($bookId);
        $_SESSION['flash_success'] = "Prenotazione confermata! Sei in coda (Posizione attuale: $coda). Ti avviseremo via email.";
    } else {
        $_SESSION['flash_error'] = "Errore durante la prenotazione. Riprova più tardi.";
    }

} catch (Exception $e) {
    error_log("Errore Process Reservation: " . $e->getMessage());
    $_SESSION['flash_error'] = "Si è verificato un errore di sistema.";
}

// 8. Redirect finale
header("Location: ../../public/book.php?id=$bookId");
exit;