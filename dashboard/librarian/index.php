<?php
/**
 * Dashboard Bibliotecario - Pagina Principale
 * File: dashboard/librarian/index.php
 */

// 1. Configurazione Sessioni e Database
// Usiamo il path relativo per tornare alla root e includere i config
require_once __DIR__ . '/../../src/config/session.php';
require_once __DIR__ . '/../../src/config/database.php';

// 2. Controllo Permessi
// Questa funzione blocca chi non è Bibliotecario e lo rimanda al login o alla home
Session::requireRole('Bibliotecario');

// 3. Recupero Dati Utente
$nomeUtente = $_SESSION['user_name'] ?? 'Collega';

// 4. Inclusione Header
require_once __DIR__ . '/../../src/Views/layout/header.php';
?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-danger">Area Bibliotecario</h1>
                <p class="lead">Benvenuto, <strong><?= htmlspecialchars($nomeUtente) ?></strong>. Cosa vuoi fare oggi?</p>
            </div>
        </div>

        <div class="row g-4">

            <div class="col-md-4">
                <div class="card shadow-sm h-100 border-top border-4 border-danger">
                    <div class="card-body text-center">
                        <div class="mb-3" style="font-size: 3rem; color: #bf2121;">
                            <i class="fas fa-book"></i>
                        </div>
                        <h5 class="card-title">Catalogo Libri</h5>
                        <p class="card-text text-muted">Aggiungi nuovi titoli, modifica quelli esistenti o rimuovi libri dal sistema.</p>
                        <a href="books.php" class="btn btn-outline-danger w-100 stretched-link">Gestisci Libri</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm h-100 border-top border-4 border-warning">
                    <div class="card-body text-center">
                        <div class="mb-3" style="font-size: 3rem; color: #ffc107;">
                            <i class="fas fa-barcode"></i>
                        </div>
                        <h5 class="card-title">Inventario Copie</h5>
                        <p class="card-text text-muted">Gestisci le copie fisiche, assegna RFID e controlla la disponibilità sugli scaffali.</p>
                        <a href="#" class="btn btn-outline-warning w-100 disabled">In Arrivo</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm h-100 border-top border-4 border-success">
                    <div class="card-body text-center">
                        <div class="mb-3" style="font-size: 3rem; color: #28a745;">
                            <i class="fas fa-hand-holding-book"></i>
                        </div>
                        <h5 class="card-title">Prestiti & Restituzioni</h5>
                        <p class="card-text text-muted">Registra l'uscita e il rientro dei libri tramite scansione RFID o manuale.</p>
                        <a href="#" class="btn btn-outline-success w-100 disabled">In Arrivo</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php
require_once __DIR__ . '/../../src/Views/layout/footer.php';
?>