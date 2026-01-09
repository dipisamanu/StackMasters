<?php
/**
 * Catalogo Pubblico (Epic 3.10 + 3.11)
 * File: public/catalog.php
 */

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/Models/BookModel.php';

// Setup Paginazione
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$perPage = 12; // 12 Libri per pagina (4x3)

// Setup Filtri
$search = $_GET['q'] ?? '';
$filterAvailable = isset($_GET['available']) && $_GET['available'] === 'on';
$filters = $filterAvailable ? ['solo_disponibili' => true] : [];

// Recupero Dati dal Model
$bookModel = new BookModel();
$books = $bookModel->paginate($page, $perPage, $search, $filters);
$totalBooks = $bookModel->count($search, $filters);
$totalPages = ceil($totalBooks / $perPage);

require_once __DIR__ . '/../src/Views/layout/header.php';
?>

    <div class="container py-5">

        <div class="text-center mb-5">
            <h1 class="fw-bold display-5 text-primary mb-3">Esplora la Biblioteca</h1>
            <p class="lead text-muted mb-4">Trova il tuo prossimo libro tra i <?= $totalBooks ?> titoli disponibili.</p>

            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <form method="GET" class="card card-body shadow-sm border-0 p-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-0 ps-3"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-0 shadow-none"
                                   placeholder="Cerca per titolo, autore o ISBN..."
                                   value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary rounded-pill px-4 ms-2">Cerca</button>
                        </div>
                        <hr class="my-2 text-muted opacity-25">
                        <div class="d-flex justify-content-between px-2 align-items-center">
                            <div class="form-check form-switch small">
                                <input class="form-check-input" type="checkbox" name="available" id="avail"
                                    <?= $filterAvailable ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label text-muted" for="avail">Mostra solo disponibili</label>
                            </div>
                            <?php if(!empty($search) || $filterAvailable): ?>
                                <a href="catalog.php" class="text-decoration-none small text-danger"><i class="fas fa-times me-1"></i>Reset filtri</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (empty($books)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-book-open fa-4x mb-3 opacity-50"></i>
                <h3>Nessun libro trovato</h3>
                <p>Prova a modificare i termini di ricerca.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($books as $b): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card h-100 shadow-sm border-0 book-card">

                            <div class="position-relative overflow-hidden bg-light d-flex align-items-center justify-content-center" style="height: 280px;">
                                <?php
                                $img = 'assets/img/placeholder.png'; // Fallback
                                if (!empty($b['immagine_copertina'])) {
                                    // Se è un URL remoto o un percorso locale
                                    $img = (strpos($b['immagine_copertina'], 'http') === 0)
                                        ? $b['immagine_copertina']
                                        : $b['immagine_copertina'];
                                }
                                ?>
                                <img src="<?= htmlspecialchars($img) ?>" class="card-img-top h-100 w-100" style="object-fit: cover;" alt="<?= htmlspecialchars($b['titolo']) ?>">

                                <div class="position-absolute top-0 end-0 p-2">
                                    <?php if ($b['copie_disponibili'] > 0): ?>
                                        <span class="badge bg-success shadow-sm"><i class="fas fa-check me-1"></i>Disp.</span>
                                    <?php elseif ($b['copie_totali'] > 0): ?>
                                        <span class="badge bg-warning text-dark shadow-sm"><i class="fas fa-clock me-1"></i>In Prestito</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger shadow-sm">Non Disp.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title fw-bold text-dark text-truncate mb-1" title="<?= htmlspecialchars($b['titolo']) ?>">
                                    <a href="book.php?id=<?= $b['id_libro'] ?>" class="text-decoration-none text-dark stretched-link">
                                        <?= htmlspecialchars($b['titolo']) ?>
                                    </a>
                                </h6>
                                <p class="card-text small text-muted mb-2">
                                    <i class="fas fa-pen-nib me-1"></i> <?= htmlspecialchars($b['autori_nomi'] ?? 'Autore Sconosciuto') ?>
                                </p>

                                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center small">
                                    <span class="text-muted"><?= $b['anno_uscita'] ? date('Y', strtotime($b['anno_uscita'])) : '-' ?></span>
                                    <span class="badge bg-light text-secondary border"><?= htmlspecialchars($b['editore'] ?? 'N/D') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&available=<?= $filterAvailable?'on':'' ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>

                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&available=<?= $filterAvailable?'on':'' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&available=<?= $filterAvailable?'on':'' ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <style>
        /* Effetto Hover sulle Card */
        .book-card { transition: transform 0.2s, box-shadow 0.2s; }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1)!important; }

        /* Immagine di fallback se il link è rotto */
        img:not([src]), img[src=""] { visibility: hidden; }
    </style>

<?php require_once __DIR__ . '/../src/Views/layout/footer.php'; ?>