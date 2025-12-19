<?php
/**
 * Catalogo Pubblico - Grid View con Card Responsive
 * Issue 3.10: Frontend: Grid view catalogo pubblico
 * Issue 3.11: UX: Badge disponibilità
 */

require_once '../config/database.php';
require_once '../models/LibroManager.php';

$libroManager = new LibroManager();

// Gestione paginazione
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;

// Carica libri con disponibilità
$result = $libroManager->getAllLibri($page, $perPage);
$libri = $result['libri'];
$totalPages = $result['pages'];
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogo Biblioteca</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .search-bar {
            max-width: 600px;
            margin: 30px auto;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .search-bar button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #667eea;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            color: white;
            cursor: pointer;
            font-size: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            position: relative;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
        }

        .card-cover {
            width: 100%;
            height: 320px;
            object-fit: cover;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .card-body {
            padding: 20px;
        }

        .card-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
            line-height: 1.3;
            height: 2.6em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .card-author {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 12px;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #ecf0f1;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-disponibile {
            background: #d4edda;
            color: #155724;
        }

        .badge-prestito {
            background: #fff3cd;
            color: #856404;
        }

        .badge-non-disponibile {
            background: #f8d7da;
            color: #721c24;
        }

        .badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .badge-disponibile::before {
            background: #28a745;
        }

        .badge-prestito::before {
            background: #ffc107;
        }

        .badge-non-disponibile::before {
            background: #dc3545;
        }

        .copie-info {
            font-size: 0.85em;
            color: #95a5a6;
        }

        /* Paginazione */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .pagination .active {
            background: #667eea;
            color: white;
        }

        /* Placeholder copertina */
        .no-cover {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            color: #bdc3c7;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            header h1 {
                font-size: 2em;
            }

            .card-cover {
                height: 220px;
            }

            .card-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>📚 Catalogo Biblioteca</h1>
        <p>Esplora la nostra collezione di libri</p>
    </header>

    <div class="search-bar">
        <input
            type="text"
            placeholder="Cerca per titolo, autore, ISBN..."
            id="searchInput"
        >
        <button>🔍</button>
    </div>

    <div class="grid">
        <?php foreach ($libri as $libro): ?>
            <?php
            // Determina badge disponibilità (Issue 3.11)
            $copieDisponibili = (int)$libro['copie_disponibili'];
            $copieTotali = (int)$libro['copie_totali'];

            if ($copieDisponibili > 0) {
                $badgeClass = 'badge-disponibile';
                $badgeText = 'Disponibile';
            } elseif ($copieTotali > 0) {
                $badgeClass = 'badge-prestito';
                $badgeText = 'In prestito';
            } else {
                $badgeClass = 'badge-non-disponibile';
                $badgeText = 'Non disponibile';
            }
            ?>

            <div class="card" onclick="window.location.href='libro_dettaglio.php?id=<?= $libro['id_libro'] ?>'">
                <?php if ($libro['copertina_url']): ?>
                    <img
                        src="<?= htmlspecialchars($libro['copertina_url']) ?>"
                        alt="<?= htmlspecialchars($libro['titolo']) ?>"
                        class="card-cover"
                        onerror="this.parentElement.querySelector('.no-cover').style.display='flex'; this.style.display='none';"
                    >
                    <div class="card-cover no-cover" style="display: none;">📖</div>
                <?php else: ?>
                    <div class="card-cover no-cover">📖</div>
                <?php endif; ?>

                <div class="card-body">
                    <h3 class="card-title">
                        <?= htmlspecialchars($libro['titolo']) ?>
                    </h3>
                    <p class="card-author">
                        <?= htmlspecialchars($libro['autori'] ?: 'Autore Sconosciuto') ?>
                    </p>

                    <div class="card-footer">
                            <span class="badge <?= $badgeClass ?>">
                                <?= $badgeText ?>
                            </span>
                        <span class="copie-info">
                                <?= $copieDisponibili ?>/<?= $copieTotali ?> copie
                            </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginazione -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">← Precedente</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">Successivo →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Ricerca live (da implementare con AJAX)
    document.getElementById('searchInput').addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const cards = document.querySelectorAll('.card');

        cards.forEach(card => {
            const title = card.querySelector('.card-title').textContent.toLowerCase();
            const author = card.querySelector('.card-author').textContent.toLowerCase();

            if (title.includes(query) || author.includes(query)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
</script>
</body>
</html>