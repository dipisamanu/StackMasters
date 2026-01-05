<?php
/**
 * Dettaglio Libro Pubblico (Epic 3.12 - Scheda Completa)
 * File: public/book.php
 */

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/Models/BookModel.php';

// 1. Validazione Input
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: catalog.php');
    exit;
}

// 2. Recupero Dati
$bookModel = new BookModel();
$book = $bookModel->getById($id);

// Se il libro non esiste nel DB
if (!$book) {
    require_once __DIR__ . '/../src/Views/layout/header.php';
    echo '<div class="container py-5 text-center" style="min-height:60vh; display:flex; flex-direction:column; justify-content:center;">
            <div class="mb-4 text-muted opacity-25"><i class="fas fa-book-dead fa-5x"></i></div>
            <h2 class="display-5 fw-bold text-dark">Libro non trovato</h2>
            <p class="lead text-muted mb-4">Il libro che stai cercando non esiste o è stato rimosso.</p>
            <div><a href="catalog.php" class="btn btn-outline-primary px-4 rounded-pill">Torna al Catalogo</a></div>
          </div>';
    require_once __DIR__ . '/../src/Views/layout/footer.php';
    exit;
}

// 3. Preparazione Variabili Vista
$pageTitle = $book['titolo'] . " - BiblioSystem";

// Gestione Immagine (Locale o Remota)
$img = 'assets/img/placeholder.png';
if (!empty($book['immagine_copertina'])) {
    $img = (strpos($book['immagine_copertina'], 'http') === 0)
        ? $book['immagine_copertina']
        : $book['immagine_copertina'];
}

// Logica Disponibilità
$statusClass = 'success';
$statusText = 'Disponibile';
$statusIcon = 'check-circle';
$canReserve = true;

if ($book['copie_disponibili'] > 0) {
    $statusClass = 'success';
    $statusText = 'Disponibile (' . $book['copie_disponibili'] . ' copie)';
} elseif ($book['copie_totali'] > 0) {
    $statusClass = 'warning';
    $statusText = 'In Prestito (Torna presto)';
    $statusIcon = 'clock';
} else {
    $statusClass = 'danger';
    $statusText = 'Non Disponibile';
    $statusIcon = 'times-circle';
    $canReserve = false;
}

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

        <div class="row g-5">
            <div class="col-md-4 col-lg-3">
                <div class="position-sticky" style="top: 2rem;">
                    <div class="card border-0 shadow rounded-3 overflow-hidden mb-3">
                        <img src="<?= htmlspecialchars($img) ?>" class="img-fluid w-100" alt="Copertina" style="object-fit: cover;">
                    </div>

                    <div class="d-grid">
                        <div class="alert alert-<?= $statusClass ?> d-flex align-items-center justify-content-center fw-bold mb-0 py-2">
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

                <div class="card bg-white border-0 shadow-sm p-4 mb-5 rounded-3 border-start border-5 border-<?= $statusClass ?>">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase small text-muted fw-bold mb-1">Cosa vuoi fare?</div>
                            <?php if($canReserve): ?>
                                <p class="mb-0 text-dark">Puoi prenotare questo libro e ritirarlo in segreteria.</p>
                            <?php else: ?>
                                <p class="mb-0 text-danger">Attualmente non ci sono copie prenotabili.</p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <?php if ($canReserve): ?>
                                    <button class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm" onclick="alert('Funzionalità Prenotazione (Epic 6) in arrivo!')">
                                        <i class="fas fa-bookmark me-2"></i>Prenota Ora
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-lg rounded-pill px-5 disabled" disabled>
                                        Non Prenotabile
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php?redirect=book.php?id=<?= $id ?>" class="btn btn-outline-primary btn-lg rounded-pill px-5">
                                    Accedi per Prenotare
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-align-left me-2 text-secondary"></i>Trama</h5>
                    <p class="lead fs-6 text-secondary text-justify" style="line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($book['descrizione'] ?? 'Nessuna descrizione disponibile per questo libro.')) ?>
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
                            <div class="text-uppercase small text-muted fw-bold mb-1">Codice ISBN</div>
                            <div class="fs-6 fw-semibold font-monospace text-dark"><?= htmlspecialchars($book['isbn']) ?></div>
                        </div>

                        <div class="col-sm-6 col-md-4">
                            <div class="text-uppercase small text-muted fw-bold mb-1">Lingua</div>
                            <div class="fs-6 fw-semibold text-dark">Italiano</div>
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