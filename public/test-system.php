<?php
/**
 * Test Completo del Sistema
 * File: public/test-system.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Completo del Sistema StackMasters</h1>";

// Test 1: Database Connection
echo "<h2>1. Test Connessione Database</h2>";
try {
    require_once '../src/config/database.php';
    $db = getDB();
    echo "<p style='color:green;'>✅ Connessione database riuscita</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Verify Tables
echo "<h2>2. Verifica Tabelle</h2>";
$expectedTables = ['Utenti', 'Libri', 'Prestiti', 'Ruoli', 'Inventari', 'Generi', 'Autori'];
try {
    foreach ($expectedTables as $table) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM $table");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "<p>✅ Tabella <strong>$table</strong>: $count record</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
}

// Test 3: Verify Test Users
echo "<h2>3. Verifica Utenti di Test</h2>";
try {
    $stmt = $db->prepare("SELECT id_utente, email, nome, cognome FROM Utenti WHERE email LIKE '%@demo.it'");
    $stmt->execute();
    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($utenti) > 0) {
        echo "<p style='color:green;'>✅ Trovati " . count($utenti) . " utenti di test:</p>";
        echo "<ul>";
        foreach ($utenti as $user) {
            echo "<li>{$user['nome']} {$user['cognome']} ({$user['email']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange;'>⚠️ Nessun utente di test trovato</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
}

// Test 4: Verify Roles
echo "<h2>4. Verifica Ruoli</h2>";
try {
    $stmt = $db->prepare("SELECT id_ruolo, nome, priorita FROM Ruoli ORDER BY priorita");
    $stmt->execute();
    $ruoli = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<ul>";
    foreach ($ruoli as $ruolo) {
        echo "<li>{$ruolo['nome']} (Priorità: {$ruolo['priorita']})</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
}

// Test 5: Password Verification
echo "<h2>5. Test Password Hashing</h2>";
try {
    $stmt = $db->prepare("SELECT email, password FROM Utenti WHERE email='mario@demo.it' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $testPassword = 'Password123!';
        if (password_verify($testPassword, $user['password'])) {
            echo "<p style='color:green;'>✅ Password hash verificato correttamente</p>";
        } else {
            echo "<p style='color:red;'>❌ Password hash non corrisponde</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
}

echo "<h2>✅ Test Completo!</h2>";
echo "<p><a href='login.php'>Vai al Login →</a></p>";
?>

