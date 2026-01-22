<?php
/**
 * Dashboard Analytics Admin - Landing Page
 * File: dashboard/admin/index.php
 */

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireRole('Admin');

$db = Database::getInstance()->getConnection();
$nomeCompleto = Session::getNomeCompleto() ?? 'Amministratore';

$kpi = [
    'utenti' => 0,
    'libri' => 0,
    'prestiti_attivi' => 0,
    'multe_pendenti' => 0
];

try {
    $kpi['utenti'] = (int)$db->query('SELECT COUNT(*) FROM utenti')->fetchColumn();
    $kpi['libri'] = (int)$db->query('SELECT COUNT(*) FROM libri')->fetchColumn();
    $kpi['prestiti_attivi'] = (int)$db->query("SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL")->fetchColumn();
    $kpi['multe_pendenti'] = (float)$db->query("SELECT SUM(importo) FROM multe WHERE data_pagamento IS NULL")->fetchColumn();
} catch (Exception $e) {
    error_log($e->getMessage());
}

// DATI GRAFICO TREND PRESTITI (Ultimi 12 mesi)
$trendLabels = [];
$trendData = [];
try {
    $stmt = $db->query("
        SELECT DATE_FORMAT(data_prestito, '%Y-%m') as mese, COUNT(*) as totale
        FROM prestiti
        WHERE data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY mese
        ORDER BY mese
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $trendLabels[] = date('M Y', strtotime($row['mese'] . '-01'));
        $trendData[] = (int)$row['totale'];
    }
} catch (Exception $e) {
}

// GRAFICO A TORTA CATEGORIE
$catLabels = [];
$catData = [];
try {
    $stmt = $db->query("
        SELECT g.nome, COUNT(lg.id_libro) as totale
        FROM generi g
        JOIN libri_generi lg ON g.id = lg.id_genere
        GROUP BY g.id
        ORDER BY totale DESC
        LIMIT 5
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $catLabels[] = $row['nome'];
        $catData[] = (int)$row['totale'];
    }
} catch (Exception $e) {
}

// ULTIMI 10 PRESTITI
$lastLoans = [];
try {
    $stmt = $db->query("
        SELECT p.id_prestito, p.data_prestito, p.data_restituzione, p.scadenza_prestito, u.id_utente, u.nome, u.cognome, l.id_libro, l.titolo
        FROM prestiti p
        JOIN utenti u ON p.id_utente = u.id_utente
        JOIN inventari i ON p.id_inventario = i.id_inventario
        JOIN libri l ON i.id_libro = l.id_libro
        ORDER BY p.data_prestito DESC
        LIMIT 10
    ");
    $lastLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
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

        .card-kpi {
            border-left: 5px solid;
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

        .icon-box {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-box i {
            font-size: 1.5rem;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="dashboard-container py-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">Dashboard Analytics</h1>
                <p class="text-muted mb-0">Benvenuto, <?= htmlspecialchars($nomeCompleto) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="users.php" class="btn btn-dark shadow-sm"><i class="fas fa-users-cog me-2"></i>Gestione Utenti</a>
                <a href="fines.php" class="btn btn-danger shadow-sm"><i class="fas fa-file-invoice-dollar me-2"></i>Gestione
                    Multe</a>
                <a href="../../public/catalog.php" class="btn btn-outline-secondary shadow-sm"><i
                            class="fas fa-book me-2"></i>Catalogo</a>
            </div>
        </div>

        <!-- KPI -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card card-custom card-kpi border-primary h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Utenti Totali
                            </div>
                            <div class="h2 fw-bold text-dark mb-0 mt-1"><?= $kpi['utenti'] ?></div>
                        </div>
                        <div class="icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom card-kpi border-success h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Libri a Catalogo
                            </div>
                            <div class="h2 fw-bold text-dark mb-0 mt-1"><?= $kpi['libri'] ?></div>
                        </div>
                        <div class="icon-box bg-success bg-opacity-10 text-success">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom card-kpi border-warning h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Prestiti Attivi
                            </div>
                            <div class="h2 fw-bold text-dark mb-0 mt-1"><?= $kpi['prestiti_attivi'] ?></div>
                        </div>
                        <div class="icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom card-kpi border-danger h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Multe Pendenti
                            </div>
                            <div class="h2 fw-bold text-danger mb-0 mt-1">
                                € <?= number_format($kpi['multe_pendenti'], 2) ?></div>
                        </div>
                        <div class="icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h5 class="fw-bold mb-0 text-dark">Trend Prestiti (12 Mesi)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card card-custom h-100">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h5 class="fw-bold mb-0 text-dark">Top Categorie</h5>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div style="width: 100%; max-width: 280px;">
                            <canvas id="catChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card card-custom">
                    <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark">Ultimi Prestiti Registrati</h5>
                        <a href="loans_history.php" class="btn btn-sm btn-light text-muted">Vedi tutti</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-custom align-middle mb-0">
                            <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Data</th>
                                <th>Utente</th>
                                <th>Libro</th>
                                <th class="text-end pe-4">Stato</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($lastLoans)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">Nessun dato recente.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lastLoans as $loan): 
                                    $isReturned = !empty($loan['data_restituzione']);
                                    $isOverdue = !$isReturned && (strtotime($loan['scadenza_prestito']) < time());
                                ?>
                                    <tr style="cursor: pointer;"
                                        onclick="window.location='user_details.php?id=<?= $loan['id_utente'] ?>'">
                                        <td class="ps-4 text-muted small"><?= date('d/m/Y H:i', strtotime($loan['data_prestito'])) ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($loan['cognome'] . ' ' . $loan['nome']) ?></td>
                                        <td>
                                            <a href="../../public/book.php?id=<?= $loan['id_libro'] ?>"
                                               class="text-decoration-none text-dark hover-primary"
                                               onclick="event.stopPropagation();">
                                                <?= htmlspecialchars($loan['titolo']) ?>
                                            </a>
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
                </div>
            </div>
        </div>

    </div>

    <script>
        // Configurazione Grafico Trend
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'Prestiti',
                    data: <?= json_encode($trendData) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {legend: {display: false}},
                scales: {
                    y: {beginAtZero: true, grid: {borderDash: [2, 4]}},
                    x: {grid: {display: false}}
                }
            }
        });

        // Configurazione Grafico Categorie
        const ctxCat = document.getElementById('catChart').getContext('2d');
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    data: <?= json_encode($catData) ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {position: 'bottom', labels: {boxWidth: 12, font: {size: 11}}}
                },
                cutout: '70%'
            }
        });
    </script>

<?php require_once '../../src/Views/layout/footer.php'; ?>