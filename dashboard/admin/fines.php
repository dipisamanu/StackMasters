<?php
/**
 * Gestione Multe - Admin
 * File: dashboard/admin/fines.php
 */

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireRole('Admin');

$db = Database::getInstance()->getConnection();

// FILTRI E ORDINAMENTO
$sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'ASC' : 'DESC';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterDate = $_GET['date_filter'] ?? '';

$query = "
    SELECT u.id_utente, u.nome, u.cognome, u.email, u.cf, SUM(m.importo) as totale_multe, MAX(m.data_creazione) as ultima_multa
    FROM utenti u
    JOIN multe m ON u.id_utente = m.id_utente
    WHERE m.data_pagamento IS NULL
";

$params = [];

if (!empty($search)) {
    $keywords = array_filter(explode(' ', $search));
    $conditions = [];
    foreach ($keywords as $index => $word) {
        $key = ":word$index";
        $conditions[] = "CONCAT(IFNULL(u.nome,''), ' ', IFNULL(u.cognome,''), ' ', IFNULL(u.email,''), ' ', IFNULL(u.cf,'')) LIKE $key";
        $params[$key] = "%$word%";
    }
    if (!empty($conditions)) {
        $query .= " AND (" . implode(' AND ', $conditions) . ")";
    }
}

$query .= " GROUP BY u.id_utente";

// Ordinamento personalizzato
if ($filterDate === 'recent') {
    $query .= " ORDER BY ultima_multa DESC";
} elseif ($filterDate === 'oldest') {
    $query .= " ORDER BY ultima_multa ASC";
} else {
    $query .= " ORDER BY totale_multe $sortOrder";
}

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $usersWithFines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $usersWithFines = [];
}

require_once '../../src/Views/layout/header.php';
?>

    <style>
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
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background-color: #fff;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            font-size: 0.95rem;
            font-weight: 600;
            background-color: #e9ecef;
            color: #495057;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
            .dashboard-container {
                padding-left: 3rem;
                padding-right: 3rem;
            }
        }
    </style>

    <div class="dashboard-container py-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h1 class="h4 fw-bold text-dark mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Gestione Multe</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Multe</li>
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
                    <h5 class="mb-0 fw-bold text-dark">Utenti con Multe Pendenti</h5>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill"><?= count($usersWithFines) ?></span>
                </div>

                <form method="GET" class="d-flex gap-2 w-100 w-md-auto" style="max-width: 900px;">
                    <div class="input-group" style="flex-grow: 1;">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Cerca utente..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <select class="form-select" name="sort" style="width: 180px; flex-shrink: 0;">
                        <option value="desc" <?= $sortOrder === 'DESC' && empty($filterDate) ? 'selected' : '' ?>>Importo Decrescente</option>
                        <option value="asc" <?= $sortOrder === 'ASC' && empty($filterDate) ? 'selected' : '' ?>>Importo Crescente</option>
                    </select>

                    <select class="form-select" name="date_filter" style="width: 150px; flex-shrink: 0;">
                        <option value="" <?= empty($filterDate) ? 'selected' : '' ?>>Data...</option>
                        <option value="recent" <?= $filterDate === 'recent' ? 'selected' : '' ?>>Più Recenti</option>
                        <option value="oldest" <?= $filterDate === 'oldest' ? 'selected' : '' ?>>Più Datate</option>
                    </select>

                    <button class="btn btn-primary px-4" type="submit">Filtra</button>
                    <?php if($search || $filterDate): ?>
                        <a href="fines.php" class="btn btn-light border" title="Reset filtri"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-custom align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4" style="width: 35%;">Utente</th>
                            <th style="width: 20%;">Contatti</th>
                            <th style="width: 15%;">Ultima Multa</th>
                            <th class="text-end" style="width: 15%;">Totale Multe</th>
                            <th class="text-end pe-4" style="width: 15%;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usersWithFines)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-3 text-success opacity-50"></i><br>
                                    Nessuna multa pendente trovata.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usersWithFines as $user): ?>
                                <tr style="cursor: pointer;" onclick="window.location='pay_fine.php?user_id=<?= $user['id_utente'] ?>'">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3 shadow-sm text-danger bg-danger bg-opacity-10">
                                                <?= strtoupper(substr($user['nome'], 0, 1) . substr($user['cognome'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($user['cognome'] . ' ' . $user['nome']) ?>
                                                </div>
                                                <div class="text-muted small">ID: #<?= $user['id_utente'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="far fa-envelope text-muted small" style="width: 16px;"></i>
                                            <span class="text-dark small"><?= htmlspecialchars($user['email']) ?></span>
                                        </div>
                                        <?php if(!empty($user['cf'])): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="far fa-id-card text-muted small" style="width: 16px;"></i>
                                            <span class="text-muted small" style="font-family: monospace;"><?= htmlspecialchars($user['cf']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y', strtotime($user['ultima_multa'])) ?>
                                    </td>
                                    <td class="text-end fw-bold text-danger fs-5">€ <?= number_format($user['totale_multe'], 2) ?></td>
                                    <td class="text-end pe-4">
                                        <a href="pay_fine.php?user_id=<?= $user['id_utente'] ?>" class="btn btn-sm btn-success shadow-sm" onclick="event.stopPropagation();">
                                            <i class="fas fa-hand-holding-usd me-1"></i> Gestisci
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php require_once '../../src/Views/layout/footer.php'; ?>