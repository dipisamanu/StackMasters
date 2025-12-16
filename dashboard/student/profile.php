<?php
/**
 * Pagina Profilo Utente
 * File: dashboard/student/profile.php
 */

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();

// Recupera dati utente
$stmt = $db->prepare("
    SELECT 
        u.*,
        r.nome as ruolo_principale,
        r.durata_prestito,
        r.limite_prestiti,
        COALESCE(rf.rfid, 'Non assegnato') as rfid_code
    FROM Utenti u
    LEFT JOIN Utenti_Ruoli ur ON u.id_utente = ur.id_utente
    LEFT JOIN Ruoli r ON ur.id_ruolo = r.id_ruolo
    LEFT JOIN RFID rf ON u.id_rfid = rf.id_rfid
    WHERE u.id_utente = ?
    ORDER BY r.priorita ASC
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Statistiche prestiti
$stmtStats = $db->prepare("
    SELECT 
        COUNT(*) as totale_prestiti,
        SUM(CASE WHEN data_restituzione IS NULL THEN 1 ELSE 0 END) as prestiti_attivi,
        SUM(CASE WHEN data_restituzione IS NOT NULL THEN 1 ELSE 0 END) as prestiti_completati
    FROM Prestiti
    WHERE id_utente = ?
");
$stmtStats->execute([$userId]);
$stats = $stmtStats->fetch();

// Badge ottenuti
$stmtBadges = $db->prepare("
    SELECT b.nome, b.descrizione, b.icona_url, ub.data_conseguimento
    FROM Utenti_Badge ub
    JOIN Badge b ON ub.id_badge = b.id_badge
    WHERE ub.id_utente = ?
    ORDER BY ub.data_conseguimento DESC
");
$stmtBadges->execute([$userId]);
$badges = $stmtBadges->fetchAll();

// Flash message
$flash = Session::getFlash();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il Mio Profilo - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
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

        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .logout-btn:hover {
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
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>👤 Il Mio Profilo</h1>
        <a href="../../public/logout.php" class="logout-btn">Logout</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Informazioni Personali -->
        <div class="card">
            <h2>📋 Informazioni Personali</h2>

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

            <div class="actions">
                <a href="edit-profile.php" class="btn btn-primary">✏️ Modifica Profilo</a>
            </div>
        </div>

        <!-- Account -->
        <div class="card">
            <h2>🔐 Account</h2>

            <div class="info-row">
                <div class="info-label">Ruolo:</div>
                <div class="info-value"><?= htmlspecialchars($user['ruolo_principale']) ?></div>
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

            <div class="actions">
                <a href="generate-card.php" class="btn btn-primary">🎫 Scarica Tessera</a>
                <a href="export-data.php" class="btn btn-secondary">📦 Esporta Dati</a>
            </div>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="card">
        <h2>📊 Le Mie Statistiche</h2>

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
            <h2>🏆 I Miei Badge</h2>

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

    <!-- Zona Pericolosa -->
    <div class="card" style="border: 2px solid #dc3545;">
        <h2>⚠️ Zona Pericolosa</h2>
        <p style="color: #666; margin-bottom: 15px;">
            Le seguenti azioni sono irreversibili. Procedi con cautela.
        </p>
        <div class="actions">
            <a href="delete-account.php" class="btn btn-danger"
               onclick="return confirm('Sei sicuro di voler eliminare il tuo account? Questa azione è irreversibile!')">
                🗑️ Elimina Account
            </a>
        </div>
    </div>
</div>
</body>
</html>