<?php
/**
 * Catalogo Pubblico Avanzato
 * File: public/catalog.php
 */

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/Models/BookModel.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// paginazione
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$perPage = 25;

// filtri
$search = $_GET['q'] ?? '';

$filters = [
        'solo_disponibili' => isset($_GET['available']) && $_GET['available'] === 'on',
        'anno_min' => $_GET['year_min'] ?? null,
        'anno_max' => $_GET['year_max'] ?? null,
        'rating_min' => $_GET['rating'] ?? null,
        'condizione' => $_GET['condition'] ?? null,
        'sort_by' => $_GET['sort'] ?? 'relevance' // Default: Rilevanza
];

// recupero libri
$bookModel = new BookModel();
$result = $bookModel->paginateWithCount($page, $perPage, $search, $filters);
$books = $result['data'];
$totalBooks = $result['total'];
$totalPages = ceil($totalBooks / $perPage);

require_once __DIR__ . '/../src/Views/layout/header.php';
?>

    <div class="container-fluid py-5 px-4">
        <div class="row">

            <div class="col-lg-3 col-xl-2 mb-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px; z-index: 1;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="fw-bold"><i class="fas fa-filter me-2 text-primary"></i>Filtri</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="catalog.php">

                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Ordina per</label>
                                <select class="form-select form-select-sm" name="sort" onchange="this.form.submit()">
                                    <option value="relevance" <?= $filters['sort_by'] == 'relevance' ? 'selected' : '' ?>>
                                        Rilevanza
                                    </option>
                                    <option value="popularity" <?= $filters['sort_by'] == 'popularity' ? 'selected' : '' ?>>
                                        Più Popolari
                                    </option>
                                    <option value="rating" <?= $filters['sort_by'] == 'rating' ? 'selected' : '' ?>>
                                        Valutazione Migliore
                                    </option>
                                    <option value="date_desc" <?= $filters['sort_by'] == 'date_desc' ? 'selected' : '' ?>>
                                        Più Recenti
                                    </option>
                                    <option value="date_asc" <?= $filters['sort_by'] == 'date_asc' ? 'selected' : '' ?>>
                                        Più Vecchi
                                    </option>
                                    <option value="alpha" <?= $filters['sort_by'] == 'alpha' ? 'selected' : '' ?>>
                                        Alfabetico (A-Z)
                                    </option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="available" id="avail"
                                            <?= $filters['solo_disponibili'] ? 'checked' : '' ?>
                                           onchange="this.form.submit()">
                                    <label class="form-check-label" for="avail">Solo disponibili</label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Anno
                                    Pubblicazione</label>
                                <div class="d-flex gap-2">
                                    <input type="number" name="year_min" class="form-control form-control-sm"
                                           placeholder="Da"
                                           value="<?= htmlspecialchars($filters['anno_min'] ?? '') ?>" min="1800"
                                           max="2099">
                                    <input type="number" name="year_max" class="form-control form-control-sm"
                                           placeholder="A"
                                           value="<?= htmlspecialchars($filters['anno_max'] ?? '') ?>" min="1800"
                                           max="2099">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Valutazione
                                    Minima</label>
                                <select class="form-select form-select-sm" name="rating">
                                    <option value="">Tutti</option>
                                    <option value="4" <?= $filters['rating_min'] == '4' ? 'selected' : '' ?>>4+ Stelle
                                    </option>
                                    <option value="3" <?= $filters['rating_min'] == '3' ? 'selected' : '' ?>>3+ Stelle
                                    </option>
                                    <option value="2" <?= $filters['rating_min'] == '2' ? 'selected' : '' ?>>2+ Stelle
                                    </option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Condizione
                                    Copia</label>
                                <select class="form-select form-select-sm" name="condition">
                                    <option value="">Qualsiasi</option>
                                    <option value="BUONO" <?= $filters['condizione'] == 'BUONO' ? 'selected' : '' ?>>
                                        Buono
                                    </option>
                                    <option value="DANNEGGIATO" <?= $filters['condizione'] == 'DANNEGGIATO' ? 'selected' : '' ?>>
                                        Danneggiato
                                    </option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Applica Filtri</button>
                                <a href="catalog.php" class="btn btn-outline-secondary btn-sm">Resetta Tutto</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-9 col-xl-10">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-primary mb-0">Catalogo</h2>
                        <span class="text-muted small">Trovati <?= $totalBooks ?> libri</span>
                    </div>
                    <div class="w-50">
                        <form method="GET" class="input-group">
                            <?php foreach ($filters as $key => $val):
                                if ($key != 'sort_by' && $val) echo "<input type='hidden' name='$key' value='$val'>";
                            endforeach; ?>

                            <input type="text" name="q" class="form-control" placeholder="Cerca titolo, autore, ISBN..."
                                   value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                </div>

                <?php if (empty($books)): ?>
                    <div class="text-center py-5 bg-light rounded-3">
                        <i class="fas fa-ghost fa-3x mb-3 text-muted opacity-50"></i>
                        <h4>Nessun risultato</h4>
                        <p class="text-muted">Prova a rimuovere qualche filtro o cambia termine di ricerca.</p>
                        <a href="catalog.php" class="btn btn-primary btn-sm mt-2">Mostra Tutto</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4 row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
                        <?php foreach ($books as $b): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm border-0 book-card">

                                    <div class="position-relative overflow-hidden bg-white text-center border-bottom"
                                         style="height: 260px;">
                                        <?php
                                        $img = 'assets/img/placeholder.png';
                                        if (!empty($b['immagine_copertina'])) {
                                            $img = (str_starts_with($b['immagine_copertina'], 'http'))
                                                    ? $b['immagine_copertina']
                                                    : 'uploads/covers/' . $b['immagine_copertina'];
                                        }
                                        ?>
                                        <img src="<?= htmlspecialchars($img) ?>" class="h-100 w-100"
                                             style="object-fit: contain;" alt="Cover">

                                        <div class="position-absolute top-0 end-0 p-2">
                                            <?php if ($b['copie_disponibili'] > 0): ?>
                                                <span class="badge bg-success shadow-sm">Disponibile</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger shadow-sm">Non Disp.</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($b['rating'] > 0): ?>
                                            <div class="position-absolute bottom-0 start-0 p-2">
                                        <span class="badge bg-warning text-dark border border-warning shadow-sm">
                                            <i class="fas fa-star text-white"></i> <?= number_format($b['rating'], 1) ?>
                                        </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title fw-bold mb-1">
                                            <a href="book.php?id=<?= $b['id_libro'] ?>"
                                               class="text-decoration-none text-dark stretched-link">
                                                <?= htmlspecialchars($b['titolo']) ?>
                                            </a>
                                        </h6>
                                        <p class="text-muted small mb-2">
                                            <?= htmlspecialchars($b['autori_nomi'] ?? 'Autore ignoto') ?>
                                        </p>

                                        <div class="mt-auto d-flex justify-content-between align-items-center small text-muted border-top pt-2">
                                            <span><?= $b['anno_uscita'] ? date('Y', strtotime($b['anno_uscita'])) : 'N/D' ?></span>
                                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($b['editore'] ?? 'N/D') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        ?>
                        <nav class="mt-5">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $queryString ?>"><i
                                                class="fas fa-chevron-left"></i></a>
                                </li>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= $queryString ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $queryString ?>"><i
                                                class="fas fa-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .book-card {
            transition: transform 0.2s;
        }

        .book-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
        }
    </style>

<?php require_once __DIR__ . '/../src/Views/layout/footer.php';
