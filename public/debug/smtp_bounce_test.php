<?php
// FILE DI DEBUG PER BOUNCE/INDIRIZZI INESISTENTI
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../src/config/email.php';

echo "<h1>Test Invio a Indirizzo Inesistente</h1>";

try {
    // Attiva debug massimo
    $mailer = new EmailService(true); 
    
    // Indirizzo sicuramente inesistente per forzare un errore
    $invalidAddress = "indirizzo_inesistente_" . time() . "@gmail.com";
    
    echo "<p>Tentativo invio a: <strong>$invalidAddress</strong></p>";
    echo "<p>Analizza il log qui sotto. Se vedi '250 OK' o simile alla fine, l'invio è stato accettato dal server SMTP (che poi proverà a consegnarlo e fallirà asincronamente).</p>";
    echo "<hr><pre>";

    $result = $mailer->send(
        $invalidAddress, 
        "Test Bounce StackMasters", 
        "<p>Questa mail non dovrebbe arrivare a destinazione.</p>"
    );

    echo "</pre><hr>";

    if ($result) {
        echo "<h2 style='color:orange'>⚠️ INVIO ACCETTATO DAL SERVER</h2>";
        echo "<p>Il server SMTP ha accettato la mail. Se l'indirizzo non esiste, dovresti ricevere una notifica di 'Delivery Status Notification (Failure)' sulla casella mittente ({$_ENV['MAIL_FROM_ADDRESS']}).</p>";
        echo "<p>Controlla la casella <strong>" . htmlspecialchars($_ENV['MAIL_FROM_ADDRESS']) . "</strong> (anche Spam).</p>";
    } else {
        echo "<h2 style='color:green'>✅ ERRORE RILEVATO SUBITO</h2>";
        echo "<p>Il server ha rifiutato immediatamente l'invio (comportamento corretto per indirizzi malformati o domini errati).</p>";
        echo "<p>Errore: " . htmlspecialchars($mailer->getLastError()) . "</p>";
    }

} catch (Exception $e) {
    echo "<h2>Eccezione: " . $e->getMessage() . "</h2>";
}
