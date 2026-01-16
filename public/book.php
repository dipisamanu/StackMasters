<?php
/**
 * Dettaglio Libro Pubblico
 * File: public/book.php
 */

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/Models/BookModel.php';

// Caricamento Modelli opzionali
$hasReservationModel = file_exists(__DIR__ . '/../src/Models/ReservationModel.php');
if ($hasReservationModel) {
    require_once __DIR__ . '/../src/Models/ReservationModel.php';
}

// Validazione Input
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: catalog.php');
    exit;
}

$bookModel = new BookModel();
$userId = Session::getUserId();

// -------------------------------------------------------------
// GESTIONE AZIONI RECENSIONI (POST)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_review']) && Session::isLoggedIn()) {
    $action = $_POST['action_review'];

    if ($action === 'save') {
        $voto = (int)$_POST['voto'];
        $commento = trim($_POST['commento']);

        if ($voto >= 1 && $voto <= 5 && !empty($commento)) {
            if ($bookModel->saveReview($userId, $id, $voto, $commento)) {
                $_SESSION['flash_success'] = "Recensione salvata con successo!";
            } else {
                $_SESSION['flash_error'] = "Errore nel salvataggio.";
            }
        } else {
            $_SESSION['flash_error'] = "Inserisci un voto e un commento.";
        }
    } elseif ($action === 'delete') {
        if ($bookModel->deleteReview($userId, $id)) {
            $_SESSION['flash_success'] = "Recensione eliminata.";
        } else {
            $_SESSION['flash_error'] = "Errore nell'eliminazione.";
        }
    }

    header("Location: book.php?id=$id");
    exit;
}

// -------------------------------------------------------------
// RECUPERO DATI
// -------------------------------------------------------------
$book = $bookModel->getById($id);

if (!$book) {
    require_once __DIR__ . '/../src/Views/layout/header.php';
    echo '<div class="container py-5 text-center"><h2>Libro non trovato</h2><a href="catalog.php" class="btn btn-primary">Torna al Catalogo</a></div>';
    require_once __DIR__ . '/../src/Views/layout/footer.php';
    exit;
}

$similarBooks = $bookModel->getSimilarBooks($id, 10);
$allReviews = $bookModel->getReviews($id);

// Separa recensione utente dalle altre
$userReview = null;
$otherReviews = [];

if (Session::isLoggedIn()) {
    foreach ($allReviews as $r) {
        if ($r['id_utente'] == $userId) {
            $userReview = $r;
        } else {
            $otherReviews[] = $r;
        }
    }
} else {
    $otherReviews = $allReviews;
}

$canVote = false;
if (Session::isLoggedIn()) {
    $canVote = $bookModel->hasUserReadBook($userId, $id);
}

// Immagine
$img = 'assets/img/placeholder.png';
if (!empty($book['immagine_copertina'])) {
    $img = (str_starts_with($book['immagine_copertina'], 'http')) ? $book['immagine_copertina'] : 'uploads/covers/' . $book['immagine_copertina'];
}

// Stato e Prenotazione
$statusClass = 'secondary'; $statusText = ''; $canReserve = false; $userMessage = '';

if ($book['copie_disponibili'] > 0) {
    $statusClass = 'success'; $statusText = 'Disponibile (' . $book['copie_disponibili'] . ')';
    $canReserve = false; $userMessage = '<span class="text-success fw-bold">Disponibile subito!</span>';
} elseif ($book['copie_totali'] > 0) {
    $statusClass = 'warning'; $statusText = 'In Prestito';
    $canReserve = true; $userMessage = 'Tutte le copie sono fuori. <strong>Prenotalo ora</strong>.';
} else {
    $statusClass = 'danger'; $statusText = 'Non Disponibile';
    $canReserve = false; $userMessage = '<span class="text-danger">Non disponibile.</span>';
}

$alreadyReserved = false;
if (isset($_SESSION['user_id']) && $canReserve && $hasReservationModel) {
    try {
        $resModel = new ReservationModel();
        if ($resModel->hasActiveReservation($_SESSION['user_id'], $id)) {
            $alreadyReserved = true; $canReserve = false;
            $userMessage = '<span class="text-primary fw-bold">Sei già in coda.</span>';
        }
    } catch (Exception $e) { }
}

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once __DIR__ . '/../src/Views/layout/header.php';
?>

    <style>
        /* Scroller Libri Simili */
        .similar-scroller { display: flex; overflow-x: auto; gap: 15px; padding: 10px 5px 20px 5px; scroll-behavior: smooth; }
        .similar-card { flex: 0 0 120px; width: 120px; text-decoration: none; transition: transform 0.2s; }
        .similar-card:hover { transform: translateY(-5px); }
        .similar-cover { height: 170px; width: 100%; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        /* Star Rating nel Form */
        .rating-css { display: flex; flex-direction: row-reverse; justify-content: center; gap: 10px; }
        .rating-css input { display: none; }
        .rating-css label { font-size: 30px; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .rating-css input:checked ~ label, .rating-css label:hover, .rating-css label:hover ~ label { color: #ffc107; }

        /* Scrollable Reviews Area */
        .reviews-scroll-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px; /* Spazio per scrollbar */
        }

        /* Scrollbar personalizzata per container recensioni */
        .reviews-scroll-container::-webkit-scrollbar { width: 6px; }
        .reviews-scroll-container::-webkit-scrollbar-track { background: #f1f1f1; }
        .reviews-scroll-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }

        .my-review-card { border: 2px solid #0d6efd; background-color: #f8fbff; }
    </style>

    <div class="container py-5">

        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Home</a></li>
                <li class="breadcrumb-item"><a href="catalog.php" class="text-decoration-none text-muted">Catalogo</a></li>
                <li class="breadcrumb-item active fw-bold text-dark"><?= htmlspecialchars($book['titolo']) ?></li>
            </ol>
        </nav>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row g-5">
            <div class="col-md-4 col-lg-3">
                <div class="card border-0 shadow rounded-3 overflow-hidden mb-3">
                    <img src="<?= htmlspecialchars($img) ?>" class="img-fluid w-100" alt="Copertina">
                </div>
                <div class="d-grid"><div class="alert alert-<?= $statusClass ?> text-center fw-bold mb-0"><?= $statusText ?></div></div>
            </div>

            <div class="col-md-8 col-lg-9">
                <h1 class="fw-bold display-5 mb-2"><?= htmlspecialchars($book['titolo']) ?></h1>
                <h4 class="text-muted fw-normal mb-4">di <span class="text-dark fw-bold"><?= htmlspecialchars($book['autori_nomi'] ?? 'Autore Sconosciuto') ?></span></h4>

                <div class="card bg-white border-0 shadow-sm p-4 mb-5 rounded-3 border-start border-5 border-<?= ($statusClass == 'success') ? 'success' : ($canReserve ? 'warning' : 'secondary') ?>">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div><p class="mb-0 fs-6"><?= $userMessage ?></p></div>
                        <div>
                            <?php if (Session::isLoggedIn() && $canReserve): ?>
                                <form action="../dashboard/student/process-reservation.php" method="POST">
                                    <input type="hidden" name="book_id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-warning fw-bold rounded-pill px-4">Mettiti in Coda</button>
                                </form>
                            <?php elseif (!Session::isLoggedIn()): ?>
                                <a href="login.php?redirect=book.php?id=<?= $id ?>" class="btn btn-outline-dark rounded-pill">Accedi</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="fw-bold">Descrizione</h5>
                    <p class="lead fs-6 text-secondary text-justify"><?= nl2br(htmlspecialchars($book['descrizione'] ?? '')) ?></p>
                </div>

                <div class="mt-5 border-top pt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="fw-bold m-0"><i class="fas fa-star text-warning me-2"></i>Recensioni</h3>

                        <?php if (Session::isLoggedIn() && $canVote && !$userReview): ?>
                            <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                <i class="fas fa-pen me-2"></i>Scrivi Recensione
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($userReview): ?>
                        <div class="card my-review-card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="fw-bold text-primary mb-1"><i class="fas fa-user-circle me-2"></i>La tua recensione</h6>
                                        <div class="text-warning small mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?= $i <= $userReview['voto'] ? 'fas' : 'far' ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="text-muted ms-2 fw-normal"><?= date('d/m/Y', strtotime($userReview['data_creazione'])) ?></span>
                                        </div>
                                        <p class="mb-2 text-dark"><?= nl2br(htmlspecialchars($userReview['descrizione'])) ?></p>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#reviewModal"><i class="fas fa-edit me-2"></i>Modifica</a></li>
                                            <li>
                                                <form method="POST" action="" onsubmit="return confirm('Vuoi davvero eliminare la tua recensione?');">
                                                    <input type="hidden" name="action_review" value="delete">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash-alt me-2"></i>Elimina</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="reviews-scroll-container">
                        <?php if (empty($otherReviews)): ?>
                            <?php if (!$userReview): ?>
                                <div class="text-center py-4 bg-light rounded-3">
                                    <p class="text-muted mb-0 fst-italic">Ancora nessuna recensione. Sii il primo!</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($otherReviews as $rev): ?>
                                    <div class="card border-0 bg-light p-3 rounded-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-weight: bold; font-size: 0.9rem;">
                                                    <?= strtoupper(substr($rev['nome'], 0, 1) . substr($rev['cognome'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold small"><?= htmlspecialchars($rev['nome'] . ' ' . $rev['cognome']) ?></h6>
                                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date('d/m/Y', strtotime($rev['data_creazione'])) ?></small>
                                                </div>
                                            </div>
                                            <div class="text-warning small">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="<?= $i <= $rev['voto'] ? 'fas' : 'far' ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <p class="mb-0 text-dark small ps-5"><?= nl2br(htmlspecialchars($rev['descrizione'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($similarBooks)): ?>
            <div class="mt-5 pt-4 border-top">
                <h4 class="fw-bold text-dark mb-4">Chi ha letto questo ha letto anche...</h4>
                <div class="similar-scroller">
                    <?php foreach ($similarBooks as $sim):
                        $imgSim = (str_starts_with($sim['immagine_copertina'], 'http')) ? $sim['immagine_copertina'] : 'uploads/covers/' . $sim['immagine_copertina'];
                        ?>
                        <a href="book.php?id=<?= $sim['id_libro'] ?>" class="similar-card">
                            <img src="<?= htmlspecialchars($imgSim) ?>" class="similar-cover mb-2" alt="Cover">
                            <h6 class="text-dark small fw-bold text-truncate mb-0"><?= htmlspecialchars($sim['titolo']) ?></h6>
                            <?php if (isset($sim['tipo_correlazione']) && $sim['tipo_correlazione'] === 'UTENTI'): ?>
                                <span class="badge bg-success" style="font-size: 0.6rem;">Scelto dai lettori</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

<?php if (Session::isLoggedIn()): ?>
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><?= $userReview ? 'Modifica Recensione' : 'Scrivi Recensione' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0">
                    <form method="POST" action="">
                        <input type="hidden" name="action_review" value="save">

                        <div class="text-center py-3">
                            <p class="text-muted small mb-2 text-uppercase fw-bold">Il tuo voto</p>
                            <div class="rating-css">
                                <?php $myVote = $userReview['voto'] ?? 0; ?>
                                <input type="radio" value="5" name="voto" id="rate5" <?= $myVote == 5 ? 'checked' : '' ?>><label for="rate5" class="fas fa-star"></label>
                                <input type="radio" value="4" name="voto" id="rate4" <?= $myVote == 4 ? 'checked' : '' ?>><label for="rate4" class="fas fa-star"></label>
                                <input type="radio" value="3" name="voto" id="rate3" <?= $myVote == 3 ? 'checked' : '' ?>><label for="rate3" class="fas fa-star"></label>
                                <input type="radio" value="2" name="voto" id="rate2" <?= $myVote == 2 ? 'checked' : '' ?>><label for="rate2" class="fas fa-star"></label>
                                <input type="radio" value="1" name="voto" id="rate1" <?= $myVote == 1 ? 'checked' : '' ?>><label for="rate1" class="fas fa-star"></label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Commento</label>
                            <textarea name="commento" class="form-control bg-light border-0" rows="4" placeholder="Cosa ne pensi di questo libro?" required><?= htmlspecialchars($userReview['descrizione'] ?? '') ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary rounded-pill fw-bold">Pubblica</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../src/Views/layout/footer.php'; ?>