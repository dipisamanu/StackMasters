<?php
/**
 * Dashboard Studente Unificata
 * File: dashboard/student/index.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireLogin();

// Controllo Ruoli
$mainRole = Session::getMainRole();
if ($mainRole === 'Admin') {
    header('Location: ../admin/index.php');
    exit;
} elseif ($mainRole === 'Bibliotecario') {
    header('Location: ../librarian/index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];
$nomeCompleto = Session::getNomeCompleto();

// 1. Recupera TUTTI i prestiti (Attivi e Storici)
$prestitiAttivi = [];
$storicoPrestiti = [];

try {
    $stmt = $db->prepare("
        SELECT 
            p.id_prestito,
            l.id_libro,
            l.titolo,
            l.immagine_copertina,
            l.autori_nomi as autore,
            p.data_prestito,
            p.scadenza_prestito,
            p.data_restituzione,
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
        WHERE p.id_utente = ?
        ORDER BY p.data_prestito DESC
    ");
    $stmt->execute([$userId]);
    $allLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allLoans as $loan) {
        if ($loan['data_restituzione'] === null) {
            $prestitiAttivi[] = $loan;
        } else {
            $storicoPrestiti[] = $loan;
        }
    }
} catch (Exception $e) { }

// 2. Recupera Prenotazioni Attive
$prenotazioni = [];
try {
    $stmtP = $db->prepare("
        SELECT 
            pr.id_prenotazione,
            l.titolo,
            l.id_libro,
            l.immagine_copertina,
            l.autori_nomi as autore, -- Assumendo che la vista o tabella abbia questo campo o lo aggiungi nella join
            pr.data_richiesta,
            pr.scadenza_ritiro,
            pr.copia_libro
        FROM prenotazioni pr
        JOIN (
            SELECT lb.*, GROUP_CONCAT(CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori_nomi
            FROM libri lb
            LEFT JOIN libri_autori la ON lb.id_libro = la.id_libro
            LEFT JOIN autori a ON la.id_autore = a.id
            GROUP BY lb.id_libro
        ) l ON pr.id_libro = l.id_libro
        WHERE pr.id_utente = ?
        ORDER BY pr.data_richiesta DESC
    ");
    $stmtP->execute([$userId]);
    $prenotazioni = $stmtP->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

// Statistiche
$countAttivi = count($prestitiAttivi);
$countStorico = count($storicoPrestiti);
$countPrenotazioni = count($prenotazioni);

require_once '../../src/Views/layout/header.php';
?>

    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-card-modern {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
            transition: transform 0.2s;
            height: 100%;
        }

        .stat-card-modern:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.08);
        }

        .book-thumb {
            width: 50px;
            height: 75px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.8em;
            border-radius: 50rem;
        }

        .nav-pills .nav-link {
            color: #495057;
            font-weight: 600;
            border-radius: 50rem;
            padding: 0.5rem 1.2rem;
        }
        .nav-pills .nav-link.active {
            background-color: #2c3e50;
            color: white;
        }

        .text-alert-danger { color: #dc3545; font-weight: bold; }
        .text-alert-warning { color: #fd7e14; font-weight: bold; }
        .text-alert-success { color: #198754; font-weight: bold; }
    </style>

    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div>
                    <h1 class="fw-bold mb-1">Ciao, <?= htmlspecialchars($nomeCompleto) ?>! 👋</h1>
                    <p class="mb-0 opacity-75">Ecco il riepilogo della tua attività di lettura.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="../../public/catalog.php" class="btn btn-light rounded-pill fw-bold text-dark shadow-sm">
                        <i class="fas fa-search me-2"></i>Nuovo Libro
                    </a>
                    <a href="profile.php" class="btn btn-outline-light rounded-pill fw-bold">
                        <i class="fas fa-user me-2"></i>Profilo
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card-modern border-start border-4 border-primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">In Lettura</h6>
                            <h2 class="fw-bold text-dark mb-0"><?= $countAttivi ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-2 rounded text-primary">
                            <i class="fas fa-book-reader fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-modern border-start border-4 border-warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Prenotazioni</h6>
                            <h2 class="fw-bold text-dark mb-0"><?= $countPrenotazioni ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-2 rounded text-warning">
                            <i class="fas fa-clock fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-modern border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Letti in totale</h6>
                            <h2 class="fw-bold text-dark mb-0"><?= $countStorico ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 p-2 rounded text-success">
                            <i class="fas fa-check-circle fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom p-4">
                <ul class="nav nav-pills card-header-pills" id="dashboardTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#attivi">
                            <i class="fas fa-book-open me-2"></i>In Corso (<?= $countAttivi ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#prenotazioni">
                            <i class="fas fa-bookmark me-2"></i>Prenotazioni (<?= $countPrenotazioni ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#storico">
                            <i class="fas fa-history me-2"></i>Storico
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-0">
                <div class="tab-content" id="dashboardTabContent">

                    <div class="tab-pane fade show active" id="attivi">
                        <?php if (empty($prestitiAttivi)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-couch fa-3x text-muted opacity-25 mb-3"></i>
                                <h5 class="text-muted">Nessun libro in lettura</h5>
                                <p class="small text-muted">Corri a sceglierne uno dal catalogo!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle table-hover mb-0">
                                    <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Libro</th>
                                        <th>Scadenza</th>
                                        <th>Stato</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($prestitiAttivi as $p):
                                        $giorni = (int)$p['giorni_rimanenti'];
                                        // Logica colori richiesta: Rosso < 0, Arancione <= 3, Verde altrimenti
                                        if ($giorni < 0) {
                                            $dateClass = 'text-alert-danger';
                                            $badgeClass = 'bg-danger';
                                            $statusText = "SCADUTO da " . abs($giorni) . "gg";
                                        } elseif ($giorni <= 3) {
                                            $dateClass = 'text-alert-warning';
                                            $badgeClass = 'bg-warning text-dark';
                                            $statusText = $giorni == 0 ? "Scade OGGI" : "Scade tra $giorni gg";
                                        } else {
                                            $dateClass = 'text-dark';
                                            $badgeClass = 'bg-success';
                                            $statusText = "In Prestito";
                                        }

                                        $img = !empty($p['immagine_copertina']) && str_starts_with($p['immagine_copertina'], 'http') ? $p['immagine_copertina'] : '../../public/uploads/covers/' . ($p['immagine_copertina'] ?? 'default.jpg');
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <a href="../../public/book.php?id=<?= $p['id_libro'] ?>">
                                                        <img src="<?= htmlspecialchars($img) ?>" class="book-thumb me-3" alt="Cover">
                                                    </a>
                                                    <div>
                                                        <a href="../../public/book.php?id=<?= $p['id_libro'] ?>" class="fw-bold text-dark text-decoration-none">
                                                            <?= htmlspecialchars($p['titolo']) ?>
                                                        </a>
                                                        <div class="small text-muted"><?= htmlspecialchars($p['autore']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="<?= $dateClass ?>">
                                                <?= date('d/m/Y', strtotime($p['scadenza_prestito'])) ?>
                                            </td>
                                            <td><span class="badge <?= $badgeClass ?> status-badge"><?= $statusText ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="prenotazioni">
                        <?php if (empty($prenotazioni)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-3x text-muted opacity-25 mb-3"></i>
                                <h5 class="text-muted">Nessuna prenotazione attiva</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle table-hover mb-0">
                                    <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Libro Richiesto</th>
                                        <th>Data Richiesta</th>
                                        <th>Stato</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($prenotazioni as $pr):
                                        $isReady = !empty($pr['copia_libro']);
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <a href="../../public/book.php?id=<?= $pr['id_libro'] ?>" class="text-decoration-none text-dark fw-bold">
                                                    <?= htmlspecialchars($pr['titolo']) ?>
                                                </a>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($pr['data_richiesta'])) ?></td>
                                            <td>
                                                <?php if ($isReady): ?>
                                                    <span class="badge bg-success status-badge animate__animated animate__pulse animate__infinite">
                                                    <i class="fas fa-check me-1"></i>PRONTO AL RITIRO
                                                </span>
                                                    <div class="small text-danger mt-1 fw-bold">Scade il <?= date('d/m', strtotime($pr['scadenza_ritiro'])) ?></div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary status-badge">In Coda</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="storico">
                        <?php if (empty($storicoPrestiti)): ?>
                            <div class="text-center py-5">
                                <p class="text-muted">Il tuo storico è ancora vuoto.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle table-hover mb-0">
                                    <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Libro</th>
                                        <th>Preso il</th>
                                        <th>Restituito il</th>
                                        <th></th> </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($storicoPrestiti as $s): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <a href="../../public/book.php?id=<?= $s['id_libro'] ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($s['titolo']) ?>
                                                </a>
                                            </td>
                                            <td class="text-muted"><?= date('d/m/Y', strtotime($s['data_prestito'])) ?></td>
                                            <td class="text-success fw-bold"><?= date('d/m/Y', strtotime($s['data_restituzione'])) ?></td>
                                            <td class="text-end pe-4">
                                                <a href="../../public/book.php?id=<?= $s['id_libro'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                                    Recensisci
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

    </div>

<?php require_once '../../src/Views/layout/footer.php'; ?>