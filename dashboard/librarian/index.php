<?php
/**
 * Dashboard Bibliotecario - Pagina Principale
 * File: dashboard/librarian/index.php
 */

// 1. Configurazione Sessioni e Database
require_once __DIR__ . '/../../src/config/session.php';
require_once __DIR__ . '/../../src/config/database.php';

// 2. Controllo Permessi
Session::requireRole('Bibliotecario');

// 3. Recupero Dati Utente
$nomeUtente = $_SESSION['nome_completo'] ?? 'Collega';

// 4. Inclusione Header
require_once __DIR__ . '/../../src/Views/layout/header.php';
?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }

        .card-lms { border: none; border-radius: 16px; transition: transform 0.2s, box-shadow 0.2s; background: white; height: 100%; }
        .card-lms:hover { transform: translateY(-5px); box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1); }

        /* Colore Prestito: Indigo */
        .btn-prestito { background-color: #4f46e5; color: white; border: none; font-weight: 700; padding: 12px; border-radius: 10px; transition: all 0.2s; }
        .btn-prestito:hover { background-color: #4338ca; color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }

        /* Colore Restituzione: Emerald/Green */
        .btn-restituzione { background-color: #10b981; color: white; border: none; font-weight: 700; padding: 12px; border-radius: 10px; transition: all 0.2s; }
        .btn-restituzione:hover { background-color: #059669; color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }

        .icon-box { width: 64px; height: 64px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.5rem; }
        .fw-black { font-weight: 900; }
    </style>

    <div class="container py-5">
        <!-- Intestazione -->
        <div class="row mb-5">
            <div class="col-12 text-center text-md-start">
                <h1 class="fw-black text-slate-800 display-6 uppercase tracking-tight">Area Bibliotecario</h1>
                <p class="text-muted">Benvenuto, <strong><?= htmlspecialchars($nomeUtente) ?></strong>. Gestisci il flusso operativo della biblioteca.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- SEZIONE CIRCOLAZIONE (AZIONI RAPIDE) -->
            <div class="col-md-12 mb-2">
                <div class="card card-lms shadow-sm p-4 border-start border-5 border-primary">
                    <div class="row align-items-center">
                        <div class="col-lg-7">
                            <h4 class="fw-bold mb-1">Circolazione Libri</h4>
                            <p class="text-muted small mb-lg-0">Registra l'uscita (Prestito) o il rientro (Restituzione) dei volumi.</p>
                        </div>
                        <div class="col-lg-5">
                            <div class="row g-3">
                                <div class="col-6">
                                    <a href="new-loan.php" class="btn btn-prestito w-100 d-flex align-items-center justify-content-center gap-2">
                                        <i class="fas fa-arrow-up"></i> PRESTITO
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="returns.php" class="btn btn-restituzione w-100 d-flex align-items-center justify-content-center gap-2">
                                        <i class="fas fa-arrow-down"></i> RIENTRO
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CATALOGO -->
            <div class="col-md-6">
                <div class="card card-lms shadow-sm p-4">
                    <div class="icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-book"></i>
                    </div>
                    <h5 class="fw-bold text-center">Catalogo Libri</h5>
                    <p class="text-muted text-center small">Aggiungi nuovi titoli, modifica i metadati o gestisci gli autori del sistema.</p>
                    <a href="books.php" class="btn btn-outline-danger w-100 mt-auto rounded-3 fw-bold">Gestisci Libri</a>
                </div>
            </div>

            <!-- INVENTARIO -->
            <div class="col-md-6">
                <div class="card card-lms shadow-sm p-4">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-barcode"></i>
                    </div>
                    <h5 class="fw-bold text-center">Inventario Copie</h5>
                    <p class="text-muted text-center small">Controlla le singole copie fisiche, assegna codici e verifica la disponibilità.</p>
                    <a href="inventory.php" class="btn btn-outline-warning w-100 mt-auto rounded-3 fw-bold">Gestisci Copie</a>
                </div>
            </div>
        </div>
    </div>

<?php
require_once __DIR__ . '/../../src/Views/layout/footer.php';
?>