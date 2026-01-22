<?php
/**
 * Landing Page Modernizzata
 * File: public/index.php
 */

require_once '../src/config/session.php';

// Se l'utente è già loggato, via alla dashboard corretta
if (Session::isLoggedIn()) {
    $role = Session::getMainRole();
    if ($role === 'Admin') header('Location: ../dashboard/admin/');
    elseif ($role === 'Bibliotecario') header('Location: ../dashboard/librarian/');
    else header('Location: ../dashboard/student/');
    exit;
}

require_once '../src/Views/layout/header.php';
?>

<style>
    :root {
        --primary-color: #0d6efd;
        --secondary-color: #64748b;
        --dark-bg: #0f172a;
        --light-bg: #f8fafc;
    }

    body {
        background-color: var(--light-bg);
    }

    .hero-section {
        position: relative;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
        padding: 120px 0;
        color: white;
        overflow: hidden;
    }

    /* Elementi decorativi di sfondo */
    .hero-bg-shape {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.1;
        z-index: 0;
    }

    .shape-1 {
        width: 400px;
        height: 400px;
        background: #3b82f6;
        top: -100px;
        right: -50px;
    }

    .shape-2 {
        width: 300px;
        height: 300px;
        background: #8b5cf6;
        bottom: -50px;
        left: -100px;
    }

    .hero-content {
        position: relative;
        z-index: 1;
    }

    .btn-hero {
        padding: 12px 35px;
        font-weight: 600;
        border-radius: 50rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .btn-hero:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .stats-container {
        margin-top: -50px; /* Sovrapposizione alla Hero */
        position: relative;
        z-index: 2;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(0, 0, 0, 0.02);
        transition: transform 0.3s ease;
        text-align: center;
        height: 100%;
    }

    .stat-card:hover {
        transform: translateY(-10px);
    }

    .icon-wrapper {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin: 0 auto 1.5rem;
    }

    .section-title {
        font-weight: 800;
        letter-spacing: -0.5px;
        color: #1e293b;
        margin-bottom: 1rem;
    }

    .hours-card {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .hour-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px dashed #e2e8f0;
    }

    .hour-row:last-child {
        border-bottom: none;
    }

    .step-card {
        border: none;
        background: transparent;
        text-align: center;
        padding: 1rem;
    }

    .step-number {
        font-size: 4rem;
        font-weight: 900;
        color: #e2e8f0;
        line-height: 1;
        margin-bottom: -20px;
        position: relative;
        z-index: 0;
    }

    .step-icon {
        position: relative;
        z-index: 1;
        background: white;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        color: var(--primary-color);
        font-size: 1.5rem;
    }

</style>

<section class="hero-section">
    <div class="hero-bg-shape shape-1"></div>
    <div class="hero-bg-shape shape-2"></div>

    <div class="container hero-content text-center">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <span class="badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 mb-4 fw-normal">
                    <i class="fas fa-sparkles text-warning me-2"></i>La biblioteca del futuro è qui
                </span>
                <h1 class="display-3 fw-bold mb-4">Scopri, Prenota, <span class="text-primary text-gradient"
                                                                          style="background: -webkit-linear-gradient(#60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Impara.</span>
                </h1>
                <p class="lead mb-5 text-white opacity-75 mx-auto" style="max-width: 700px;">
                    BiblioSystem rivoluziona il modo di vivere la cultura.
                    Un catalogo infinito a portata di click, gestione smart dei prestiti e spazi pensati per la tua
                    concentrazione.
                </p>

                <div class="d-flex justify-content-center gap-3">
                    <a href="register.php" class="btn btn-primary btn-hero shadow-lg">
                        <i class="fas fa-rocket me-2"></i>Inizia Subito
                    </a>
                    <a href="catalog.php" class="btn btn-outline-light btn-hero">
                        Esplora Catalogo
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container stats-container mb-5">
    <div class="row g-4 justify-content-center">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-book"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">10.000+</h3>
                <p class="text-muted small text-uppercase fw-bold ls-1 mb-0">Volumi Disponibili</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">500+</h3>
                <p class="text-muted small text-uppercase fw-bold ls-1 mb-0">Studenti Attivi</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">24/7</h3>
                <p class="text-muted small text-uppercase fw-bold ls-1 mb-0">Servizi Digitali</p>
            </div>
        </div>
    </div>
</div>

<section class="py-5">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5">

            <div class="col-lg-6">
                <h2 class="section-title display-6">Studiare non è mai stato <br>così semplice.</h2>
                <p class="text-secondary fs-5 mb-4 lh-lg">
                    Dimentica le file e le schede cartacee. Con BiblioSystem hai il controllo totale: verifica la
                    disponibilità in tempo reale, prenota da casa e ricevi notifiche intelligenti.
                </p>

                <div class="d-flex flex-column gap-3 mt-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-wifi fa-lg text-primary me-3 bg-white p-3 rounded shadow-sm"></i>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">WiFi Ultra-Veloce</h6>
                            <small class="text-muted">Connessione fibra in tutte le aule studio.</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tablet-alt fa-lg text-primary me-3 bg-white p-3 rounded shadow-sm"></i>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Consultazione Digitale</h6>
                            <small class="text-muted">Accesso a migliaia di eBook e riviste.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 offset-lg-1">
                <div class="hours-card">
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-dark text-white rounded p-2 me-3"><i class="far fa-clock fa-lg"></i></div>
                        <h4 class="fw-bold m-0 text-dark">Orari di Apertura</h4>
                    </div>

                    <div class="hour-row">
                        <span class="text-secondary fw-medium">Lunedì - Venerdì</span>
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">08:00 - 17:00</span>
                    </div>
                    <div class="hour-row">
                        <span class="text-secondary fw-medium">Sabato</span>
                        <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2">08:00 - 12:30</span>
                    </div>
                    <div class="hour-row">
                        <span class="text-secondary fw-medium">Domenica</span>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2">Chiuso</span>
                    </div>

                    <div class="alert alert-light border mt-4 mb-0 d-flex align-items-center">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <small class="text-muted">Gli orari possono variare nei festivi.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-white border-top">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">Inizia in 4 passaggi</h2>
            <p class="text-muted">Il processo è completamente automatizzato per farti risparmiare tempo.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">01</div>
                    <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                    <h5 class="fw-bold text-dark">Registrati</h5>
                    <p class="text-muted small">Crea il tuo account gratuito e ottieni la tessera virtuale.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">02</div>
                    <div class="step-icon"><i class="fas fa-search"></i></div>
                    <h5 class="fw-bold text-dark">Cerca</h5>
                    <p class="text-muted small">Sfoglia il catalogo e trova il libro che ti serve.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">03</div>
                    <div class="step-icon"><i class="fas fa-mouse-pointer"></i></div>
                    <h5 class="fw-bold text-dark">Prenota</h5>
                    <p class="text-muted small">Blocca il libro con un click. Ti avviseremo quando è pronto.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">04</div>
                    <div class="step-icon"><i class="fas fa-book-reader"></i></div>
                    <h5 class="fw-bold text-dark">Ritira</h5>
                    <p class="text-muted small">Passa in sede, mostra il QR Code e goditi la lettura.</p>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <a href="register.php" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm">
                Crea il tuo Account Ora
            </a>
        </div>
    </div>
</section>

<?php
require_once '../src/Views/layout/footer.php';
?>
