<?php
require_once '../../src/config/database.php';
require_once '../../src/config/session.php';

Session::requireLogin();

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();

// Recupera dati attuali
try {
    $stmt = $db->prepare("SELECT * FROM utenti WHERE id_utente = ?");
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

        $notificheAttive = isset($_POST['notifiche_attive']) ? 1 : 0;
        $quietStart = $_POST['quiet_hours_start'] ?? null;
        $quietEnd = $_POST['quiet_hours_end'] ?? null;

        // Validazione email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email non valida";
        } else {
            // Verifica se email già usata da altro utente
            try {
                $stmtCheck = $db->prepare("SELECT id_utente FROM utenti WHERE email = ? AND id_utente != ?");
                $stmtCheck->execute([$email, $userId]);
                if ($stmtCheck->fetch()) {
                    $errors[] = "Email già in uso da un altro utente";
                }
            } catch (Exception $e) {
                error_log("Errore verifica email: " . $e->getMessage());
                $errors[] = "Errore durante la verifica dell'email";
            }
        }

        if (empty($errors)) {
            try {
                $stmtUpdate = $db->prepare("
                    UPDATE utenti 
                    SET email = ?, 
                        notifiche_attive = ?,
                        quiet_hours_start = ?,
                        quiet_hours_end = ?
                    WHERE id_utente = ?
                ");

                $stmtUpdate->execute([
                    $email,
                    $notificheAttive,
                    $quietStart ?: null,
                    $quietEnd ?: null,
                    $userId
                ]);

                // Log modifica
                try {
                    $db->prepare("
                        INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address)
                        VALUES (?, 'MODIFICA_PROFILO', 'Profilo aggiornato', INET_ATON(?))
                    ")->execute([$userId, $_SERVER['REMOTE_ADDR']]);
                } catch (Exception $e) {
                    error_log("Errore log audit: " . $e->getMessage());
                }

                Session::setFlash('success', 'Profilo aggiornato con successo!');
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
            $user['notifiche_attive'] = $notificheAttive;
            $user['quiet_hours_start'] = $quietStart;
            $user['quiet_hours_end'] = $quietEnd;
        }
    }
}

// Calcolo iniziali per l'avatar
$iniziali = strtoupper(substr($user['nome'], 0, 1) . substr($user['cognome'], 0, 1));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Profilo - Biblioteca ITIS Rossi</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #bf2121; /* Rosso ITIS Rossi */
            --primary-hover: #a01b1b;
            --bg-color: #f3f4f6;
            --card-border-radius: 16px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: #1f2937;
        }

        .profile-header-card {
            background: linear-gradient(135deg, #fff 0%, #fff 100%);
            border: none;
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--primary-color);
        }

        .avatar-circle {
            width: 80px;
            height: 80px;
            background-color: #fee2e2;
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05);
        }

        .card-custom {
            border: none;
            border-radius: var(--card-border-radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            background: #fff;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .data-item {
            margin-bottom: 8px;
        }

        .data-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .data-value {
            font-weight: 500;
            color: #111827;
            font-size: 0.95rem;
            background-color: #f9fafb;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
        }

        .data-icon {
            color: #9ca3af;
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.75rem;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(191, 33, 33, 0.1);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-outline-custom {
            color: #4b5563;
            border-color: #d1d5db;
            border-radius: 8px;
            background: white;
            font-weight: 500;
        }

        .btn-outline-custom:hover {
            background-color: #f3f4f6;
            color: #111827;
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            border-left: 4px solid #ef4444;
            background-color: #fef2f2;
        }
    </style>
</head>
<body class="py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="profile.php" class="text-decoration-none text-secondary fw-bold small">
                    <i class="fas fa-chevron-left me-1"></i> Torna al Profilo
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-custom alert-dismissible fade show shadow-sm mb-4" role="alert">
                    <div class="d-flex text-danger">
                        <i class="fas fa-exclamation-circle mt-1 me-3 fs-5"></i>
                        <div>
                            <strong class="d-block mb-1">Impossibile salvare le modifiche</strong>
                            <ul class="mb-0 ps-3 small text-secondary">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="profile-header-card p-4 d-flex align-items-center">
                    <div class="avatar-circle me-4">
                        <?= $iniziali ?>
                    </div>
                    <div>
                        <h1 class="h4 fw-bold text-dark mb-1">Modifica Profilo</h1>
                        <p class="text-muted mb-0 small">Gestisci le tue informazioni personali e le preferenze
                            dell'account.</p>
                    </div>
                </div>

                <div class="card-custom p-4">
                    <div class="section-title">
                        <i class="fas fa-user-circle me-2"></i>Dati Anagrafici
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="data-item">
                                <div class="data-label">Nome Completo</div>
                                <div class="data-value">
                                    <i class="fas fa-user data-icon"></i>
                                    <?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="data-item">
                                <div class="data-label">Codice Fiscale</div>
                                <div class="data-value">
                                    <i class="fas fa-id-card data-icon"></i>
                                    <?= htmlspecialchars($user['cf']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="data-item">
                                <div class="data-label">Data di Nascita</div>
                                <div class="data-value">
                                    <i class="fas fa-calendar-alt data-icon"></i>
                                    <?= date('d/m/Y', strtotime($user['data_nascita'])) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="data-item">
                                <div class="data-label">Luogo di Nascita</div>
                                <div class="data-value">
                                    <i class="fas fa-map-marker-alt data-icon"></i>
                                    <?= htmlspecialchars($user['comune_nascita']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-light border d-flex align-items-center mt-3 mb-0 py-2 rounded-3">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <small class="text-muted">Per modificare questi dati, rivolgiti all'amministratore.</small>
                    </div>
                </div>

                <div class="card-custom p-4">
                    <div class="section-title">
                        <i class="fas fa-cog me-2"></i>Impostazioni Account
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <label for="email" class="form-label fw-bold small text-secondary text-uppercase">Email di
                                Contatto</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i
                                            class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control border-start-0 ps-0" id="email" name="email"
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <label class="form-check-label fw-bold text-dark" for="notifiche_attive">Notifiche
                                            Email</label>
                                        <div class="small text-muted">Ricevi avvisi su prestiti e scadenze.</div>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="notifiche_attive"
                                               name="notifiche_attive"
                                               style="width: 3em; height: 1.5em;" <?= $user['notifiche_attive'] ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <hr class="text-muted opacity-25">

                                <div class="row align-items-center g-2">
                                    <div class="col-12 col-md-4">
                                        <span class="small fw-bold text-secondary text-uppercase"><i
                                                    class="fas fa-moon me-1"></i>Modalità Silenziosa</span>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white">Dalle</span>
                                            <input type="time" id="quiet_hours_start" name="quiet_hours_start"
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($user['quiet_hours_start'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white">Alle</span>
                                            <input type="time" id="quiet_hours_end" name="quiet_hours_end"
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($user['quiet_hours_end'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4 mb-5">
                    <a href="change-password.php" class="text-danger text-decoration-none small fw-bold">
                        <i class="fas fa-key me-1"></i> Cambia Password
                    </a>
                    <div class="d-flex gap-2">
                        <a href="profile.php" class="btn btn-outline-custom">Annulla</a>
                        <button type="submit" class="btn btn-primary-custom shadow-sm">
                            Salva Modifiche
                        </button>
                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>