<?php
/**
 * Profilo Utente Unificato (Studenti, Admin, Staff)
 * File: dashboard/student/profile.php
 */

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/session.php';

Session::requireLogin();

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();
$flash = Session::getFlash();

$currentRole = Session::getMainRole();
$isAdmin = ($currentRole === 'Admin');

$dashboardLink = match ($currentRole) {
    'Admin' => '../admin/index.php',
    'Bibliotecario' => '../librarian/index.php',
    default => 'index.php'
};

// Recupero Dati Utente
try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.nome as ruolo_principale,
            COALESCE(rf.rfid, 'Non assegnato') as rfid_code
        FROM utenti u
        LEFT JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente AND ur.id_ruolo = (
            SELECT id_ruolo FROM utenti_ruoli WHERE id_utente = u.id_utente ORDER BY id_ruolo LIMIT 1
        )
        LEFT JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
        LEFT JOIN rfid rf ON u.id_rfid = rf.id_rfid
        WHERE u.id_utente = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) die("Errore: Utente non trovato");
} catch (Exception $e) {
    die("Errore sistema: " . $e->getMessage());
}

// Badge (Solo se non è admin, opzionale)
$badges = [];
if (!$isAdmin) {
    try {
        $stmtBadges = $db->prepare("
            SELECT b.nome, b.descrizione, b.icona_url, ub.data_conseguimento
            FROM utenti_badge ub
            JOIN badge b ON ub.id_badge = b.id_badge
            WHERE ub.id_utente = ?
            ORDER BY ub.data_conseguimento DESC
        ");
        $stmtBadges->execute([$userId]);
        $badges = $stmtBadges->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}

require_once '../../src/Views/layout/header.php';
?>

    <style>
        .profile-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .card-custom {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .card-header-custom {
            padding: 1rem 1.5rem 0.75rem;
            border-radius: 12px 12px 0 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 700;
            color: #495057;
            font-size: 0.95rem;
            width: 40%;
        }

        .info-value {
            font-weight: 400;
            color: #212529;
            text-align: right;
            width: 60%;
            font-size: 1rem;
        }

        .badge-item {
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
        }

        .badge-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .danger-zone {
            border: 2px solid #dc3545;
            background-color: #fff5f5;
        }

        .danger-zone .card-body {
            color: #842029;
        }
    </style>

    <div class="profile-container">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5 gap-3">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">Il Mio Profilo</h1>
                <p class="text-muted mb-0">Gestisci le tue informazioni e monitora le attività.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= $dashboardLink ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Dashboard
                </a>
                <a href="../../public/logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Esci
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show shadow-sm mb-4"
                 role="alert">
                <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card card-custom">
                    <div class="card-header-custom">
                        <h5 class="mb-0 fw-bold text-danger"><i class="fas fa-id-card me-2"></i>Anagrafica</h5>
                    </div>
                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="info-row">
                            <span class="info-label">Nome Completo</span>
                            <span class="info-value fs-5"><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Codice Fiscale</span>
                            <span class="info-value font-monospace"><?= htmlspecialchars($user['cf']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Data di Nascita</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($user['data_nascita'])) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Comune</span>
                            <span class="info-value"><?= htmlspecialchars($user['comune_nascita']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card card-custom">
                    <div class="card-header-custom">
                        <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-user-shield me-2"></i>Account</h5>
                    </div>
                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="info-row">
                            <span class="info-label">Ruolo</span>
                            <span class="info-value">
                            <span class="badge bg-dark px-3 py-2 fs-6"><?= htmlspecialchars($user['ruolo_principale']) ?></span>
                        </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Stato Email</span>
                            <span class="info-value">
                            <?php if ($user['email_verificata']): ?>
                                <span class="text-success fw-bold"><i
                                            class="fas fa-check-circle me-1"></i> Verificata</span>
                            <?php else: ?>
                                <span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Non Verificata</span>
                            <?php endif; ?>
                        </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">RFID</span>
                            <span class="info-value font-monospace text-muted"><?= htmlspecialchars($user['rfid_code']) ?></span>
                        </div>
                        <?php if (!$isAdmin): ?>
                            <div class="info-row">
                                <span class="info-label">Livello XP</span>
                                <span class="info-value text-warning fw-bold fs-5"><i
                                            class="fas fa-star me-1"></i><?= $user['livello_xp'] ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label">Membro dal</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($user['data_creazione'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($badges)): ?>
            <div class="card card-custom mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-warning mb-4 border-bottom pb-2"><i class="fas fa-trophy me-2"></i>I Miei
                        Traguardi</h5>
                    <div class="row g-3 row-cols-2 row-cols-md-4 row-cols-lg-6">
                        <?php foreach ($badges as $badge): ?>
                            <div class="col">
                                <div class="badge-item h-100">
                                    <div class="fs-1 mb-2">🏅</div>
                                    <div class="fw-bold text-dark small mb-1"><?= htmlspecialchars($badge['nome']) ?></div>
                                    <div class="text-muted"
                                         style="font-size: 0.7rem;"><?= date('d/m/y', strtotime($badge['data_conseguimento'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card card-custom mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold text-dark mb-4 border-bottom pb-2"><i class="fas fa-cogs me-2"></i>Impostazioni</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="change-password.php"
                           class="btn btn-primary w-100 py-3 fw-bold shadow-sm text-decoration-none d-flex align-items-center justify-content-center">
                            <i class="fas fa-key me-2"></i> Cambia Password
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="edit-profile.php"
                           class="btn btn-light border w-100 py-3 fw-bold text-dark text-decoration-none d-flex align-items-center justify-content-center">
                            <i class="fas fa-edit me-2"></i> Modifica Dati
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="generate-card.php"
                           class="btn btn-dark w-100 py-3 fw-bold shadow-sm text-white text-decoration-none d-flex align-items-center justify-content-center">
                            <i class="fas fa-id-card me-2"></i> Tessera Virtuale
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="export-data.php"
                           class="btn btn-light border w-100 py-3 fw-bold text-secondary text-decoration-none d-flex align-items-center justify-content-center">
                            <i class="fas fa-download me-2"></i> Esporta Dati
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$isAdmin): ?>
            <div class="card card-custom danger-zone">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-danger mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Zona
                            Pericolosa</h5>
                        <h6 class="mb-0 fw-bold">L'eliminazione dell'account è irreversibile e cancellerà tutti i tuoi
                            dati.</h6>
                    </div>
                    <a href="delete-account.php" class="btn btn-danger fw-bold px-4 py-2 shadow-sm text-decoration-none"
                       onclick="return confirm('Sei assolutamente sicuro? Questa azione non può essere annullata.')">
                        Elimina Account
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

<?php require_once '../../src/Views/layout/footer.php'; ?>