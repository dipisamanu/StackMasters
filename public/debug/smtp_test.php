<?php
// FILE DI DEBUG RAPIDO PER SMTP
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test SMTP Diagnostico</h1>";

// 1. Caricamento Classi
require_once __DIR__ . '/../../src/config/email.php';

echo "<p>✅ Classe EmailService caricata.</p>";

try {
    // 2. Istanziazione con DEBUG ATTIVO (true)
    $mailer = new EmailService(true); 
    echo "<p>✅ Istanza EmailService creata.</p>";

    // 3. Tentativo Invio
    $to = "tuamail@esempio.com"; // Sostituisci se vuoi testare un indirizzo specifico, altrimenti usa un dummy
    if (isset($_GET['to'])) $to = $_GET['to'];
    
    echo "<p>Tentativo invio a: <strong>$to</strong> (puoi cambiarlo aggiungendo ?to=tua@mail.it all'url)</p>";
    echo "<hr><pre>";

    $result = $mailer->send(
        $to, 
        "Test SMTP StackMasters " . date('H:i:s'), 
        "<h1>Test Funzionamento</h1><p>Se leggi questo, l'invio funziona.</p>"
    );

    echo "</pre><hr>";

    if ($result) {
        echo "<h2 style='color:green'>✅ INVIO RIUSCITO</h2>";
    } else {
        echo "<h2 style='color:red'>❌ INVIO FALLITO</h2>";
        echo "<p>Errore salvato: " . htmlspecialchars($mailer->getLastError()) . "</p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ ECCEZIONE CRITICA</h2>";
    echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
