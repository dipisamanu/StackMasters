<?php
/**
 * Dettagli Utente Admin
 * File: dashboard/admin/user_details.php
 */

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireRole('Admin');

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$userId) {
    header('Location: users.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Recupera dati utente
$stmt = $db->prepare("
    SELECT u.*, 
           GROUP_CONCAT(r.nome SEPARATOR ', ') as ruoli_nomi
    FROM utenti u
    LEFT JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente
    LEFT JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
    WHERE u.id_utente = ?
    GROUP BY u.id_utente
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}

// Recupera prestiti attivi con dettagli copia
$stmtLoans = $db->prepare("
    SELECT p.*, l.titolo, l.id_libro, i.collocazione, i.id_inventario
    FROM prestiti p
    JOIN inventari i ON p.id_inventario = i.id_inventario
    JOIN libri l ON i.id_libro = l.id_libro
    WHERE p.id_utente = ? AND p.data_restituzione IS NULL
    ORDER BY p.scadenza_prestito
");
$stmtLoans->execute([$userId]);
$activeLoans = $stmtLoans->fetchAll(PDO::FETCH_ASSOC);

// Recupera storico prestiti (ultimi 5) con dettagli copia
$stmtHistory = $db->prepare("
    SELECT p.*, l.titolo, l.id_libro, i.id_inventario
    FROM prestiti p
    JOIN inventari i ON p.id_inventario = i.id_inventario
    JOIN libri l ON i.id_libro = l.id_libro
    WHERE p.id_utente = ? AND p.data_restituzione IS NOT NULL
    ORDER BY p.data_restituzione DESC
    LIMIT 5
");
$stmtHistory->execute([$userId]);
$historyLoans = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

require_once '../../src/Views/layout/header.php';
?>

<style>
    .dashboard-container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1.5rem;
    }
    .card-custom {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        background-color: #fff;
    }
    .avatar-lg {
        width: 80px;
        height: 80px;
        font-size: 2rem;
        background-color: #e9ecef;
        color: #495057;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold text-dark mb-0">Dettagli Utente</h1>
        <a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Torna alla lista</a>
    </div>

    <div class="row g-4">
        <!-- Colonna Sinistra: Anagrafica -->
        <div class="col-lg-4">
            <div class="card card-custom h-100">
                <div class="card-body text-center p-4">
                    <div class="avatar-lg mx-auto mb-3 shadow-sm">
                        <?= strtoupper(substr($user['nome'], 0, 1) . substr($user['cognome'], 0, 1)) ?>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></h4>
                    <p class="text-muted small mb-3">ID: #<?= $user['id_utente'] ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <?php 
                        $roles = explode(', ', $user['ruoli_nomi'] ?? '');
                        foreach($roles as $r): 
                            if(empty($r)) continue;
                            $bg = match(trim($r)) { 'Admin'=>'danger', 'Bibliotecario'=>'primary', default=>'secondary' };
                        ?>
                            <span class="badge bg-<?= $bg ?> bg-opacity-10 text-<?= $bg ?> border border-<?= $bg ?> border-opacity-25"><?= htmlspecialchars($r) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <div class="text-start mt-4">
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Email</label>
                            <div class="text-dark"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Codice Fiscale</label>
                            <div class="text-dark font-monospace"><?= htmlspecialchars($user['cf']) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Data di Nascita</label>
                            <div class="text-dark"><?= date('d/m/Y', strtotime($user['data_nascita'])) ?></div>
                        </div>
                        <div>
                            <label class="small text-muted text-uppercase fw-bold">Comune</label>
                            <div class="text-dark"><?= htmlspecialchars($user['comune_nascita']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonna Destra: Prestiti -->
        <div class="col-lg-8">
            <!-- Prestiti Attivi -->
            <div class="card card-custom mb-4">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="fw-bold mb-0 text-primary"><i class="fas fa-book-reader me-2"></i>Prestiti in Corso</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase text-muted">
                            <tr>
                                <th class="ps-4">Libro</th>
                                <th>Copia</th>
                                <th>Data Prestito</th>
                                <th>Scadenza</th>
                                <th class="text-end pe-4">Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($activeLoans)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Nessun prestito attivo.</td></tr>
                            <?php else: ?>
                                <?php foreach($activeLoans as $l): 
                                    $isOverdue = (strtotime($l['scadenza_prestito']) < time());
                                ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="../../public/book.php?id=<?= $l['id_libro'] ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($l['titolo']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">#<?= $l['id_inventario'] ?></span>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;"><?= htmlspecialchars($l['collocazione']) ?></small>
                                        </td>
                                        <td class="small text-muted"><?= date('d/m/Y', strtotime($l['data_prestito'])) ?></td>
                                        <td class="small fw-bold <?= $isOverdue ? 'text-danger' : 'text-dark' ?>">
                                            <?= date('d/m/Y', strtotime($l['scadenza_prestito'])) ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if($isOverdue): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger">Scaduto</span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">In corso</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Storico Recente -->
            <div class="card card-custom">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="fw-bold mb-0 text-secondary"><i class="fas fa-history me-2"></i>Storico Recente</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase text-muted">
                            <tr>
                                <th class="ps-4">Libro</th>
                                <th>Copia</th>
                                <th>Restituito il</th>
                                <th class="text-end pe-4">Esito</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($historyLoans)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">Nessuno storico disponibile.</td></tr>
                            <?php else: ?>
                                <?php foreach($historyLoans as $h): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="../../public/book.php?id=<?= $h['id_libro'] ?>" class="text-decoration-none text-muted">
                                                <?= htmlspecialchars($h['titolo']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border font-monospace">#<?= $h['id_inventario'] ?></span>
                                        </td>
                                        <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($h['data_restituzione'])) ?></td>
                                        <td class="text-end pe-4">
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Concluso</span>
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

<?php require_once '../../src/Views/layout/footer.php'; ?>