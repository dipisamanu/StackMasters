<?php
/**
 * Dettaglio Libro Pubblico (Epic 6.1 - Logica Prenotazione)
 * File: public/book.php
 */

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/Models/BookModel.php';

// Carichiamo il modello prenotazioni SOLO se il file esiste (evita errore 500)
$hasReservationModel = file_exists(__DIR__ . '/../src/Models/ReservationModel.php');
if ($hasReservationModel) {
    require_once __DIR__ . '/../src/Models/ReservationModel.php';
}

// 1. Validazione Input
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: catalog.php');
    exit;
}

// 2. Recupero Dati
$bookModel = new BookModel();
$book = $bookModel->getById($id);

if (!$book) {
    require_once __DIR__ . '/../src/Views/layout/header.php';
    echo '<div class="container py-5 text-center">
            <h2 class="display-5 fw-bold text-dark">Libro non trovato</h2>
            <a href="catalog.php" class="btn btn-outline-primary mt-3">Torna al Catalogo</a>
          </div>';
    require_once __DIR__ . '/../src/Views/layout/footer.php';
    exit;
}

// Gestione Immagine
$img = 'assets/img/placeholder.png';
if (!empty($book['immagine_copertina'])) {
    $img = (strpos($book['immagine_copertina'], 'http') === 0) ? $book['immagine_copertina'] : $book['immagine_copertina'];
}

// -----------------------------------------------------------
// LOGICA EPIC 6.1: Regole di Visualizzazione Tasto Prenota
// -----------------------------------------------------------
$statusClass = 'secondary';
$statusText = '';
$statusIcon = '';
$canReserve = false;
$userMessage = '';

if ($book['copie_disponibili'] > 0) {
    // CASO A: Libro Disponibile -> Niente prenotazione, vai a prenderlo!
    $statusClass = 'success';
    $statusText = 'Disponibile (' . $book['copie_disponibili'] . ' copie)';
    $statusIcon = 'check-circle';
    $canReserve = false;
    $userMessage = '<span class="text-success fw-bold"><i class="fas fa-walking me-1"></i> Disponibile subito!</span> Passa in biblioteca a ritirarlo.';

} elseif ($book['copie_totali'] > 0) {
    // CASO B: Copie finite (In prestito) -> Abilita Prenotazione (Coda)
    $statusClass = 'warning';
    $statusText = 'In Prestito (Tutte le copie occupate)';
    $statusIcon = 'clock';
    $canReserve = true;
    $userMessage = 'Tutte le copie sono fuori. <strong>Prenotalo ora</strong> per metterti in coda.';

} else {
    // CASO C: Nessuna copia fisica esistente (Smarrito/Fuori Catalogo)
    $statusClass = 'danger';
    $statusText = 'Non Disponibile';
    $statusIcon = 'times-circle';
    $canReserve = false;
    $userMessage = '<span class="text-danger">Non ci sono copie consultabili di questo testo.</span>';
}

// Controllo se l'utente ha GIÀ prenotato (se loggato)
$alreadyReserved = false;
if (isset($_SESSION['user_id']) && $canReserve && $hasReservationModel) {
    try {
        $resModel = new ReservationModel();
        if ($resModel->hasActiveReservation($_SESSION['user_id'], $id)) {
            $alreadyReserved = true;
            $canReserve = false; // Disabilita tasto se già in coda
            $userMessage = '<span class="text-primary fw-bold">Sei già in lista d\'attesa</span> per questo libro.';
        }
    } catch (Exception $e) { /* Ignora errori model */ }
}

// Recupero messaggi flash (feedback prenotazione)
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once __DIR__ . '/../src/Views/layout/header.php';
?>

    <div class="container py-5">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Home</a></li>
                <li class="breadcrumb-item"><a href="catalog.php" class="text-decoration-none text-muted">Catalogo</a></li>
                <li class="breadcrumb-item active fw-bold text-dark" aria-current="page"><?= htmlspecialchars($book['titolo']) ?></li>
            </ol>
        </nav>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-5">
            <div class="col-md-4 col-lg-3">
                <div class="position-sticky" style="top: 2rem;">
                    <div class="card border-0 shadow rounded-3 overflow-hidden mb-3">
                        <img src="<?= htmlspecialchars($img) ?>" class="img-fluid w-100" alt="Copertina" style="object-fit: cover;">
                    </div>

                    <div class="d-grid">
                        <div class="alert alert-<?= $statusClass ?> d-flex align-items-center justify-content-center fw-bold mb-0 py-2 shadow-sm">
                            <i class="fas fa-<?= $statusIcon ?> me-2"></i> <?= $statusText ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8 col-lg-9">
                <div class="mb-4">
                    <h1 class="fw-bold display-5 mb-2 text-dark"><?= htmlspecialchars($book['titolo']) ?></h1>
                    <h4 class="text-muted fw-normal">
                        di <span class="text-dark fw-semibold border-bottom border-secondary"><?= htmlspecialchars($book['autori_nomi'] ?? 'Autore Sconosciuto') ?></span>
                    </h4>
                </div>

                <div class="card bg-white border-0 shadow-sm p-4 mb-5 rounded-3 border-start border-5 border-<?= ($statusClass == 'success') ? 'success' : ($canReserve ? 'warning' : 'secondary') ?>">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase small text-muted fw-bold mb-1">Stato Disponibilità</div>
                            <p class="mb-0 fs-6"><?= $userMessage ?></p>
                        </div>

                        <div>
                            <?php if (isset($_SESSION['user_id'])): ?>

                                <?php if ($canReserve): ?>
                                    <form action="../dashboard/student/process-reservation.php" method="POST">
                                        <input type="hidden" name="book_id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-warning text-dark fw-bold btn-lg rounded-pill px-5 shadow-sm">
                                            <i class="fas fa-history me-2"></i>Mettiti in Coda
                                        </button>
                                    </form>

                                <?php elseif ($alreadyReserved): ?>
                                    <button class="btn btn-primary btn-lg rounded-pill px-5 disabled opacity-75" disabled>
                                        <i class="fas fa-clock me-2"></i>Sei in coda
                                    </button>

                                <?php elseif ($book['copie_disponibili'] > 0): ?>
                                    <button class="btn btn-outline-success btn-lg rounded-pill px-5 disabled" style="opacity: 1; border-width: 2px;">
                                        <i class="fas fa-map-marker-alt me-2"></i>Vieni a ritirarlo
                                    </button>

                                <?php else: ?>
                                    <button class="btn btn-secondary btn-lg rounded-pill px-5 disabled" disabled>
                                        Non Disponibile
                                    </button>
                                <?php endif; ?>

                            <?php else: ?>
                                <a href="login.php?redirect=book.php?id=<?= $id ?>" class="btn btn-outline-dark btn-lg rounded-pill px-5">
                                    Accedi per Info
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-align-left me-2 text-secondary"></i>Descrizione</h5>
                    <p class="lead fs-6 text-secondary text-justify" style="line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($book['descrizione'] ?? 'Nessuna descrizione disponibile.')) ?>
                    </p>
                </div>

                <div class="card bg-light border-0 rounded-3 p-4">
                    <h5 class="fw-bold text-dark mb-4"><i class="fas fa-info-circle me-2 text-secondary"></i>Dettagli Tecnici</h5>
                    <div class="row g-4">
                        <div class="col-sm-6 col-md-4">
                            <div class="text-uppercase small text-muted fw-bold mb-1">Editore</div>
                            <div class="fs-6 fw-semibold text-dark"><?= htmlspecialchars($book['editore'] ?? 'N/D') ?></div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-uppercase small text-muted fw-bold mb-1">Anno</div>
                            <div class="fs-6 fw-semibold text-dark"><?= $book['anno_uscita'] ? date('Y', strtotime($book['anno_uscita'])) : 'N/D' ?></div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-uppercase small text-muted fw-bold mb-1">Pagine</div>
                            <div class="fs-6 fw-semibold text-dark"><?= $book['numero_pagine'] ?? 'N/D' ?></div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-uppercase small text-muted fw-bold mb-1">ISBN</div>
                            <div class="fs-6 fw-semibold font-monospace text-dark"><?= htmlspecialchars($book['isbn']) ?></div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-uppercase small text-muted fw-bold mb-1">Categoria</div>
                            <div class="fs-6 fw-semibold text-dark">Generale</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <style>
        .text-justify { text-align: justify; }
    </style>

<?php require_once __DIR__ . '/../src/Views/layout/footer.php'; ?>