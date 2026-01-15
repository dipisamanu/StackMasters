<?php
/**
 * Home Page Pubblica - Landing Page
 * File: public/index.php
 */

require_once '../src/config/session.php';
require_once '../src/Views/layout/header.php';
?>

    <style>
        :root {
            --primary-red: #bf2121;
            --dark-text: #333333;
            --light-bg: #f8f9fa;
        }

        .hero-header {
            position: relative;
            background-size: cover;
            background: linear-gradient(rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.75)) no-repeat center;
            padding: 80px 0;
            color: white;
        }

        /* Media Query per Mobile */
        @media (max-width: 768px) {
            .hero-header {
                padding: 50px 0;
            }

            .display-4 {
                font-size: 2.5rem;
            }
        }

        .btn-hero-base {
            font-weight: 600;
            padding: 14px 45px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border-radius: 6px;
            border: 2px solid transparent;
            letter-spacing: 0.5px;
            font-size: 1rem;
        }

        .btn-hero-primary {
            background-color: #d92525;
            border-color: #d92525;
            color: white;
            box-shadow: none;
        }

        .btn-hero-primary:hover {
            background-color: #b01f1f;
            border-color: #b01f1f;
            color: white;
            transform: translateY(-3px);
        }

        .btn-hero-outline {
            background-color: rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.8);
            color: white;
        }

        .btn-hero-outline:hover {
            background-color: white;
            border-color: white;
            color: var(--primary-red);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
            transform: translateY(-3px);
        }

        .stats-section {
            background-color: white;
            padding: 60px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .stat-card {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid #eee;
            border-bottom: 4px solid var(--primary-red);
            height: 100%;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark-text);
            margin-bottom: 10px;
        }

        .section-title {
            color: var(--dark-text);
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: var(--primary-red);
            margin: 15px auto 0;
            border-radius: 2px;
        }

        .hours-card {
            background: #ffffff;
            color: var(--dark-text);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }

        .hours-title {
            color: var(--primary-red);
            font-weight: 800;
            margin-bottom: 25px;
            font-size: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .hours-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .hours-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 1.1rem;
        }

        .hours-list li:last-child {
            border-bottom: none;
        }

        .step-icon-wrapper {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 2px solid var(--primary-red);
            font-size: 2rem;
            color: var(--primary-red);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
    </style>

    <header class="hero-header text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">La Tua Biblioteca, <span class="text-danger">Digitale</span></h1>
                    <p class="lead mb-4 opacity-90 fs-5">
                        Un ecosistema moderno per l'apprendimento.<br>
                        Cerca, prenota e gestisci i tuoi libri direttamente dallo smartphone.
                    </p>

                    <?php if (!Session::isLoggedIn()): ?>
                        <div class="d-flex justify-content-center gap-4 mt-5">
                            <a href="register.php" class="btn btn-hero-base btn-hero-primary"
                               aria-label="Registrati alla biblioteca">
                                <i class="fas fa-user-plus me-2" aria-hidden="true"></i> Inizia Ora
                            </a>
                            <a href="login.php" class="btn btn-hero-base btn-hero-outline"
                               aria-label="Accedi al tuo account">
                                Accedi
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="lead mb-3 fw-semibold">Bentornato, <?= htmlspecialchars(Session::getNomeCompleto() ?? 'utente') ?>!</p>
                        <div class="mt-5">
                            <a href="<?php
                            $role = Session::getMainRole();
                            if ($role === 'Admin') echo '../dashboard/admin/';
                            elseif ($role === 'Bibliotecario') echo '../dashboard/librarian/';
                            else echo '../dashboard/student/';
                            ?>" class="btn btn-hero-base btn-hero-primary">
                                <i class="fas fa-tachometer-alt me-2" aria-hidden="true"></i> Vai alla Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section class="stats-section">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-book fa-3x text-secondary mb-3 opacity-50" aria-hidden="true"></i>
                        <div class="stat-number">10.000+</div>
                        <div class="text-uppercase fw-bold text-muted small">Volumi Disponibili</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-users fa-3x text-secondary mb-3 opacity-50" aria-hidden="true"></i>
                        <div class="stat-number">500+</div>
                        <div class="text-uppercase fw-bold text-muted small">Studenti Attivi</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-check-circle fa-3x text-secondary mb-3 opacity-50" aria-hidden="true"></i>
                        <div class="stat-number">24/7</div>
                        <div class="text-uppercase fw-bold text-muted small">Prenotazioni Online</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" style="background-color: #f8f9fa;">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col-lg-7 mb-5 mb-lg-0">
                    <h2 class="section-title">Innovazione al servizio della cultura</h2>
                    <p class="fs-5 text-dark mb-4">
                        La biblioteca dell'Istituto Rossi si rinnova. Abbiamo abbandonato la vecchia gestione cartacea
                        per offrirti un servizio <strong>veloce, trasparente e smart</strong>.
                    </p>
                    <p class="text-muted mb-4">
                        Grazie alla nuova piattaforma puoi verificare la disponibilità dei testi in tempo reale,
                        ricevere notifiche sulle scadenze e guadagnare badge.
                    </p>
                    <ul class="list-unstyled mt-4">
                        <li class="mb-3 fs-5"><i class="fas fa-wifi text-danger me-3" aria-hidden="true"></i> Postazioni
                            studio con WiFi
                        </li>
                        <li class="mb-3 fs-5"><i class="fas fa-tablet-alt text-danger me-3" aria-hidden="true"></i>
                            Consultazione digitale
                        </li>
                        <li class="mb-3 fs-5"><i class="fas fa-user-friends text-danger me-3" aria-hidden="true"></i>
                            Area studio di gruppo
                        </li>
                    </ul>
                </div>

                <div class="col-lg-5">

                    <div class="hours-card">
                        <h3 class="hours-title"><i class="far fa-clock me-2" aria-hidden="true"></i> Orari di Apertura
                        </h3>
                        <ul class="hours-list">
                            <li>
                                <span class="fw-bold text-dark">Lunedì - Venerdì</span>
                                <span class="badge bg-success fs-6">08:00 - 17:00</span>
                            </li>
                            <li>
                                <span class="fw-bold text-dark">Sabato</span>
                                <span class="badge bg-warning text-dark fs-6">08:00 - 12:30</span>
                            </li>
                            <li>
                                <span class="fw-bold text-dark">Domenica</span>
                                <span class="badge bg-secondary fs-6">Chiuso</span>
                            </li>
                        </ul>
                        <div class="mt-3 pt-3 border-top text-center text-muted small">
                            * Gli orari possono variare durante le festività.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-4">
            <h2 class="text-center section-title">Come Funziona il Prestito</h2>

            <div class="row g-4 mt-5 text-center">
                <div class="col-md-3">
                    <div class="step-icon-wrapper">
                        <i class="fas fa-user-edit" aria-hidden="true"></i>
                    </div>
                    <h4 class="h5 fw-bold text-dark">1. Registrati</h4>
                    <p class="text-muted small">Crea il tuo account in pochi secondi. Il sistema genererà la tua tessera
                        virtuale.</p>
                </div>

                <div class="col-md-3">
                    <div class="step-icon-wrapper">
                        <i class="fas fa-search" aria-hidden="true"></i>
                    </div>
                    <h4 class="h5 fw-bold text-dark">2. Cerca & Prenota</h4>
                    <p class="text-muted small">Sfoglia il catalogo online. Se il libro è disponibile, prenotalo con un
                        click.</p>
                </div>

                <div class="col-md-3">
                    <div class="step-icon-wrapper">
                        <i class="fas fa-qrcode" aria-hidden="true"></i>
                    </div>
                    <h4 class="h5 fw-bold text-dark">3. Ritira</h4>
                    <p class="text-muted small">Passa in biblioteca, mostra il QR Code della tua tessera e ritira il
                        libro.</p>
                </div>

                <div class="col-md-3">
                    <div class="step-icon-wrapper">
                        <i class="fas fa-undo-alt" aria-hidden="true"></i>
                    </div>
                    <h4 class="h5 fw-bold text-dark">4. Restituisci</h4>
                    <p class="text-muted small">Riporta il libro entro la scadenza (30 giorni per docenti, 14 per
                        studenti).</p>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="register.php" class="btn btn-outline-danger px-4 rounded-pill">
                    Crea subito il tuo account
                </a>
            </div>
        </div>
    </section>

<?php
require_once '../src/Views/layout/footer.php';
?>