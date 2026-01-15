<?php
/**
 * Dashboard Studente - Bottoni in Header
 * File: dashboard/student/index.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireLogin();

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];
$nomeCompleto = $_SESSION['nome_completo'] ?? 'Studente';

// Recupera prestiti attivi
$prestiti = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id_prestito,
            l.titolo,
            l.autori_nomi as autore,
            p.data_prestito,
            p.scadenza_prestito,
            DATEDIFF(p.scadenza_prestito, CURDATE()) as giorni_rimanenti
        FROM prestiti p
        JOIN inventari i ON p.id_inventario = i.id_inventario
        JOIN (
            SELECT lb.*, GROUP_CONCAT(CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi
            FROM libri lb
            LEFT JOIN libri_autori la ON lb.id_libro = la.id_libro
            LEFT JOIN autori a ON la.id_autore = a.id
            GROUP BY lb.id_libro
        ) l ON i.id_libro = l.id_libro
        WHERE p.id_utente = ? AND p.data_restituzione IS NULL
        ORDER BY p.scadenza_prestito ASC
    ");
    $stmt->execute([$userId]);
    $prestiti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

// Calcolo statistiche
$totale = count($prestiti);
$scaduti = count(array_filter($prestiti, fn($p) => $p['giorni_rimanenti'] < 0));
$inScadenza = count(array_filter($prestiti, fn($p) => $p['giorni_rimanenti'] >= 0 && $p['giorni_rimanenti'] <= 3));

require_once '../../src/Views/layout/header.php';
?>

    <div class="container py-5">

        <div class="mb-5 border-bottom pb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center gap-3">

                <div class="text-center text-lg-start">

                    <h1 class="fw-bold text-dark display-6 mb-0">Bentornato, <?= htmlspecialchars($nomeCompleto) ?></h1>
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="../../public/catalog.php" class="btn btn-danger rounded-pill px-4 shadow-sm fw-bold">
                        <i class="fas fa-search me-2"></i>Cerca Libro
                    </a>

                    <a href="generate-card.php" class="btn btn-dark rounded-pill px-4 shadow-sm fw-bold">
                        <i class="fas fa-qrcode me-2"></i>Tessera
                    </a>

                    <a href="profile.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm fw-bold">
                        <i class="fas fa-user-cog me-2"></i>Profilo
                    </a>
                </div>

            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                    <div class="card-body p-4">
                        <h6 class="text-muted small text-uppercase fw-bold">Libri in lettura</h6>
                        <h2 class="display-5 fw-bold text-primary mb-0"><?= $totale ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                    <div class="card-body p-4">
                        <h6 class="text-muted small text-uppercase fw-bold">In Scadenza</h6>
                        <h2 class="display-5 fw-bold text-warning mb-0"><?= $inScadenza ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                    <div class="card-body p-4">
                        <h6 class="text-muted small text-uppercase fw-bold">Scaduti</h6>
                        <h2 class="display-5 fw-bold text-danger mb-0"><?= $scaduti ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="fw-bold mb-4 text-secondary"><i class="fas fa-book-reader me-2"></i>I tuoi prestiti attivi</h4>

        <?php if (empty($prestiti)): ?>
            <div class="text-center py-5 bg-white rounded shadow-sm border border-light">
                <div class="mb-3 text-muted opacity-25"><i class="fas fa-book-open fa-4x"></i></div>
                <h5 class="fw-bold text-muted">Non hai libri in prestito</h5>
                <p class="text-muted small mb-3">È il momento perfetto per iniziare una nuova avventura.</p>
                <a href="../../public/catalog.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">
                    Vai al Catalogo
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($prestiti as $p):
                    $giorni = (int)$p['giorni_rimanenti'];
                    $statusClass = $giorni < 0 ? 'bg-danger text-white' : ($giorni <= 3 ? 'bg-warning text-dark' : 'bg-success text-white');
                    $statusText = $giorni < 0 ? "Scaduto da " . abs($giorni) . " gg" : ($giorni == 0 ? "Scade oggi" : "Scade tra $giorni gg");
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm transition-hover">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3 align-items-center">
                                    <span class="badge <?= $statusClass ?> rounded-pill px-3"><?= $statusText ?></span>
                                    <small class="text-muted fw-bold"><i class="far fa-calendar me-1"></i> <?= date('d/m', strtotime($p['data_prestito'])) ?></small>
                                </div>
                                <h5 class="card-title fw-bold text-truncate mb-1" title="<?= htmlspecialchars($p['titolo']) ?>">
                                    <?= htmlspecialchars($p['titolo']) ?>
                                </h5>
                                <p class="card-text text-muted small mb-4">
                                    <i class="fas fa-pen-nib me-1 opacity-50"></i> <?= htmlspecialchars($p['autore'] ?? 'Autore sconosciuto') ?>
                                </p>

                                <div class="d-grid">
                                    <div class="p-2 bg-light rounded text-center small text-muted border">
                                        <i class="fas fa-clock me-1 text-danger"></i> Scadenza: <strong><?= date('d/m/Y', strtotime($p['scadenza_prestito'])) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <style>
        /* Effetto hover leggero sulle card dei prestiti */
        .transition-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .transition-hover:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.08)!important; }
    </style>

<?php require_once '../../src/Views/layout/footer.php'; ?>