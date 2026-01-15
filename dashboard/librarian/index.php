<?php
/**
 * Dashboard Bibliotecario - Pagina Principale
 * File: dashboard/librarian/index.php
 */

// 1. Configurazione Sessioni e Database
require_once __DIR__ . '/../../src/config/session.php';
require_once __DIR__ . '/../../src/config/database.php';

// 2. Protezione Accesso (Controlla che l'utente sia autenticato e abbia il ruolo corretto)
Session::requireLogin();
Session::requireRole('Bibliotecario');

// 3. Recupero Dati Utente tramite la classe Session
$nomeUtente = Session::getNomeCompleto() ?? 'Collega';
$ruoloPrimario = Session::getMainRole();
$userId = Session::getUserId();

/**
 * RECUPERO STATISTICHE PERSONALI (L'operatore visualizza i propri libri in lettura)
 */
$db = Database::getInstance()->getConnection();
$statsPersonali = ['totale' => 0, 'scaduti' => 0, 'in_scadenza' => 0];

try {
    $stmtStats = $db->prepare("
        SELECT 
            COUNT(*) as totale,
            SUM(CASE WHEN scadenza_prestito < CURDATE() THEN 1 ELSE 0 END) as scaduti,
            SUM(CASE WHEN scadenza_prestito >= CURDATE() AND DATEDIFF(scadenza_prestito, CURDATE()) <= 3 THEN 1 ELSE 0 END) as in_scadenza
        FROM prestiti 
        WHERE id_utente = ? AND data_restituzione IS NULL
    ");
    $stmtStats->execute([$userId]);
    $result = $stmtStats->fetch(PDO::FETCH_ASSOC);
    if ($result) $statsPersonali = $result;
} catch (Exception $e) {
    // Silenzioso
}

/**
 * LOGICA DI DISTINZIONE RUOLO (Allineata con il file student/index.php)
 * Determina i colori e le etichette dell'Area Personale
 */
$labelArea = "Area Personale";
$colorArea = "indigo"; // Default per Staff/Admin

switch($ruoloPrimario) {
    case 'Studente':
        $labelArea = "Area Studente";
        $colorArea = "danger"; // Rosso
        break;
    case 'Docente':
        $labelArea = "Area Docente";
        $colorArea = "warning"; // Arancio/Giallo
        break;
    case 'Bibliotecario':
    case 'Staff':
    case 'Admin':
    default:
        $labelArea = "Profilo Staff";
        $colorArea = "indigo"; // Blu/Indigo
        break;
}

// 4. Inclusione Header
require_once __DIR__ . '/../../src/Views/layout/header.php';
?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }

        /* Design Card Premium */
        .card-lms { border: none; border-radius: 20px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: white; height: 100%; display: flex; flex-direction: column; border: 1px solid #e2e8f0; }
        .card-lms:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); border-color: #cbd5e1; }

        /* Pulsanti Operativi Front-Desk */
        .btn-prestito { background-color: #4f46e5; color: white; border: none; font-weight: 700; padding: 12px; border-radius: 14px; transition: all 0.2s; }
        .btn-prestito:hover { background-color: #4338ca; color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }

        .btn-restituzione { background-color: #10b981; color: white; border: none; font-weight: 700; padding: 12px; border-radius: 14px; transition: all 0.2s; }
        .btn-restituzione:hover { background-color: #059669; color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }

        .icon-box { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; font-size: 1.25rem; }
        .fw-black { font-weight: 900; }

        .section-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .section-label::after { content: ""; height: 1px; flex-grow: 1; background: #e2e8f0; }

        /* Indicatori Numerici Area Personale */
        .mini-stat { display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; padding: 10px; border-radius: 12px; border: 1px solid #f1f5f9; min-width: 70px; }
        .mini-stat-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
        .mini-stat-value { font-weight: 900; font-size: 1.1rem; }

        /* Pulsante Accesso Area Personale con Hover Pieno */
        .btn-personal-access { padding: 14px; border-radius: 14px; font-weight: 800; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.025em; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; border-width: 2px; border-style: solid; }

        .btn-personal-indigo { background: rgba(79, 70, 229, 0.05); color: #4f46e5; border-color: rgba(79, 70, 229, 0.2); }
        .btn-personal-indigo:hover { background: #4f46e5; color: white; border-color: #4f46e5; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3); }

        .btn-personal-danger { background: rgba(239, 68, 68, 0.05); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }
        .btn-personal-danger:hover { background: #ef4444; color: white; border-color: #ef4444; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3); }

        .btn-personal-warning { background: rgba(245, 158, 11, 0.05); color: #b45309; border-color: rgba(245, 158, 11, 0.2); }
        .btn-personal-warning:hover { background: #f59e0b; color: white; border-color: #f59e0b; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.3); }

        .btn-personal-success { background: rgba(16, 185, 129, 0.05); color: #065f46; border-color: rgba(16, 185, 129, 0.2); }
        .btn-personal-success:hover { background: #10b981; color: white; border-color: #10b981; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); }

        .role-badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 5px 14px; border-radius: 20px; letter-spacing: 0.05em; border: 1px solid rgba(0,0,0,0.05); }
        .badge-indigo { background: #e0e7ff; color: #4338ca; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-warning { background: #fef3c7; color: #92400e; }
    </style>

    <div class="container py-5">
        <!-- Header Dashboard -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="bg-indigo-600 p-2 rounded-lg text-white shadow-lg">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h1 class="fw-black text-slate-800 display-6 uppercase tracking-tight m-0">Area Bibliotecario</h1>
                        </div>
                        <p class="text-muted">Benvenuto, <strong><?= htmlspecialchars($nomeUtente) ?></strong>. Gestisci la biblioteca o controlla i tuoi prestiti.</p>
                    </div>
                    <div>
                        <span class="role-badge badge-<?= $colorArea ?>">Sessione: <?= htmlspecialchars($ruoloPrimario) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEZIONE 1: GESTIONE OPERATIVA -->
        <div class="section-label">Registro Operativo e Circolazione</div>
        <div class="row g-4 mb-5">
            <div class="col-md-12">
                <div class="card card-lms shadow-sm p-4 border-start border-5 border-primary">
                    <div class="row align-items-center">
                        <div class="col-lg-7">
                            <h4 class="fw-bold mb-1">Front-Desk Circolazione</h4>
                            <p class="text-muted small mb-lg-0">Interfaccia rapida per scansione barcode e registrazione dei movimenti asset.</p>
                        </div>
                        <div class="col-lg-5">
                            <div class="row g-3">
                                <div class="col-6">
                                    <a href="new-loan.php" class="btn btn-prestito w-100 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                        <i class="fas fa-sign-out-alt"></i> PRESTITO
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="returns.php" class="btn btn-restituzione w-100 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                        <i class="fas fa-sign-in-alt"></i> RIENTRO
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-lms shadow-sm p-4">
                    <div class="icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-book"></i>
                    </div>
                    <h5 class="fw-bold">Gestione Catalogo</h5>
                    <p class="text-muted small mb-4">Modifica metadati, gestisci autori e importa nuovi volumi via ISBN.</p>
                    <a href="books.php" class="btn btn-outline-danger w-100 mt-auto rounded-3 fw-bold">Vai al Catalogo</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-lms shadow-sm p-4">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-barcode"></i>
                    </div>
                    <h5 class="fw-bold">Stato Inventario</h5>
                    <p class="text-muted small mb-4">Controllo copie fisiche, collocazioni e perizia dello stato volumi.</p>
                    <a href="inventory.php" class="btn btn-outline-warning w-100 mt-auto rounded-3 fw-bold">Gestisci Copie</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-lms shadow-sm p-4">
                    <div class="icon-box bg-info bg-opacity-10 text-info">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <h5 class="fw-bold">Registro Prestiti</h5>
                    <p class="text-muted small mb-4">Visione globale dei prestiti attivi e monitoraggio dei ritardi degli utenti.</p>
                    <a href="lista-prestiti.php" class="btn btn-outline-info w-100 mt-auto rounded-3 fw-bold">Monitoraggio</a>
                </div>
            </div>
        </div>

        <!-- SEZIONE 2: AREA PERSONALE (COORDINATA AL RUOLO) -->
        <div class="section-label">La Mia Attività (Profilo Lettore)</div>
        <div class="row g-4">
            <!-- PRESTITI PERSONALI -->
            <div class="col-md-6">
                <div class="card card-lms shadow-sm p-5 border-bottom border-5 border-<?= $colorArea ?>">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box bg-<?= $colorArea ?> bg-opacity-10 text-<?= $colorArea ?> m-0">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <h5 class="fw-bold m-0"><?= $labelArea ?></h5>
                        </div>

                        <div class="d-flex gap-2">
                            <div class="mini-stat">
                                <span class="mini-stat-label">Letture</span>
                                <span class="mini-stat-value text-<?= $colorArea ?>"><?= $statsPersonali['totale'] ?></span>
                            </div>
                            <?php if ($statsPersonali['scaduti'] > 0): ?>
                                <div class="mini-stat bg-danger bg-opacity-10 border-danger border-opacity-20">
                                    <span class="mini-stat-label text-danger">Scaduti</span>
                                    <span class="mini-stat-value text-danger"><?= $statsPersonali['scaduti'] ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="text-muted small mb-5">Hai attualmente <strong><?= $statsPersonali['totale'] ?></strong> volumi in carico.
                        <?php if ($statsPersonali['in_scadenza'] > 0): ?>
                            <span class="text-warning font-bold">Attenzione: <?= $statsPersonali['in_scadenza'] ?> libri scadono tra meno di 3gg.</span>
                        <?php endif; ?>
                        Monitora i tuoi rientri personali.</p>

                    <a href="../student/index.php" class="btn-personal-access btn-personal-<?= $colorArea ?> shadow-sm">
                        <i class="fas fa-book-reader"></i> I Miei Libri Attivi
                    </a>
                </div>
            </div>

            <!-- GAMIFICATION -->
            <div class="col-md-6">
                <div class="card card-lms shadow-sm p-5 border-bottom border-5 border-success">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box bg-success bg-opacity-10 text-success m-0">
                                <i class="fas fa-medal"></i>
                            </div>
                            <h5 class="fw-bold m-0">Badge & XP</h5>
                        </div>
                    </div>
                    <p class="text-muted small mb-5">Continua a leggere per aumentare il tuo livello e sbloccare badge esclusivi come lettore Staff dell'ITIS Rossi.</p>

                    <a href="../student/profile.php" class="btn-personal-access btn-personal-success shadow-sm">
                        <i class="fas fa-trophy"></i> Vedi i miei traguardi
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/../../src/Views/layout/footer.php';
?>