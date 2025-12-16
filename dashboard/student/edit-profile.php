<?php
/**
 * Modifica Profilo Utente
 * File: dashboard/student/edit-profile.php
 */

session_start();

require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = getDB();
$userId = Session::getUserId();

// Recupera dati attuali
try {
    $stmt = $db->prepare("SELECT * FROM Utenti WHERE id_utente = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Utente non trovato");
    }
} catch (Exception $e) {
    error_log("Errore recupero utente: " . $e->getMessage());
    die("Errore nel caricamento dei dati");
}

$errors = [];

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF non valido';
    } else {
        // Campi modificabili
        $email = strtolower(trim($_POST['email'] ?? ''));
        $comune = trim($_POST['comune_nascita'] ?? '');
        $notificheAttive = isset($_POST['notifiche_attive']) ? 1 : 0;
        $quietStart = $_POST['quiet_hours_start'] ?? null;
        $quietEnd = $_POST['quiet_hours_end'] ?? null;

        // Validazione email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email non valida";
        } else {
            // Verifica se email già usata da altro utente
            try {
                $stmtCheck = $db->prepare("SELECT id_utente FROM Utenti WHERE email = ? AND id_utente != ?");
                $stmtCheck->execute([$email, $userId]);
                if ($stmtCheck->fetch()) {
                    $errors[] = "Email già in uso da un altro utente";
                }
            } catch (Exception $e) {
                error_log("Errore verifica email: " . $e->getMessage());
                $errors[] = "Errore durante la verifica dell'email";
            }
        }

        // Validazione comune
        if (empty($comune)) {
            $errors[] = "Il comune è obbligatorio";
        }

        if (empty($errors)) {
            try {
                $stmtUpdate = $db->prepare("
                    UPDATE Utenti 
                    SET email = ?, 
                        comune_nascita = ?, 
                        notifiche_attive = ?,
                        quiet_hours_start = ?,
                        quiet_hours_end = ?
                    WHERE id_utente = ?
                ");

                $stmtUpdate->execute([
                        $email,
                        $comune,
                        $notificheAttive,
                        $quietStart ?: null,
                        $quietEnd ?: null,
                        $userId
                ]);

                // Log modifica
                try {
                    $db->prepare("
                        INSERT INTO Logs_Audit (id_utente, azione, dettagli, ip_address)
                        VALUES (?, 'MODIFICA_PROFILO', 'Profilo aggiornato', INET_ATON(?))
                    ")->execute([$userId, $_SERVER['REMOTE_ADDR']]);
                } catch (Exception $e) {
                    error_log("Errore log audit: " . $e->getMessage());
                }

                $_SESSION['flash'] = [
                        'type' => 'success',
                        'message' => 'Profilo aggiornato con successo!'
                ];
                header('Location: profile.php');
                exit;

            } catch (Exception $e) {
                error_log("Errore aggiornamento profilo: " . $e->getMessage());
                $errors[] = "Errore durante l'aggiornamento. Riprova.";
            }
        }

        // Se ci sono errori, mantieni i dati inseriti
        if (!empty($errors)) {
            $user['email'] = $email;
            $user['comune_nascita'] = $comune;
            $user['notifiche_attive'] = $notificheAttive;
            $user['quiet_hours_start'] = $quietStart;
            $user['quiet_hours_end'] = $quietEnd;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Profilo - Biblioteca ITIS Rossi</title>
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
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert ul {
            margin: 10px 0 0 20px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h2 {
            color: #bf2121;
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
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

        input[type="email"],
        input[type="text"],
        input[type="time"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #bf2121;
            box-shadow: 0 0 0 3px rgba(191, 33, 33, 0.1);
        }

        input:disabled {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }

        .time-range {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .time-range .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .info-box p {
            color: #0c5460;
            font-size: 14px;
            line-height: 1.5;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(191, 33, 33, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .readonly-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .readonly-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .readonly-info p:last-child {
            margin-bottom: 0;
        }

        .readonly-info strong {
            color: #333;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1><i class="fas fa-edit"></i> Modifica Profilo</h1>
        <p class="subtitle">Aggiorna le tue informazioni personali</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Si sono verificati i seguenti errori:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <!-- Dati Personali (Read-only) -->
            <div class="form-section">
                <h2>📋 Dati Personali</h2>

                <div class="info-box">
                    <p><strong>ℹ️ Nota:</strong> I seguenti dati non possono essere modificati autonomamente. Per modifiche, contatta l'amministratore della biblioteca.</p>
                </div>

                <div class="readonly-info">
                    <p><strong>Nome:</strong> <?= htmlspecialchars($user['nome']) ?></p>
                    <p><strong>Cognome:</strong> <?= htmlspecialchars($user['cognome']) ?></p>
                    <p><strong>Codice Fiscale:</strong> <?= htmlspecialchars($user['cf']) ?></p>
                    <p><strong>Data di Nascita:</strong> <?= date('d/m/Y', strtotime($user['data_nascita'])) ?></p>
                    <p><strong>Sesso:</strong> <?= $user['sesso'] === 'M' ? 'Maschio' : 'Femmina' ?></p>
                </div>
            </div>

            <!-- Dati Modificabili -->
            <div class="form-section">
                <h2>🔧 Contatti e Residenza</h2>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($user['email']) ?>" required>
                    <div class="help-text">Assicurati che sia un'email valida e accessibile</div>
                </div>

                <div class="form-group">
                    <label for="comune_nascita">Comune di Nascita *</label>
                    <input type="text" id="comune_nascita" name="comune_nascita"
                           value="<?= htmlspecialchars($user['comune_nascita']) ?>" required>
                </div>
            </div>

            <!-- Preferenze Notifiche -->
            <div class="form-section">
                <h2>🔔 Preferenze Notifiche</h2>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="notifiche_attive" name="notifiche_attive"
                                <?= $user['notifiche_attive'] ? 'checked' : '' ?>>
                        <label for="notifiche_attive">Ricevi notifiche via email</label>
                    </div>
                    <div class="help-text">
                        Riceverai email per scadenze prestiti, prenotazioni disponibili, multe, ecc.
                    </div>
                </div>

                <div class="form-group">
                    <label>Orario "Non Disturbare" (opzionale)</label>
                    <div class="help-text" style="margin-bottom: 10px;">
                        Imposta un orario in cui non ricevere notifiche email
                    </div>
                    <div class="time-range">
                        <div class="form-group">
                            <label for="quiet_hours_start">Dalle</label>
                            <input type="time" id="quiet_hours_start" name="quiet_hours_start"
                                   value="<?= htmlspecialchars($user['quiet_hours_start'] ?? '') ?>">
                        </div>
                        <span style="padding-top: 30px;">→</span>
                        <div class="form-group">
                            <label for="quiet_hours_end">Alle</label>
                            <input type="time" id="quiet_hours_end" name="quiet_hours_end"
                                   value="<?= htmlspecialchars($user['quiet_hours_end'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pulsanti -->
            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salva Modifiche
                </button>
                <a href="profile.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Annulla
                </a>
            </div>
        </form>
    </div>

    <!-- Sezione Cambio Password -->
    <div class="card">
        <h2><i class="fas fa-key"></i> Cambia Password</h2>
        <p class="subtitle">Per motivi di sicurezza, il cambio password richiede la verifica dell'identità</p>
        <a href="change-password.php" class="btn btn-primary">
            <i class="fas fa-key"></i> Cambia Password
        </a>
    </div>
</div>
</body>
</html>