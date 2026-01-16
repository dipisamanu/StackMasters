<?php
/**
 * Home Page Applicativa (Dashboard Utente)
 * File: public/home.php
 */

require_once __DIR__ . '/../src/config/session.php';

if (!Session::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../src/Models/BookModel.php';

$bookModel = new BookModel();

// 1. Recupero Dati tramite Stored Procedures
// Novità (Ultimi inseriti)
$newArrivals = $bookModel->getHomeSection('NOVITA', 10);

// Trending (Algoritmo smart settimanale)
$trending = $bookModel->getHomeSection('TRENDING', 10);

// Top Charts (Più letti del mese)
$topMonth = $bookModel->getHomeSection('TOP_MESE', 5);

// Raccomandazioni (Solo se loggato)
$recommendations = [];
$userId = Session::getUserId();
if ($userId) {
    $recommendations = $bookModel->getPersonalRecommendations($userId, 8);
}

require_once __DIR__ . '/../src/Views/layout/header.php';
?>

    <style>
        :root {
            --card-width: 180px;
            --card-height: 280px;
            --gap: 20px;
        }

        /* Hero Section ridotta e moderna */
        .hero-mini {
            background: linear-gradient(135deg, #1a1a1a 0%, #3a0e0e 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        /* Horizontal Scroller Container */
        .books-scroller {
            display: flex;
            overflow-x: auto;
            gap: var(--gap);
            padding: 20px 5px 40px 5px; /* Padding bottom per l'ombra hover */
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x mandatory;
        }

        .books-scroller::-webkit-scrollbar {
            height: 6px;
        }

        .books-scroller::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .books-scroller::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 10px;
        }

        .books-scroller::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }

        /* Book Card */
        .book-card-mini {
            flex: 0 0 var(--card-width);
            width: var(--card-width);
            scroll-snap-align: start;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border: none;
            background: transparent;
        }

        .book-card-mini:hover {
            transform: translateY(-10px);
            z-index: 2;
        }

        .cover-wrapper {
            height: 260px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            transition: box-shadow 0.3s ease;
        }

        .book-card-mini:hover .cover-wrapper {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .book-cover {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .book-info {
            margin-top: 10px;
        }

        .book-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }

        .book-author {
            font-size: 0.85rem;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges */
        .trend-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ffc107;
            color: #000;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .score-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            backdrop-filter: blur(4px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .section-title {
            font-weight: 800;
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 0;
        }

        .see-all {
            font-size: 0.9rem;
            text-decoration: none;
            color: var(--primary-red, #dc3545);
            font-weight: 600;
        }

        /* Top List Style */
        .top-list-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .top-list-item:hover {
            transform: translateX(5px);
            background: #fffcfc;
        }

        .rank-number {
            font-size: 3rem;
            font-weight: 900;
            color: #e0e0e0;
            margin-right: 20px;
            line-height: 1;
            width: 40px;
            text-align: center;
        }
    </style>

    <div class="hero-mini">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold display-5">Bentornato, <?= htmlspecialchars(Session::getNomeCompleto()) ?></h1>
                    <p class="lead opacity-75">Ecco le novità selezionate per te oggi.</p>
                </div>
                <div class="col-lg-4 d-none d-lg-block text-end opacity-50">
                    <i class="fas fa-book-reader fa-8x"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">

        <?php if (!empty($recommendations)): ?>
            <div class="mb-5">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-magic text-warning me-2"></i>Scelti per Te
                    </h2>
                    <span class="text-muted small">Basato sui tuoi generi preferiti</span>
                </div>

                <div class="books-scroller">
                    <?php foreach ($recommendations as $book): ?>
                        <?php renderBookCard($book, true); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($trending)): ?>
            <div class="mb-5">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-fire text-danger me-2"></i>Trending
                    </h2>
                    <a href="catalog.php?sort=popularity" class="see-all">Vedi classifica completa <i
                                class="fas fa-chevron-right ms-1"></i></a>
                </div>

                <div class="books-scroller">
                    <?php foreach ($trending as $book): ?>
                        <?php renderBookCard($book, false, true); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-5">
            <div class="col-lg-8">
                <div class="section-header">
                    <h2 class="section-title">Appena Arrivati</h2>
                    <a href="catalog.php?sort=date_desc" class="see-all">Tutto il catalogo <i
                                class="fas fa-chevron-right ms-1"></i></a>
                </div>

                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
                    <?php foreach (array_slice($newArrivals, 0, 8) as $book): ?>
                        <div class="col">
                            <?php renderBookCard($book); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="section-header">
                    <h2 class="section-title">Top del Mese</h2>
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php
                    $rank = 1;
                    foreach ($topMonth as $book):
                        $img = getCoverUrl($book['immagine_copertina']);
                        ?>
                        <a href="book.php?id=<?= $book['id_libro'] ?>" class="text-decoration-none">
                            <div class="top-list-item">
                                <div class="rank-number"><?= $rank++ ?></div>
                                <img src="<?= htmlspecialchars($img) ?>" alt="Cover"
                                     style="width: 50px; height: 75px; object-fit: cover; border-radius: 4px; margin-right: 15px;">
                                <div class="flex-grow-1 overflow-hidden">
                                    <h6 class="mb-1 text-dark fw-bold text-truncate"><?= htmlspecialchars($book['titolo']) ?></h6>
                                    <small class="text-muted d-block text-truncate"><?= htmlspecialchars($book['autori'] ?? 'AA.VV.') ?></small>
                                    <small class="text-primary fw-bold"><i
                                                class="fas fa-book-reader me-1"></i><?= $book['prestiti'] ?? 0 ?>
                                        prestiti</small>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>

                    <?php if (empty($topMonth)): ?>
                        <div class="alert alert-light text-center">Dati insufficienti per la classifica mensile.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

<?php
// --- Helper Functions interne alla pagina ---
function getCoverUrl($path)
{
    if (empty($path)) return 'assets/img/placeholder.png';
    // Se inizia con http è un URL esterno, altrimenti è locale
    return (str_starts_with($path, 'http')) ? $path : 'uploads/covers/' . $path;
}

function renderBookCard($book, $isRecommendation = false, $isTrending = false): void
{
    $img = getCoverUrl($book['immagine_copertina']);
    $url = "book.php?id=" . $book['id_libro'];
    ?>
    <div class="book-card-mini">
        <a href="<?= $url ?>" class="text-decoration-none">
            <div class="cover-wrapper">
                <img src="<?= htmlspecialchars($img) ?>" loading="lazy" class="book-cover"
                     alt="<?= htmlspecialchars($book['titolo']) ?>">

                <?php if ($isTrending): ?>
                    <div class="trend-badge"><i class="fas fa-bolt"></i> Hot</div>
                <?php endif; ?>

                <?php if ($isRecommendation && isset($book['genere_suggerito'])): ?>
                    <div class="score-badge">Perché ami: <?= htmlspecialchars($book['genere_suggerito']) ?></div>
                <?php endif; ?>
            </div>
            <div class="book-info">
                <div class="book-title" title="<?= htmlspecialchars($book['titolo']) ?>">
                    <?= htmlspecialchars($book['titolo']) ?>
                </div>
                <div class="book-author" title="<?= htmlspecialchars($book['autori'] ?? '') ?>">
                    <?= htmlspecialchars($book['autori'] ?? 'Autore sconosciuto') ?>
                </div>
            </div>
        </a>
    </div>
    <?php
}

require_once __DIR__ . '/../src/Views/layout/footer.php';
?>