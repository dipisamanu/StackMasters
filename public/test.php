<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Test iniziato<br>";

$sessionPath = __DIR__ . '/../src/config/session.php';
echo "2. Percorso session.php: $sessionPath<br>";
echo "3. File esiste? " . (file_exists($sessionPath) ? "SÌ" : "NO") . "<br>";

if (file_exists($sessionPath)) {
    echo "4. Caricamento session.php...<br>";
    require_once $sessionPath;
    echo "5. Session.php caricato!<br>";

    echo "6. Test generateCSRFToken(): ";
    $token = generateCSRFToken();
    echo "OK - Token: " . substr($token, 0, 10) . "...<br>";
} else {
    echo "4. ERRORE: File non trovato!<br>";
    echo "5. Directory attuale: " . __DIR__ . "<br>";
    echo "6. Contenuto parent dir:<br>";
    print_r(scandir(__DIR__ . '/..'));
}

echo "7. Test completato!";
?>