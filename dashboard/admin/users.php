<?php
/**
 * Gestione Utenti e Ruoli (Ex Dashboard)
 * File: dashboard/admin/users.php
 */

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireRole('Admin');

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();
$nomeCompleto = Session::getNomeCompleto() ?? 'Amministratore';
$flash = Session::getFlash();

// --- 1. GESTIONE AGGIORNAMENTO RUOLO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_role') {
    $targetUser = (int)($_POST['user_id'] ?? 0);
    $targetRole = (int)($_POST['role_id'] ?? 0);

    try {
        if (!$targetUser || !$targetRole) {
            throw new RuntimeException('Dati mancanti per aggiornare il ruolo.');
        }

        $stmt = $db->prepare("SELECT id_ruolo, nome FROM ruoli WHERE id_ruolo = ?");
        $stmt->execute([$targetRole]);
        $roleRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) throw new RuntimeException('Ruolo selezionato non valido.');

        if ($targetUser === $userId && $roleRow['nome'] !== 'Admin') {
            if (Session::isAdmin()) {
                throw new RuntimeException('Non puoi rimuovere il ruolo Admin dal tuo account mentre sei connesso.');
            }
        }

        $db->beginTransaction();
        $db->prepare('DELETE FROM utenti_ruoli WHERE id_utente = ?')->execute([$targetUser]);
        $db->prepare('INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, ?)')->execute([$targetUser, $targetRole]);
        $db->commit();

        Session::setFlash('success', 'Ruolo aggiornato correttamente.');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        Session::setFlash('danger', 'Errore: ' . $e->getMessage());
    }

    header('Location: users.php');
    exit;
}

// --- 2. LOGICA DI RICERCA E PAGINAZIONE ---
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
        $conditions[] = "CONCAT(IFNULL(u.nome,''), ' ', IFNULL(u.cognome,''), ' ', IFNULL(u.email,''), ' ', IFNULL(u.cf,'')) LIKE $key";
        $params[$key] = "%$word%";
    }
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(' AND ', $conditions);
    }
}

$sql = "
    SELECT 
        u.id_utente, 
        u.nome, 
        u.cognome, 
        u.email,
        u.cf,
        GROUP_CONCAT(r.nome ORDER BY r.priorita ASC SEPARATOR '|') as ruoli_nomi,
        (
            SELECT r2.id_ruolo 
            FROM utenti_ruoli ur2 
            JOIN ruoli r2 ON ur2.id_ruolo = r2.id_ruolo 
            WHERE ur2.id_utente = u.id_utente 
            ORDER BY r2.priorita ASC 
            LIMIT 1
        ) as main_role_id,
        COUNT(*) OVER() as total_records
    FROM utenti u
    LEFT JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente
    LEFT JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
    $whereClause
    GROUP BY u.id_utente
    ORDER BY u.cognome ASC, u.nome ASC
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
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    error_log("Errore query utenti: " . $e->getMessage());
}

$totalRecords = !empty($users) ? $users[0]['total_records'] : 0;
$totalPages = ceil($totalRecords / $limit);
$roles = $db->query('SELECT id_ruolo, nome FROM ruoli ORDER BY priorita')->fetchAll(PDO::FETCH_ASSOC);

// FILTRO: Sposta l'utente corrente in cima
$currentUser = null;
$otherUsers = [];
foreach ($users as $u) {
    if ($u['id_utente'] == $userId) $currentUser = $u;
    else $otherUsers[] = $u;
}
$users = [];
if ($currentUser) $users[] = $currentUser;
$users = array_merge($users, $otherUsers);

require_once '../../src/Views/layout/header.php';
?>

    <style>
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; }
        .content-wrapper { flex: 1 0 auto; }
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

        .role-select {
            border-color: #dee2e6;
            background-color: #f8f9fa;
            font-size: 0.85rem;
            border-radius: 6px;
        }

        .role-select:focus {
            background-color: #fff;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
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
                <h1 class="h4 fw-bold text-dark mb-1"><i class="fas fa-users-cog me-2"></i>Gestione Utenti</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Utenti</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show shadow-sm border-0 border-start border-4 border-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
                <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card card-custom mb-4">
            <div class="card-header bg-white py-3 border-bottom-0 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-dark">Elenco Utenti</h5>
                    <span class="badge bg-light text-secondary border rounded-pill"><?= $totalRecords ?></span>
                </div>

                <form method="GET" action="users.php" class="d-flex gap-2 w-100 w-md-auto" style="max-width: 400px;">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Cerca utente..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary px-4" type="submit">Cerca</button>
                    </div>
                    <?php if($search): ?>
                        <a href="users.php" class="btn btn-light border" title="Reset filtri"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-custom align-middle mb-0">
                    <thead class="bg-light">
                    <tr>
                        <th class="ps-4" style="width: 40%;">Utente</th>
                        <th style="width: 25%;">Contatti</th>
                        <th style="width: 15%;">Ruoli</th>
                        <th class="text-end pe-4" style="width: 20%;">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-2x mb-3 opacity-25"></i><br>
                                Nessun utente trovato con questi criteri.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u):
                            $isCurrentUser = ($u['id_utente'] == $userId);
                            $userRoles = !empty($u['ruoli_nomi']) ? explode('|', $u['ruoli_nomi']) : [];
                            ?>
                            <tr class="<?= $isCurrentUser ? 'bg-warning bg-opacity-10' : '' ?>">
                                <td class="ps-4" style="cursor: pointer;" onclick="window.location='user_details.php?id=<?= $u['id_utente'] ?>'">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3 shadow-sm">
                                            <?= strtoupper(substr($u['nome'], 0, 1) . substr($u['cognome'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($u['cognome'] . ' ' . $u['nome']) ?>
                                                <?php if($isCurrentUser): ?>
                                                    <span class="badge bg-warning text-dark ms-2" style="font-size: 0.6rem;">TU</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small">ID: #<?= $u['id_utente'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="cursor: pointer;" onclick="window.location='user_details.php?id=<?= $u['id_utente'] ?>'">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="far fa-envelope text-muted small" style="width: 16px;"></i>
                                        <span class="text-dark small"><?= htmlspecialchars($u['email']) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="far fa-id-card text-muted small" style="width: 16px;"></i>
                                        <span class="text-muted small" style="font-family: monospace;"><?= htmlspecialchars($u['cf']) ?></span>
                                    </div>
                                </td>
                                <td style="cursor: pointer;" onclick="window.location='user_details.php?id=<?= $u['id_utente'] ?>'">
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (empty($userRoles)): ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary fw-normal border">Nessuno</span>
                                        <?php else: ?>
                                            <?php foreach ($userRoles as $r):
                                                $badgeClass = match($r) {
                                                    'Admin' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                                                    'Bibliotecario' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
                                                    'Docente' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25',
                                                    default => 'bg-light text-dark border'
                                                };
                                                ?>
                                                <span class="badge <?= $badgeClass ?> fw-medium"><?= htmlspecialchars($r) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($isCurrentUser): ?>
                                        <span class="text-muted small fst-italic"><i class="fas fa-lock me-1"></i>Protetto</span>
                                    <?php else: ?>
                                        <form class="d-flex justify-content-end align-items-center gap-2" method="POST" action="users.php">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id_utente'] ?>">
                                            <select name="role_id" class="form-select form-select-sm role-select py-1" style="width: 140px;" onclick="event.stopPropagation();">
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?= $role['id_ruolo'] ?>" <?= ($u['main_role_id'] == $role['id_ruolo']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($role['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-light border text-primary shadow-sm" title="Salva Modifiche" onclick="event.stopPropagation(); return confirm('Modificare il ruolo di questo utente?');">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
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