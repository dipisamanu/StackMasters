<?php

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();

try {
    // Dati Personali
    $stmtUser = $db->prepare("
        SELECT 
            id_utente,
            cf,
            nome,
            cognome,
            email,
            data_nascita,
            sesso,
            comune_nascita,
            email_verificata,
            consenso_privacy,
            notifiche_attive,
            quiet_hours_start,
            quiet_hours_end,
            livello_xp,
            data_creazione,
            ultimo_aggiornamento
        FROM utenti
        WHERE id_utente = ?
    ");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // Ruoli
    $stmtRuoli = $db->prepare("
        SELECT r.nome, r.durata_prestito, r.limite_prestiti, ur.prestiti_tot, ur.streak_restituzioni
        FROM utenti_ruoli ur
        JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
        WHERE ur.id_utente = ?
    ");
    $stmtRuoli->execute([$userId]);
    $ruoli = $stmtRuoli->fetchAll(PDO::FETCH_ASSOC);

    // Badge
    $stmtBadge = $db->prepare("
        SELECT b.nome, b.descrizione, ub.data_conseguimento
        FROM utenti_badge ub
        JOIN badge b ON ub.id_badge = b.id_badge
        WHERE ub.id_utente = ?
    ");
    $stmtBadge->execute([$userId]);
    $badges = $stmtBadge->fetchAll(PDO::FETCH_ASSOC);

    // Prestiti
    $stmtPrestiti = $db->prepare("
        SELECT 
            p.id_prestito,
            l.titolo as libro,
            l.isbn,
            p.data_prestito,
            p.scadenza_prestito,
            p.data_restituzione,
            i.collocazione
        FROM prestiti p
        JOIN inventari i ON p.id_inventario = i.id_inventario
        JOIN libri l ON i.id_libro = l.id_libro
        WHERE p.id_utente = ?
        ORDER BY p.data_prestito DESC
    ");
    $stmtPrestiti->execute([$userId]);
    $prestiti = $stmtPrestiti->fetchAll(PDO::FETCH_ASSOC);

    // Prenotazioni
    $stmtPrenotazioni = $db->prepare("
        SELECT 
            pr.id_prenotazione,
            l.titolo as libro,
            l.isbn,
            pr.data_richiesta,
            pr.data_disponibilita,
            pr.scadenza_ritiro
        FROM prenotazioni pr
        JOIN libri l ON pr.id_libro = l.id_libro
        WHERE pr.id_utente = ?
        ORDER BY pr.data_richiesta DESC
    ");
    $stmtPrenotazioni->execute([$userId]);
    $prenotazioni = $stmtPrenotazioni->fetchAll(PDO::FETCH_ASSOC);

    // Multe
    $stmtMulte = $db->prepare("
        SELECT 
            id_multa,
            giorni,
            importo,
            causa,
            commento,
            data_creazione,
            data_pagamento
        FROM multe
        WHERE id_utente = ?
        ORDER BY data_creazione DESC
    ");
    $stmtMulte->execute([$userId]);
    $multe = $stmtMulte->fetchAll(PDO::FETCH_ASSOC);

    // Recensioni
    $stmtRecensioni = $db->prepare("
        SELECT 
            l.titolo as libro,
            l.isbn,
            r.voto,
            r.descrizione,
            r.data_creazione
        FROM recensioni r
        JOIN libri l ON r.id_libro = l.id_libro
        WHERE r.id_utente = ?
        ORDER BY r.data_creazione DESC
    ");
    $stmtRecensioni->execute([$userId]);
    $recensioni = $stmtRecensioni->fetchAll(PDO::FETCH_ASSOC);

    // Log Audit (ultimi 100)
    $stmtLogs = $db->prepare("
        SELECT 
            azione,
            dettagli,
            INET_NTOA(ip_address) as ip_address,
            timestamp
        FROM logs_audit
        WHERE id_utente = ?
        ORDER BY timestamp DESC
        LIMIT 100
    ");
    $stmtLogs->execute([$userId]);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Notifiche
    $stmtNotifiche = $db->prepare("
        SELECT 
            tipo,
            titolo,
            messaggio,
            letto,
            stato_email,
            data_creazione,
            data_invio_email
        FROM notifiche_web
        WHERE id_utente = ?
        ORDER BY data_creazione DESC
        LIMIT 50
    ");
    $stmtNotifiche->execute([$userId]);
    $notifiche = $stmtNotifiche->fetchAll(PDO::FETCH_ASSOC);

    // Costruisci array completo
    $exportData = [
        'metadata' => [
            'export_date' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'gdpr_compliance' => true,
            'data_format' => 'JSON',
            'version' => '1.0'
        ],
        'dati_personali' => $userData,
        'ruoli' => $ruoli,
        'badge' => $badges,
        'prestiti' => [
            'totale' => count($prestiti),
            'elenco' => $prestiti
        ],
        'prenotazioni' => [
            'totale' => count($prenotazioni),
            'elenco' => $prenotazioni
        ],
        'multe' => [
            'totale' => count($multe),
            'totale_importo' => array_sum(array_column($multe, 'importo')),
            'elenco' => $multe
        ],
        'recensioni' => [
            'totale' => count($recensioni),
            'elenco' => $recensioni
        ],
        'log_attivita' => [
            'totale' => count($logs),
            'ultimi_100' => $logs
        ],
        'notifiche' => [
            'totale' => count($notifiche),
            'ultime_50' => $notifiche
        ]
    ];

    // Log export
    try {
        $db->prepare("
            INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address)
            VALUES (?, 'EXPORT_DATI', 'Export dati personali richiesto', INET_ATON(?))
        ")->execute([$userId, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        error_log("Errore log audit: " . $e->getMessage());
    }

    // Output JSON con headers corretti
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="biblioteca_dati_utente_' . $userId . '_' . date('Y-m-d') . '.json"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    error_log("Errore export dati: " . $e->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Errore Export</title>
        <link rel="icon" href="../../public/assets/img/itisrossi.png">
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background: #f5f5f5;
                margin: 0;
                padding: 20px;
            }

            .error-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 500px;
            }

            h1 {
                color: #dc3545;
                margin-bottom: 20px;
            }

            p {
                color: #666;
                margin-bottom: 20px;
            }

            a {
                display: inline-block;
                padding: 12px 24px;
                background: #bf2121;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
            }

            a:hover {
                background: #931b1b;
            }
        </style>
    </head>
    <body>
    <div class="error-box">
        <h1>❌ Errore Export</h1>
        <p>Si è verificato un errore durante l'export dei tuoi dati.</p>
        <p>Riprova più tardi o contatta l'assistenza.</p>
        <a href="profile.php">← Torna al Profilo</a>
    </div>
    </body>
    </html>
    <?php
    exit;
}