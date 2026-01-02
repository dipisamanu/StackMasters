<?php
/**
 * Home Page Pubblica
 * File: public/index.php
 */

require_once '../src/config/session.php';

// Includi Header
require_once '../src/Views/layout/header.php';
?>

    <div class="hero" style="
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('assets/img/itisrossi.png');
    background-size: cover;
    background-position: center;
    color: white;
    padding: 100px 20px;
    text-align: center;">

        <h1 style="font-size: 3rem; margin-bottom: 20px;">Benvenuto nella Biblioteca Digitale</h1>
        <p style="font-size: 1.2rem; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto;">
            Gestisci i tuoi prestiti, scopri nuove letture e accedi al catalogo completo dell'Istituto Rossi direttamente online.
        </p>

        <?php if (!Session::isLoggedIn()): ?>
            <div style="margin-top: 30px;">
                <a href="register.php" style="
                background: #bf2121;
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin-right: 15px;">
                    Inizia Ora
                </a>
                <a href="login.php" style="
                background: white;
                color: #333;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;">
                    Accedi
                </a>
            </div>
        <?php else: ?>
            <div style="margin-top: 30px;">
                <a href="<?php
                $role = Session::getMainRole();
                if ($role === 'Admin') echo 'dashboard/admin/';
                elseif ($role === 'Bibliotecario') echo 'dashboard/librarian/';
                else echo 'dashboard/student/';
                ?>" style="
                background: #bf2121;
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;">
                    Vai alla tua Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div style="max-width: 1200px; margin: 60px auto; padding: 0 20px;">
        <h2 style="text-align: center; color: #bf2121; margin-bottom: 40px;">Cosa puoi fare?</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center;">
                <i class="fas fa-search" style="font-size: 3rem; color: #bf2121; margin-bottom: 20px;"></i>
                <h3>Cerca Libri</h3>
                <p style="color: #666;">Consulta il nostro vasto catalogo di libri, manuali tecnici e narrativa.</p>
            </div>

            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center;">
                <i class="fas fa-calendar-check" style="font-size: 3rem; color: #bf2121; margin-bottom: 20px;"></i>
                <h3>Gestisci Prestiti</h3>
                <p style="color: #666;">Controlla le scadenze, rinnova i tuoi prestiti e prenota i libri che desideri.</p>
            </div>

            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center;">
                <i class="fas fa-medal" style="font-size: 3rem; color: #bf2121; margin-bottom: 20px;"></i>
                <h3>Guadagna Badge</h3>
                <p style="color: #666;">Più leggi, più sali di livello! Sblocca obiettivi e diventa un Lettore Esperto.</p>
            </div>
        </div>
    </div>

<?php
// Includi Footer
require_once '../src/Views/layout/footer.php';
?>