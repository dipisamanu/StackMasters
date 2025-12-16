<?php
/**
 * Cancellazione Account (GDPR Right to be Forgotten)
 * File: dashboard/student/delete-account.php
 */

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();
$step = $_GET['step'] ?? '1';

// Recupera info utente
$stmt = $db->prepare("
    SELECT nome, cognome, email, cf
    FROM Utenti
    WHERE id_utente = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Verifica prestiti attivi
$stmtPrestiti = $db->prepare("
    SELECT COUNT(*) as prestiti_attivi
    FROM Prestiti
    WHERE id_utente = ? AND data_restituzione IS NULL
");
$stmtPrestiti->execute([$userId]);
$prestitiAttivi = $stmtPrestiti->fetchColumn();

// Verifica multe non pagate
$stmtMulte = $db->prepare("
    SELECT COUNT(*) as multe_non_pagate, COALESCE(SUM(importo), 0) as importo_totale
    FROM Multe
    WHERE id_utente = ? AND data_pagamento IS NULL
");
$stmtMulte->execute([$userId]);
$multe = $stmtMulte->fetch();

// STEP 2: Conferma finale
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Token CSRF non valido');
        header('Location: delete-account.php');
        exit;
    }

    $password = $_POST['password'] ?? '';
    $confirmText = $_POST['confirm_text'] ?? '';

    $errors = [];

    // Verifica password
    $stmtPw = $db->prepare("SELECT password FROM Utenti WHERE id_utente = ?");
    $stmtPw->execute([$userId]);
    $hashedPassword = $stmtPw->fetchColumn();

    if (!password_verify($password, $hashedPassword)) {
        $errors[] = "Password non corretta";
    }

    // Verifica testo di conferma
    if (strtoupper($confirmText) !== 'ELIMINA') {
        $errors[] = "Testo di conferma non corretto";
    }

    // Verifica prerequisiti
    if ($prestitiAttivi > 0) {
        $errors[] = "Non puoi eliminare l'account con prestiti attivi";
    }

    if ($multe['multe_non_pagate'] > 0) {
        $errors[] = "Non puoi eliminare l'account con multe non pagate";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Log finale prima dell'eliminazione (AZIONE CORRETTA)
            $db->prepare("
                INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address)
                VALUES (?, 'CANCELLAZIONE_ACCOUNT', ?, INET_ATON(?))
            ")->execute([
                    $userId,
                    "Account eliminato dall'utente: " . $user['email'],
                    $_SERVER['REMOTE_ADDR']
            ]);

            // 1. Elimina badge
            $db->prepare("DELETE FROM Utenti_Badge WHERE id_utente = ?")->execute([$userId]);

            // 2. Elimina ruoli
            $db->prepare("DELETE FROM Utenti_Ruoli WHERE id_utente = ?")->execute([$userId]);

            // 3. Elimina recensioni (o anonimizzale)
            $db->prepare("DELETE FROM Recensioni WHERE id_utente = ?")->execute([$userId]);

            // 4. Elimina prenotazioni
            $db->prepare("DELETE FROM Prenotazioni WHERE id_utente = ?")->execute([$userId]);

            // 5. Elimina multe (già pagate)
            $db->prepare("DELETE FROM Multe WHERE id_utente = ? AND data_pagamento IS NOT NULL")->execute([$userId]);

            // 6. Elimina prestiti completati
            $db->prepare("DELETE FROM Prestiti WHERE id_utente = ?")->execute([$userId]);

            // 7. Elimina notifiche
            $db->prepare("DELETE FROM Notifiche_Web WHERE id_utente = ?")->execute([$userId]);

            // 8. Anonimizza log audit
            $db->prepare("UPDATE Logs_Audit SET id_utente = NULL WHERE id_utente = ?")->execute([$userId]);

            // 9. Elimina l'utente
            $db->prepare("DELETE FROM Utenti WHERE id_utente = ?")->execute([$userId]);

            $db->commit();

            // Logout e reindirizza
            Session::logout();

            // Mostra pagina di conferma
            ?>
            <!DOCTYPE html>
            <html lang="it">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Account Eliminato</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        padding: 20px;
                    }
                    .success-box {
                        background: white;
                        padding: 50px;
                        border-radius: 20px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        text-align: center;
                        max-width: 500px;
                    }
                    .icon {
                        font-size: 80px;
                        margin-bottom: 20px;
                    }
                    h1 {
                        color: #28a745;
                        margin-bottom: 15px;
                        font-size: 28px;
                    }
                    p {
                        color: #666;
                        line-height: 1.6;
                        margin-bottom: 30px;
                        font-size: 16px;
                    }
                    .btn {
                        display: inline-block;
                        padding: 15px 40px;
                        background: #bf2121;
                        color: white;
                        text-decoration: none;
                        border-radius: 8px;
                        font-weight: 600;
                        transition: all 0.3s ease;
                    }
                    .btn:hover {
                        background: #931b1b;
                        transform: translateY(-2px);
                    }
                </style>
            </head>
            <body>
            <div class="success-box">
                <div class="icon">✅</div>
                <h1>Account Eliminato</h1>
                <p>
                    Il tuo account è stato eliminato con successo.<br>
                    Tutti i tuoi dati sono stati rimossi dal sistema in conformità con il GDPR.
                </p>
                <p>
                    Grazie per aver utilizzato la Biblioteca ITIS Rossi.<br>
                    Saremo felici di rivederti in futuro!
                </p>
                <a href="../../public/login.php" class="btn">Vai alla Home</a>
            </div>
            </body>
            </html>
            <?php
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Errore cancellazione account: " . $e->getMessage());
            Session::setFlash('error', 'Errore durante la cancellazione. Riprova più tardi.');
            header('Location: delete-account.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elimina Account - Biblioteca ITIS Rossi</title>
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
            max-width: 700px;
            margin: 0 auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .danger-card {
            border: 3px solid #dc3545;
        }

        h1 {
            color: #dc3545;
            margin-bottom: 10px;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .warning-box h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .warning-box ul {
            color: #856404;
            margin-left: 20px;
        }

        .warning-box li {
            margin-bottom: 8px;
        }

        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .error-box h3 {
            color: #721c24;
            margin-bottom: 10px;
        }

        .error-box ul {
            color: #721c24;
            margin-left: 20px;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box p {
            color: #0c5460;
            line-height: 1.6;
        }

        .checklist {
            margin: 20px 0;
        }

        .checklist-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .checklist-item.blocked {
            background: #f8d7da;
            border: 2px solid #dc3545;
        }

        .checklist-item.ok {
            background: #d4edda;
            border: 2px solid #28a745;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #dc3545;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-danger:disabled {
            background: #ccc;
            cursor: not-allowed;
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
<div class="container">
    <?php if ($step === '1'): ?>
        <!-- STEP 1: Verifica Prerequisiti -->
        <div class="card danger-card">
            <h1>⚠️ Elimina Account</h1>
            <p style="color: #666; margin-bottom: 20px;">
                Stai per eliminare definitivamente il tuo account. Questa azione è irreversibile.
            </p>

            <div class="warning-box">
                <h3>⚠️ Attenzione!</h3>
                <p>L'eliminazione dell'account comporterà:</p>
                <ul>
                    <li>Cancellazione permanente di tutti i tuoi dati personali</li>
                    <li>Rimozione dello storico prestiti e prenotazioni</li>
                    <li>Perdita di tutti i badge ottenuti</li>
                    <li>Impossibilità di recuperare l'account</li>
                </ul>
            </div>

            <h3 style="margin-top: 30px; margin-bottom: 15px;">📋 Verifica Prerequisiti</h3>

            <div class="checklist">
                <div class="checklist-item <?= $prestitiAttivi === 0 ? 'ok' : 'blocked' ?>">
                    <div style="font-size: 24px;">
                        <?= $prestitiAttivi === 0 ? '✅' : '❌' ?>
                    </div>
                    <div>
                        <strong>Prestiti Attivi</strong><br>
                        <?= $prestitiAttivi === 0
                                ? 'Nessun prestito attivo'
                                : "Hai $prestitiAttivi prestiti attivi. Restituisci i libri prima di eliminare l'account."
                        ?>
                    </div>
                </div>

                <div class="checklist-item <?= $multe['multe_non_pagate'] === 0 ? 'ok' : 'blocked' ?>">
                    <div style="font-size: 24px;">
                        <?= $multe['multe_non_pagate'] === 0 ? '✅' : '❌' ?>
                    </div>
                    <div>
                        <strong>Multe Non Pagate</strong><br>
                        <?= $multe['multe_non_pagate'] === 0
                                ? 'Nessuna multa in sospeso'
                                : "Hai {$multe['multe_non_pagate']} multe non pagate (totale: €" . number_format($multe['importo_totale'], 2) . "). Salda le multe prima di procedere."
                        ?>
                    </div>
                </div>
            </div>

            <?php if ($prestitiAttivi > 0 || $multe['multe_non_pagate'] > 0): ?>
                <div class="error-box">
                    <h3>🚫 Impossibile Procedere</h3>
                    <p>Devi completare i seguenti passaggi prima di poter eliminare l'account:</p>
                    <ul>
                        <?php if ($prestitiAttivi > 0): ?>
                            <li>Restituisci tutti i libri in prestito</li>
                        <?php endif; ?>
                        <?php if ($multe['multe_non_pagate'] > 0): ?>
                            <li>Paga tutte le multe in sospeso</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <p><strong>💡 Suggerimento:</strong> Prima di eliminare l'account, puoi esportare tutti i tuoi dati personali utilizzando la funzione "Esporta Dati" dalla pagina del profilo.</p>
            </div>

            <div class="actions">
                <?php if ($prestitiAttivi === 0 && $multe['multe_non_pagate'] === 0): ?>
                    <a href="delete-account.php?step=2" class="btn btn-danger">
                        Continua con l'Eliminazione →
                    </a>
                <?php else: ?>
                    <button class="btn btn-danger" disabled>
                        Impossibile Procedere
                    </button>
                <?php endif; ?>
                <a href="profile.php" class="btn btn-secondary">← Annulla</a>
            </div>
        </div>

    <?php else: ?>
        <!-- STEP 2: Conferma Finale -->
        <div class="card danger-card">
            <h1>🔒 Conferma Eliminazione</h1>
            <p style="color: #666; margin-bottom: 20px;">
                Ultimo passaggio prima dell'eliminazione definitiva del tuo account.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <h3>Errori di Validazione</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="warning-box">
                    <h3>⚠️ Ultima Conferma</h3>
                    <p>Stai per eliminare l'account di:</p>
                    <ul>
                        <li><strong>Nome:</strong> <?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></li>
                        <li><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></li>
                        <li><strong>CF:</strong> <?= htmlspecialchars($user['cf']) ?></li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="password">Inserisci la tua password per confermare *</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Password del tuo account">
                </div>

                <div class="form-group">
                    <label for="confirm_text">
                        Digita "ELIMINA" per confermare *
                    </label>
                    <input type="text" id="confirm_text" name="confirm_text" required
                           placeholder="ELIMINA" autocomplete="off">
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-danger">
                        🗑️ Elimina Definitivamente
                    </button>
                    <a href="profile.php" class="btn btn-secondary">← Annulla</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>