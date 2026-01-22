<?php
/**
 * Script di Test Automatico per la Registrazione Prestiti
 * FILE: public/debug/test-prestito.php
 *
 * Esegue test automatici per verificare la logica di blocco per multe
 * e per libri non disponibili.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../src/config/database.php';

// Stile per un output leggibile
echo <<<HTML
<style>
    body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; padding: 2rem; }
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
    .container { max-width: 900px; margin: auto; background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
    h1 { font-size: 1.5rem; font-weight: 900; color: #4338ca; border-bottom: 3px solid #a5b4fc; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
    .test-case { border: 1px solid #e2e8f0; border-radius: 0.75rem; margin-bottom: 1.5rem; overflow: hidden; }
    .test-header { padding: 1rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .test-title { font-weight: 700; color: #1e293b; }
    .test-body { padding: 1.5rem; font-size: 0.9rem; line-height: 1.6; }
    .result { padding: 0.5rem 1rem; border-radius: 99px; font-weight: 700; font-size: 0.8rem; }
    .result.pass { background-color: #dcfce7; color: #166534; }
    .result.fail { background-color: #fee2e2; color: #991b1b; }
    .output { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; word-wrap: break-word; }
    code { background: #e0e7ff; color: #4338ca; padding: 2px 5px; border-radius: 4px; font-weight: 600; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div class="container">
    <h1><i class="fas fa-vial"></i> Test Suite: Registrazione Prestito</h1>
HTML;

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // SETUP DATI DI TEST
    // Utente 1 (con multa)
    $db->exec("INSERT INTO utenti (id_utente, cf, nome, cognome, email, password, consenso_privacy) VALUES (999991, 'UTENTEMULTA', 'Mario', 'Multato', 'multato@test.com', 'test', 1)");
    $db->exec("INSERT INTO multe (id_multa, id_utente, importo, causa) VALUES (999991, 999991, 15.50, 'RITARDO')");

    // Utente 2 (senza multa)
    $db->exec("INSERT INTO utenti (id_utente, cf, nome, cognome, email, password, consenso_privacy) VALUES (999992, 'UTENTEBUONO', 'Giulia', 'Corretta', 'corretta@test.com', 'test', 1)");

    // Utente 3 (che ha già un libro)
    $db->exec("INSERT INTO utenti (id_utente, cf, nome, cognome, email, password, consenso_privacy) VALUES (999993, 'UTENTEPOSSESSORE', 'Luca', 'Lettore', 'lettore@test.com', 'test', 1)");

    // Libro 1 (disponibile)
    $db->exec("INSERT INTO libri (id_libro, titolo) VALUES (999991, 'Libro Disponibile per Test')");
    $db->exec("INSERT INTO inventari (id_inventario, id_libro, stato) VALUES (999991, 999991, 'DISPONIBILE')");

    // Libro 2 (già in prestito)
    $db->exec("INSERT INTO libri (id_libro, titolo) VALUES (999992, 'Libro Già in Prestito')");
    $db->exec("INSERT INTO inventari (id_inventario, id_libro, stato) VALUES (999992, 999992, 'IN_PRESTITO')");
    $db->exec("INSERT INTO prestiti (id_prestito, id_inventario, id_utente, data_restituzione) VALUES (999991, 999992, 999993, NULL)");


    // FUNZIONE PER ESEGUIRE TEST
    function run_test($title, $post_data, $expected_string): void
    {
        echo '<div class="test-case"><div class="test-header"><p class="test-title">' . $title . '</p></div><div class="test-body">';
        
        $_POST = $post_data;
        ob_start();
        // CORREZIONE PERCORSO: Aggiunto un '../' per risalire alla cartella /dashboard
        include __DIR__ . '/../../dashboard/librarian/registra-prestito.php';
        $output = ob_get_clean();

        if (strpos($output, $expected_string) !== false) {
            echo '<p><span class="result pass"><i class="fas fa-check"></i> PASS</span> Il test ha prodotto l\'output atteso.</p>';
        } else {
            echo '<p><span class="result fail"><i class="fas fa-times"></i> FAIL</span> L\'output non corrisponde a quanto atteso.</p>';
            echo '<p><b>Atteso:</b> una stringa contenente <code>' . htmlspecialchars($expected_string) . '</code></p>';
        }
        
        // Mostra un'anteprima dell'output per il debug
        echo '<div class="output">' . htmlspecialchars(substr(strip_tags($output), 0, 500)) . '...</div>';
        echo '</div></div>';
    }

    // ESECUZIONE DEI TEST

    // Test 1: Blocco per multa
    run_test(
        'Test 1: Blocco per utente con multa',
        ['user_barcode' => 'UTENTEMULTA', 'book_ids' => [999991]],
        'PRESTITO BLOCCATO'
    );

    // Test 2: Blocco per libro già in prestito
    run_test(
        'Test 2: Blocco per libro non disponibile',
        ['user_barcode' => 'UTENTEBUONO', 'book_ids' => [999992]],
        'Attualmente in possesso di'
    );

    // Test 3: Prestito andato a buon fine
    run_test(
        'Test 3: Registrazione prestito valida',
        ['user_barcode' => 'UTENTEBUONO', 'book_ids' => [999991]],
        'Operazione Finalizzata'
    );


    // PULIZIA
    $db->rollBack();
    echo "<p style='text-align:center; color: #16a34a; font-weight: bold;'><i class='fas fa-check-circle'></i> Transazione annullata. Il database è stato ripristinato allo stato originale.</p>";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "<div style='background: #fecaca; color: #991b1b; padding: 1.5rem; border-radius: 0.5rem;'>";
    echo "<b>ERRORE CRITICO NELLO SCRIPT DI TEST:</b> " . $e->getMessage();
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "</div>";
