<?php
/**
 * Pagina Profilo Utente
 * File: dashboard/student/profile.php
 */

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();

$flash = Session::getFlash();

// Recupera dati utente
try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.nome as ruolo_principale,
            r.durata_prestito,
            r.limite_prestiti,
            COALESCE(rf.rfid, 'Non assegnato') as rfid_code
        FROM Utenti u
        LEFT JOIN Utenti_Ruoli ur ON u.id_utente = ur.id_utente AND ur.id_ruolo = (
            SELECT id_ruolo FROM Utenti_Ruoli WHERE id_utente = u.id_utente ORDER BY id_ruolo LIMIT 1
        )
        LEFT JOIN Ruoli r ON ur.id_ruolo = r.id_ruolo
        LEFT JOIN RFID rf ON u.id_rfid = rf.id_rfid
        WHERE u.id_utente = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Errore: Utente non trovato");
    }
} catch (Exception $e) {
    error_log("Errore recupero utente: " . $e->getMessage());
    die("Errore nel caricamento del profilo");
}

// Statistiche prestiti
try {
    $stmtStats = $db->prepare("
        SELECT 
            COUNT(*) as totale_prestiti,
            SUM(CASE WHEN data_restituzione IS NULL THEN 1 ELSE 0 END) as prestiti_attivi,
            SUM(CASE WHEN data_restituzione IS NOT NULL THEN 1 ELSE 0 END) as prestiti_completati
        FROM Prestiti
        WHERE id_utente = ?
    ");
    $stmtStats->execute([$userId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Errore statistiche: " . $e->getMessage());
    $stats = ['totale_prestiti' => 0, 'prestiti_attivi' => 0, 'prestiti_completati' => 0];
}

// Badge ottenuti
try {
    $stmtBadges = $db->prepare("
        SELECT b.nome, b.descrizione, b.icona_url, ub.data_conseguimento
        FROM Utenti_Badge ub
        JOIN Badge b ON ub.id_badge = b.id_badge
        WHERE ub.id_utente = ?
        ORDER BY ub.data_conseguimento DESC
    ");
    $stmtBadges->execute([$userId]);
    $badges = $stmtBadges->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Errore badge: " . $e->getMessage());
    $badges = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il Mio Profilo - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="../../public/assets/img/itisrossi.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1 {
            color: #333;
            font-size: 28px;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-header {
            padding: 10px 20px;
            color: white;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-dashboard {
            background: #bf2121;
        }

        .btn-dashboard:hover {
            background: #931b1b;
        }

        .btn-logout {
            background: #dc3545;
        }

        .btn-logout:hover {
            background: #c82333;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }

            .header-buttons {
                width: 100%;
                justify-content: center;
            }
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card h2 {
            color: #bf2121;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            width: 180px;
            flex-shrink: 0;
        }

        .info-value {
            color: #333;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #bf2121;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .badge-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: transform 0.2s;
        }

        .badge-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .badge-icon {
            font-size: 40px;
            margin-bottom: 8px;
        }

        .badge-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .badge-desc {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .badge-date {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #bf2121;
            color: white;
        }

        .btn-primary:hover {
            background: #931b1b;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .danger-zone {
            border: 2px solid #dc3545;
        }

        .danger-zone p {
            color: #666;
            margin-bottom: 15px;
        }

        .actions-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .actions-section h2 {
            color: #bf2121;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .action-primary {
            background: #bf2121;
        }

        .action-primary:hover {
            background: #931b1b;
        }

        .action-secondary {
            background: #0066cc;
        }

        .action-secondary:hover {
            background: #0052a3;
        }

        .action-danger {
            background: #dc3545;
        }

        .action-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-user-circle"></i> Il Mio Profilo</h1>
        <div class="header-buttons">
            <a href="index.php" class="btn-header btn-dashboard">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="../../public/logout.php" class="btn-header btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Informazioni Personali -->
        <div class="card">
            <h2><i class="fas fa-id-card"></i> Informazioni Personali</h2>

            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value"><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Codice Fiscale:</div>
                <div class="info-value"><?= htmlspecialchars($user['cf']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Data di Nascita:</div>
                <div class="info-value"><?= date('d/m/Y', strtotime($user['data_nascita'])) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Sesso:</div>
                <div class="info-value"><?= $user['sesso'] === 'M' ? 'Maschio' : 'Femmina' ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Comune di Nascita:</div>
                <div class="info-value"><?= htmlspecialchars($user['comune_nascita']) ?></div>
            </div>
        </div>

        <!-- Account -->
        <div class="card">
            <h2><i class="fas fa-cog"></i> Account</h2>

            <div class="info-row">
                <div class="info-label">Ruolo:</div>
                <div class="info-value"><?= htmlspecialchars($user['ruolo_principale'] ?? 'Non assegnato') ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Email Verificata:</div>
                <div class="info-value">
                    <?= $user['email_verificata'] ? '✅ Sì' : '❌ No' ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Codice RFID:</div>
                <div class="info-value"><?= htmlspecialchars($user['rfid_code']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Livello XP:</div>
                <div class="info-value"><?= $user['livello_xp'] ?> punti</div>
            </div>

            <div class="info-row">
                <div class="info-label">Membro dal:</div>
                <div class="info-value"><?= date('d/m/Y', strtotime($user['data_creazione'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="card">
        <h2><i class="fas fa-chart-bar"></i> Le Mie Statistiche</h2>

        <div class="stat-grid">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['totale_prestiti'] ?></div>
                <div class="stat-label">Prestiti Totali</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= $stats['prestiti_attivi'] ?></div>
                <div class="stat-label">Prestiti Attivi</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?= $stats['prestiti_completati'] ?></div>
                <div class="stat-label">Prestiti Completati</div>
            </div>
        </div>
    </div>

    <!-- Badge -->
    <?php if (count($badges) > 0): ?>
        <div class="card">
            <h2><i class="fas fa-trophy"></i> I Miei Badge</h2>

            <div class="badges-grid">
                <?php foreach ($badges as $badge): ?>
                    <div class="badge-item">
                        <div class="badge-icon">🏅</div>
                        <div class="badge-name"><?= htmlspecialchars($badge['nome']) ?></div>
                        <div class="badge-desc"><?= htmlspecialchars($badge['descrizione']) ?></div>
                        <div class="badge-date">
                            Ottenuto il <?= date('d/m/Y', strtotime($badge['data_conseguimento'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Azioni Profilo -->
    <div class="actions-section">
        <h2><i class="fas fa-sliders-h"></i> Gestione Profilo</h2>
        <div class="actions-grid">
            <a href="change-password.php" class="action-btn action-primary">
                <i class="fas fa-key"></i> 🔐 Cambio Password
            </a>
            <a href="edit-profile.php" class="action-btn action-primary">
                <i class="fas fa-edit"></i> ✏️ Modifica Profilo
            </a>
            <a href="generate-card.php" class="action-btn action-secondary">
                <i class="fas fa-id-card"></i> 🎫 Scarica Tessera
            </a>
            <a href="export-data.php" class="action-btn action-secondary">
                <i class="fas fa-download"></i> 📦 Esporta Dati
            </a>
        </div>
    </div>

    <!-- Zona Pericolosa -->
    <div class="actions-section danger-zone">
        <h2><i class="fas fa-exclamation-triangle"></i> Zona Pericolosa</h2>
        <p>Le seguenti azioni sono irreversibili. Procedi con cautela.</p>
        <div class="actions-grid">
            <a href="delete-account.php" class="action-btn action-danger" onclick="return confirm('Sei sicuro? Questa azione è IRREVERSIBILE!')">
                <i class="fas fa-trash"></i> 🗑️ Elimina Account
            </a>
        </div>
    </div>
</div>
</body>
</html>