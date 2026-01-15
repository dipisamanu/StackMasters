<?php
/**
 * Catalogo Pubblico Avanzato
 * File: public/catalog.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/Models/BookModel.php';

$bookModel = new BookModel();
$genres = $bookModel->getAllGenres();

// Paginazione
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25; // 5x5 libri

// Defaults per lo slider
$currentYear = date('Y');
$minYearDb = 1900; // Anno minimo base

// Recupero Filtri
$filters = [
        'q' => $_GET['q'] ?? '',
        'sort' => $_GET['sort'] ?? 'relevance',
        'genre' => $_GET['genre'] ?? '',
        'year_min' => $_GET['year_min'] ?? $minYearDb,
        'year_max' => $_GET['year_max'] ?? $currentYear,
        'rating' => $_GET['rating'] ?? '',
        'condition' => $_GET['condition'] ?? '',
        'available' => $_GET['available'] ?? ''
];

// Esecuzione Query
$results = $bookModel->searchBooks($page, $perPage, $filters);
$books = $results['data'];
$totalBooks = $results['total'];
$totalPages = ceil($totalBooks / $perPage);

require_once __DIR__ . '/../src/Views/layout/header.php';
?>

    <style>
        :root {
            --primary-color: #0d6efd;
        }

        .slider-container {
            position: relative;
            height: 30px;
            margin-top: 10px;
        }

        .slider-container input[type="range"] {
            position: absolute;
            pointer-events: none;
            -webkit-appearance: none;
            z-index: 2;
            height: 10px;
            width: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .slider-container input[type="range"]::-webkit-slider-thumb {
            pointer-events: all;
            width: 18px;
            height: 18px;
            -webkit-appearance: none;
            background-color: var(--primary-color);
            border: 2px solid white;
            border-radius: 50%;
            cursor: grab;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 3;
        }

        /* Compatibilità Firefox */
        .slider-container input[type="range"]::-moz-range-thumb {
            pointer-events: all;
            width: 18px;
            height: 18px;
            background-color: var(--primary-color);
            border: 2px solid white;
            border-radius: 50%;
            cursor: grab;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        /* Traccia grigia di sfondo */
        .slider-track {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            left: 0;
            right: 0;
            height: 4px;
            background: #e9ecef;
            border-radius: 5px;
            z-index: 1;
        }

        /* Traccia colorata (tra i due punti) */
        .slider-range {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            height: 4px;
            background: var(--primary-color);
            border-radius: 5px;
            z-index: 1;
        }

        .slider-reset {
            font-size: 0.75rem;
            color: #6c757d;
            cursor: pointer;
            text-decoration: underline;
            transition: color 0.2s ease;
            border: none;
            background: none;
            padding: 0;
        }

        .slider-reset:hover {
            color: #dc3545; /* Rosso */
        }

        .year-input {
            font-size: 0.85rem;
            font-weight: bold;
            color: var(--primary-color);
            border: 1px solid #ced4da;
            padding: 2px 5px;
            text-align: center;
            width: 65px;
            border-radius: 4px;
        }

        .year-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
    </style>

    <div class="container-fluid py-5 px-4">
        <div class="row">

            <div class="col-lg-3 col-xl-2 mb-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px; z-index: 1;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold m-0"><i class="fas fa-filter me-2 text-primary"></i>Filtri</h5>
                    </div>

                    <div class="card-body">
                        <form method="GET" action="catalog.php" id="filterForm">

                            <?php if (!empty($filters['q'])): ?>
                                <input type="hidden" name="q" value="<?= htmlspecialchars($filters['q']) ?>">
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Ordina per</label>
                                <select class="form-select form-select-sm" name="sort">
                                    <option value="relevance" <?= $filters['sort'] == 'relevance' ? 'selected' : '' ?>>
                                        Rilevanza
                                    </option>
                                    <option value="popularity" <?= $filters['sort'] == 'popularity' ? 'selected' : '' ?>>
                                        Più Popolari
                                    </option>
                                    <option value="rating" <?= $filters['sort'] == 'rating' ? 'selected' : '' ?>>
                                        Valutazione Migliore
                                    </option>
                                    <option value="date_desc" <?= $filters['sort'] == 'date_desc' ? 'selected' : '' ?>>
                                        Più Recenti
                                    </option>
                                    <option value="date_asc" <?= $filters['sort'] == 'date_asc' ? 'selected' : '' ?>>Più
                                        Vecchi
                                    </option>
                                    <option value="alpha" <?= $filters['sort'] == 'alpha' ? 'selected' : '' ?>>
                                        Alfabetico (A-Z)
                                    </option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="available" id="avail"
                                            <?= $filters['available'] === 'on' ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="avail">Solo disponibili</label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-end mb-1">
                                    <label class="form-label small fw-bold text-muted text-uppercase m-0">Anno</label>
                                    <button type="button" class="slider-reset" onclick="resetSlider()">reset</button>
                                </div>

                                <div class="slider-container">
                                    <div class="slider-track"></div>
                                    <div class="slider-range" id="sliderRange"></div>
                                    <input type="range" min="<?= $minYearDb ?>" max="<?= $currentYear ?>"
                                           value="<?= $filters['year_min'] ?>" id="rangeMin"
                                           oninput="syncInputs('range')">
                                    <input type="range" min="<?= $minYearDb ?>" max="<?= $currentYear ?>"
                                           value="<?= $filters['year_max'] ?>" id="rangeMax"
                                           oninput="syncInputs('range')">
                                </div>

                                <div class="d-flex justify-content-between mt-2 align-items-center">
                                    <input type="text" class="year-input" name="year_min" id="numMin"
                                           value="<?= $filters['year_min'] ?>">
                                    <span class="text-muted mx-1">-</span>
                                    <input type="text" class="year-input" name="year_max" id="numMax"
                                           value="<?= $filters['year_max'] ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Categoria</label>
                                <select class="form-select form-select-sm" name="genre">
                                    <option value="">Tutte</option>
                                    <?php foreach ($genres as $g): ?>
                                        <option value="<?= $g['id'] ?>" <?= $filters['genre'] == $g['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Rating Minimo</label>
                                <select class="form-select form-select-sm" name="rating">
                                    <option value="">Qualsiasi</option>
                                    <option value="4" <?= $filters['rating'] == '4' ? 'selected' : '' ?>>4+ Stelle
                                    </option>
                                    <option value="3" <?= $filters['rating'] == '3' ? 'selected' : '' ?>>3+ Stelle
                                    </option>
                                    <option value="2" <?= $filters['rating'] == '2' ? 'selected' : '' ?>>2+ Stelle
                                    </option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Condizione
                                    Copia</label>
                                <select class="form-select form-select-sm" name="condition">
                                    <option value="">Qualsiasi</option>
                                    <option value="BUONO" <?= $filters['condition'] == 'BUONO' ? 'selected' : '' ?>>
                                        Buono
                                    </option>
                                    <option value="DANNEGGIATO" <?= $filters['condition'] == 'DANNEGGIATO' ? 'selected' : '' ?>>
                                        Danneggiato
                                    </option>
                                </select>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-sm fw-bold">Applica Filtri</button>
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
                                if ($key != 'q' && $val) echo "<input type='hidden' name='$key' value='$val'>";
                            endforeach; ?>

                            <input type="text" name="q" class="form-control" placeholder="Cerca titolo, autore, ISBN..."
                                   value="<?= htmlspecialchars($filters['q']) ?>">
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

                                        <?php if ($b['rating_medio'] > 0): ?>
                                            <div class="position-absolute bottom-0 start-0 p-2">
                                    <span class="badge bg-warning text-dark border border-warning shadow-sm">
                                        <i class="fas fa-star text-white"></i> <?= number_format($b['rating_medio'], 1) ?>
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
                                        <p class="text-muted small mb-2 text-truncate">
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
                        $qParams = $_GET;
                        unset($qParams['page']); // Rimuovo pagina corrente per ricostruire il link
                        $baseLink = '?' . http_build_query($qParams) . '&page=';

                        $maxVisible = 5;
                        $start = max(1, $page - floor($maxVisible / 2));
                        $end = min($totalPages, $start + $maxVisible - 1);
                        if ($end - $start + 1 < $maxVisible) {
                            $start = max(1, $end - $maxVisible + 1);
                        }
                        ?>
                        <nav class="mt-5">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $baseLink . ($page - 1) ?>"><i
                                                class="fas fa-chevron-left"></i></a>
                                </li>

                                <?php if ($start > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= $baseLink ?>1">1</a></li>
                                    <?php if ($start > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseLink . $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link"
                                                             href="<?= $baseLink . $totalPages ?>"><?= $totalPages ?></a>
                                    </li>
                                <?php endif; ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $baseLink . ($page + 1) ?>"><i
                                                class="fas fa-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Selettori DOM
        const rangeMin = document.getElementById('rangeMin');
        const rangeMax = document.getElementById('rangeMax');
        const numMin = document.getElementById('numMin');
        const numMax = document.getElementById('numMax');
        const track = document.getElementById('sliderRange');
        const form = document.getElementById('filterForm');

        const minYear = <?= $minYearDb ?>;
        const maxYear = <?= $currentYear ?>;

        /**
         * Aggiorna la grafica dello slider e i valori degli input testuali
         * Chiamato quando si muove lo slider
         */
        function syncInputs(source) {
            let val1 = parseInt(rangeMin.value);
            let val2 = parseInt(rangeMax.value);

            // Min non può superare Max
            if (val1 > val2) {
                let tmp = val1;
                val1 = val2;
                val2 = tmp;
            }

            // Aggiorna gli input testuali solo se l'input è lo slider
            // Se l'input è manuale, non sovrascriviamo mentre l'utente scrive (gestito separatamente)
            if (source === 'range') {
                numMin.value = val1;
                numMax.value = val2;
            }

            updateTrack(val1, val2);
        }

        function updateTrack(val1, val2) {
            const percent1 = ((val1 - minYear) / (maxYear - minYear)) * 100;
            const percent2 = ((val2 - minYear) / (maxYear - minYear)) * 100;

            track.style.left = percent1 + "%";
            track.style.width = (percent2 - percent1) + "%";
        }

        /**
         * Valida l'input testuale e aggiorna lo slider
         * @param {HTMLInputElement} input - L'input modificato
         * @param {boolean} submit - Se true, invia il form dopo la validazione
         */
        function validateAndSync(input, submit = false) {
            let val = parseInt(input.value);

            // Se non è un numero, ripristina dal range corrispondente
            if (isNaN(val)) {
                input.value = (input === numMin) ? rangeMin.value : rangeMax.value;
                return;
            }

            // Clamp tra min e max globali
            if (val < minYear) val = minYear;
            if (val > maxYear) val = maxYear;

            // Aggiorna il valore nell'input
            input.value = val;

            // Aggiorna i range dello slider
            if (input === numMin) {
                let currentMax = parseInt(rangeMax.value);
                if (val > currentMax) {
                    // Limitiamo min a max
                    val = currentMax;
                    input.value = val;
                }
                rangeMin.value = val;
            } else {
                let currentMin = parseInt(rangeMin.value);
                if (val < currentMin) {
                    // Se max è minore di min, limitiamo max a min
                    val = currentMin;
                    input.value = val;
                }
                rangeMax.value = val;
            }

            // Aggiorna la grafica
            let v1 = parseInt(rangeMin.value);
            let v2 = parseInt(rangeMax.value);
            // Ordina per sicurezza grafica
            if (v1 > v2) {
                let t = v1;
                v1 = v2;
                v2 = t;
            }
            updateTrack(v1, v2);

            if (submit) {
                form.submit();
            }
        }

        /**
         * Resetta lo slider ai valori di default
         */
        function resetSlider() {
            rangeMin.value = minYear;
            rangeMax.value = maxYear;
            numMin.value = minYear;
            numMax.value = maxYear;
            updateTrack(minYear, maxYear);
        }

        // Inizializzazione al caricamento
        document.addEventListener('DOMContentLoaded', () => {
            // Inizializza grafica
            let v1 = parseInt(rangeMin.value);
            let v2 = parseInt(rangeMax.value);
            if (v1 > v2) {
                let t = v1;
                v1 = v2;
                v2 = t;
            }
            updateTrack(v1, v2);

            // Gestione Input Testuali
            [numMin, numMax].forEach(input => {
                // Al blur (perdita focus), valida e aggiorna slider
                input.addEventListener('blur', () => {
                    validateAndSync(input, false);
                });

                // Al tasto Invio, valida, aggiorna e invia form
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        validateAndSync(input, true);
                    }
                });
            });
        });
    </script>

<?php require_once __DIR__ . '/../src/Views/layout/footer.php'; ?>