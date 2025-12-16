<?php
/**
 * Export Dati Utente in JSON (GDPR Compliance)
 * File: dashboard/student/export-data.php
 */

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();

try {
    // 1. Dati Personali
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
        FROM Utenti
        WHERE id_utente = ?
    ");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch();

    // 2. Ruoli
    $stmtRuoli = $db->prepare("
        SELECT r.nome, r.durata_prestito, r.limite_prestiti, ur.prestiti_tot, ur.streak_restituzioni
        FROM Utenti_Ruoli ur
        JOIN Ruoli r ON ur.id_ruolo = r.id_ruolo
        WHERE ur.id_utente = ?
    ");
    $stmtRuoli->execute([$userId]);
    $ruoli = $stmtRuoli->fetchAll();

    // 3. Badge
    $stmtBadge = $db->prepare("
        SELECT b.nome, b.descrizione, ub.data_conseguimento
        FROM Utenti_Badge ub
        JOIN Badge b ON ub.id_badge = b.id_badge
        WHERE ub.id_utente = ?
    ");
    $stmtBadge->execute([$userId]);
    $badges = $stmtBadge->fetchAll();

    // 4. Prestiti
    $stmtPrestiti = $db->prepare("
        SELECT 
            p.id_prestito,
            l.titolo as libro,
            l.isbn,
            p.data_prestito,
            p.scadenza_prestito,
            p.data_restituzione,
            i.collocazione
        FROM Prestiti p
        JOIN Inventari i ON p.id_inventario = i.id_inventario
        JOIN Libri l ON i.id_libro = l.id_libro
        WHERE p.id_utente = ?
        ORDER BY p.data_prestito DESC
    ");
    $stmtPrestiti->execute([$userId]);
    $prestiti = $stmtPrestiti->fetchAll();

    // 5. Prenotazioni
    $stmtPrenotazioni = $db->prepare("
        SELECT 
            pr.id_prenotazione,
            l.titolo as libro,
            l.isbn,
            pr.data_richiesta,
            pr.data_disponibilita,
            pr.scadenza_ritiro
        FROM Prenotazioni pr
        JOIN Libri l ON pr.id_libro = l.id_libro
        WHERE pr.id_utente = ?
        ORDER BY pr.data_richiesta DESC
    ");
    $stmtPrenotazioni->execute([$userId]);
    $prenotazioni = $stmtPrenotazioni->fetchAll();

    // 6. Multe
    $stmtMulte = $db->prepare("
        SELECT 
            id_multa,
            giorni,
            importo,
            causa,
            commento,
            data_creazione,
            data_pagamento
        FROM Multe
        WHERE id_utente = ?
        ORDER BY data_creazione DESC
    ");
    $stmtMulte->execute([$userId]);
    $multe = $stmtMulte->fetchAll();

    // 7. Recensioni
    $stmtRecensioni = $db->prepare("
        SELECT 
            l.titolo as libro,
            l.isbn,
            r.voto,
            r.descrizione,
            r.data_creazione
        FROM Recensioni r
        JOIN Libri l ON r.id_libro = l.id_libro
        WHERE r.id_utente = ?
        ORDER BY r.data_creazione DESC
    ");
    $stmtRecensioni->execute([$userId]);
    $recensioni = $stmtRecensioni->fetchAll();

    // 8. Log Audit (ultimi 100)
    $stmtLogs = $db->prepare("
        SELECT 
            azione,
            dettagli,
            INET_NTOA(ip_address) as ip_address,
            timestamp
        FROM Logs_Audit
        WHERE id_utente = ?
        ORDER BY timestamp DESC
        LIMIT 100
    ");
    $stmtLogs->execute([$userId]);
    $logs = $stmtLogs->fetchAll();

    // 9. Notifiche
    $stmtNotifiche = $db->prepare("
        SELECT 
            tipo,
            titolo,
            messaggio,
            letto,
            stato_email,
            data_creazione,
            data_invio_email
        FROM Notifiche_Web
        WHERE id_utente = ?
        ORDER BY data_creazione DESC
        LIMIT 50
    ");
    $stmtNotifiche->execute([$userId]);
    $notifiche = $stmtNotifiche->fetchAll();

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

    // Log export (AZIONE CORRETTA)
    $db->prepare("
        INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address)
        VALUES (?, 'EXPORT_DATI', 'Export dati personali richiesto', INET_ATON(?))
    ")->execute([$userId, $_SERVER['REMOTE_ADDR']]);

    // Output JSON con headers corretti
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="biblioteca_dati_utente_' . $userId . '_' . date('Y-m-d') . '.json"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    error_log("Errore export dati: " . $e->getMessage());

    // Mostra pagina di errore
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Errore Export</title>
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
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            h1 { color: #dc3545; margin-bottom: 20px; }
            p { color: #666; margin-bottom: 20px; }
            a {
                display: inline-block;
                padding: 12px 24px;
                background: #bf2121;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
            }
            a:hover { background: #931b1b; }
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