<?php
/**
 * Storico Prestiti Completo
 * File: dashboard/admin/loans_history.php
 */

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireRole('Admin');

$db = Database::getInstance()->getConnection();
$nomeCompleto = Session::getNomeCompleto() ?? 'Amministratore';

// --- LOGICA DI RICERCA E PAGINAZIONE ---
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');

$params = [];
$whereClause = "";

if (!empty($search)) {
    $keywords = array_filter(explode(' ', $search));
    $conditions = [];
    foreach ($keywords as $index => $word) {
        $key = ":word$index";
        $conditions[] = "CONCAT(IFNULL(u.nome,''), ' ', IFNULL(u.cognome,''), ' ', IFNULL(l.titolo,'')) LIKE $key";
        $params[$key] = "%$word%";
    }
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(' AND ', $conditions);
    }
}

// Query Prestiti con Window Function
$sql = "
    SELECT 
        p.id_prestito, 
        p.data_prestito, 
        p.data_restituzione,
        p.scadenza_prestito,
        u.id_utente, 
        u.nome, 
        u.cognome, 
        l.id_libro, 
        l.titolo,
        COUNT(*) OVER() as total_records
    FROM prestiti p
    JOIN utenti u ON p.id_utente = u.id_utente
    JOIN inventari i ON p.id_inventario = i.id_inventario
    JOIN libri l ON i.id_libro = l.id_libro
    $whereClause
    ORDER BY p.data_prestito DESC
    LIMIT :limit OFFSET :offset
";

try {
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $loans = [];
    error_log("Errore query prestiti: " . $e->getMessage());
}

$totalRecords = !empty($loans) ? $loans[0]['total_records'] : 0;
$totalPages = ceil($totalRecords / $limit);

require_once '../../src/Views/layout/header.php';
?>

<style>
    html, body { height: 100%; }
    body { display: flex; flex-direction: column; }
    footer { flex-shrink: 0; }

    .dashboard-container {
        width: 100%;
        max-width: 1440px;
        margin: 0 auto;
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
    .card-custom {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        background-color: #fff;
    }
    .table-custom th {
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #f0f0f0;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .table-custom td {
        vertical-align: middle;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }
    @media (min-width: 992px) {
        .dashboard-container { padding-left: 3rem; padding-right: 3rem; }
    }
</style>

<div class="dashboard-container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h4 fw-bold text-dark mb-1"><i class="fas fa-history me-2"></i>Storico Prestiti</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Prestiti</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard</a>
        </div>
    </div>

    <div class="card card-custom mb-4">
        <div class="card-header bg-white py-3 border-bottom-0 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark">Elenco Completo</h5>
                <span class="badge bg-light text-secondary border rounded-pill"><?= $totalRecords ?></span>
            </div>

            <form method="GET" action="loans_history.php" class="d-flex gap-2 w-100 w-md-auto" style="max-width: 400px;">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Cerca prestito..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary px-4" type="submit">Cerca</button>
                </div>
                <?php if($search): ?>
                    <a href="loans_history.php" class="btn btn-light border" title="Reset filtri"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-custom align-middle mb-0">
                <thead class="bg-light">
                <tr>
                    <th class="ps-4">Data Prestito</th>
                    <th>Utente</th>
                    <th>Libro</th>
                    <th>Scadenza</th>
                    <th>Restituzione</th>
                    <th class="text-end pe-4">Stato</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($loans)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">Nessun prestito trovato.</td></tr>
                <?php else: ?>
                    <?php foreach ($loans as $l): 
                        $isReturned = !empty($l['data_restituzione']);
                        $isOverdue = !$isReturned && (strtotime($l['scadenza_prestito']) < time());
                    ?>
                        <tr style="cursor: pointer;" onclick="window.location='user_details.php?id=<?= $l['id_utente'] ?>'">
                            <td class="ps-4 text-muted small"><?= date('d/m/Y H:i', strtotime($l['data_prestito'])) ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($l['cognome'] . ' ' . $l['nome']) ?></td>
                            <td>
                                <a href="../../public/book.php?id=<?= $l['id_libro'] ?>" class="text-decoration-none text-dark hover-primary" onclick="event.stopPropagation();">
                                    <?= htmlspecialchars($l['titolo']) ?>
                                </a>
                            </td>
                            <td class="text-muted small"><?= date('d/m/Y', strtotime($l['scadenza_prestito'])) ?></td>
                            <td class="text-muted small">
                                <?= $isReturned ? date('d/m/Y H:i', strtotime($l['data_restituzione'])) : '-' ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($isReturned): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Restituito</span>
                                <?php elseif ($isOverdue): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Scaduto</span>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Attivo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white py-3 border-top-0">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link border-0 bg-transparent" href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>"><i class="fas fa-chevron-left me-1"></i> Precedente</a>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link border-0 text-dark fw-bold px-3"><?= $page ?> / <?= $totalPages ?></span>
                        </li>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link border-0 bg-transparent" href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">Successivo <i class="fas fa-chevron-right ms-1"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../src/Views/layout/footer.php'; ?>