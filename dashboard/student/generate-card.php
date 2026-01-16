<?php
/**
 * Generazione PDF Tessera Biblioteca
 * File: dashboard/student/generate-card.php
 */


require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();

try {
    // Recupera dati utente completi
    $stmt = $db->prepare("
        SELECT 
            u.id_utente,
            u.cf,
            u.nome,
            u.cognome,
            u.email,
            u.data_nascita,
            u.data_creazione,
            r.nome as ruolo,
            r.durata_prestito,
            r.limite_prestiti,
            COALESCE(rf.rfid, 'N/A') as rfid_code
        FROM utenti u
        LEFT JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente
        LEFT JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
        LEFT JOIN rfid rf ON u.id_rfid = rf.id_rfid
        WHERE u.id_utente = ?
        ORDER BY r.priorita ASC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Utente non trovato");
    }

    // Log generazione tessera
    try {
        $db->prepare("
            INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address)
            VALUES (?, 'GENERA_TESSERA', 'Tessera generata', INET_ATON(?))
        ")->execute([$userId, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        error_log("Errore log audit: " . $e->getMessage());
    }

    // Genera tessera HTML
    generateHTMLCard($user);

} catch (Exception $e) {
    error_log("Errore generazione tessera: " . $e->getMessage());
    die("Errore durante la generazione della tessera");
}

/**
 * Genera tessera in HTML (stampabile/salvabile come PDF)
 */
function generateHTMLCard($user) {
    // Genera QR code dell'utente
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" .
            urlencode($user['cf']);

    $dataIscrizione = date('d/m/Y', strtotime($user['data_creazione']));
    $validaFino = date('d/m/Y', strtotime('+1 year'));

    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Tessera Biblioteca - <?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></title>
        <link rel="icon" href="../../public/assets/img/itisrossi.png">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
                .card-container { box-shadow: none; }
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Arial', sans-serif;
                background: #f5f5f5;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }

            .card-container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                max-width: 900px;
            }

            .card {
                width: 85.6mm;
                height: 53.98mm;
                background: linear-gradient(135deg, #bf2121 0%, #8b1818 100%);
                border-radius: 10px;
                padding: 15px;
                color: white;
                position: relative;
                box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                overflow: hidden;
                margin: 0 auto 30px;
            }

            .card::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 8px;
                position: relative;
                z-index: 1;
            }

            .logo {
                font-size: 12px;
                font-weight: bold;
                line-height: 1.2;
            }

            .logo-icon {
                font-size: 20px;
            }

            .qr-code {
                width: 40px;
                height: 40px;
                background: white;
                padding: 2px;
                border-radius: 4px;
            }

            .qr-code img {
                width: 100%;
                height: 100%;
                display: block;
            }

            .card-body {
                position: relative;
                z-index: 1;
            }

            .user-name {
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 3px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .user-id {
                font-size: 10px;
                opacity: 0.9;
                margin-bottom: 8px;
            }

            .card-footer {
                display: flex;
                justify-content: space-between;
                font-size: 8px;
                opacity: 0.8;
                position: relative;
                z-index: 1;
            }

            .info-group {
                margin-top: 20px;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 8px;
            }

            .info-group h3 {
                color: #bf2121;
                margin-bottom: 15px;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .info-row {
                display: flex;
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }

            .info-row:last-child {
                border-bottom: none;
            }

            .info-label {
                font-weight: bold;
                width: 180px;
                color: #555;
            }

            .info-value {
                color: #333;
            }

            .actions {
                margin-top: 30px;
                display: flex;
                gap: 10px;
                justify-content: center;
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
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
        </style>
    </head>
    <body>
    <div class="card-container">
        <!-- Tessera fronte -->
        <div class="card">
            <div class="card-header">
                <div class="logo">
                    <div>BIBLIOTECA</div>
                    <div>ITIS ROSSI</div>
                </div>
                <div class="qr-code">
                    <img src="<?= $qrCodeUrl ?>" alt="QR Code">
                </div>
            </div>

            <div class="card-body">
                <div class="user-name">
                    <?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?>
                </div>
                <div class="user-id">
                    ID: <?= htmlspecialchars($user['id_utente']) ?> |
                    <?= htmlspecialchars($user['ruolo'] ?? 'Studente') ?>
                </div>
            </div>

            <div class="card-footer">
                <div>Iscritto: <?= $dataIscrizione ?></div>
                <div>Valida fino: <?= $validaFino ?></div>
            </div>
        </div>

        <!-- Informazioni dettagliate -->
        <div class="info-group">
            <h3><i class="fas fa-info-circle"></i> Dettagli Tessera</h3>

            <div class="info-row">
                <div class="info-label">Nome Completo:</div>
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
                <div class="info-label">Ruolo:</div>
                <div class="info-value"><?= htmlspecialchars($user['ruolo'] ?? 'Studente') ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Durata Prestito:</div>
                <div class="info-value"><?= $user['durata_prestito'] ?? '30' ?> giorni</div>
            </div>

            <div class="info-row">
                <div class="info-label">Limite Prestiti:</div>
                <div class="info-value"><?= $user['limite_prestiti'] ?? '3' ?> libri contemporaneamente</div>
            </div>

            <div class="info-row">
                <div class="info-label">Codice RFID:</div>
                <div class="info-value"><?= htmlspecialchars($user['rfid_code']) ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Data Iscrizione:</div>
                <div class="info-value"><?= $dataIscrizione ?></div>
            </div>
        </div>

        <!-- Azioni -->
        <div class="actions no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Stampa Tessera
            </button>
            <a href="profile.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Torna al Profilo
            </a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}
?>