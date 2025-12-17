<?php
/**
 * Check Database Structure
 * File: check-database.php
 *
 * Verifica che tutte le colonne necessarie esistano
 * RIMUOVERE IN PRODUZIONE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Verifica Struttura Database</h2>";
echo "<pre>";

try {
    require_once '../src/config/database.php';
    $db = getDB();
    echo "✅ Connessione database OK\n\n";

    // Check tabella Utenti
    echo "📊 Struttura tabella Utenti:\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $db->query("DESCRIBE Utenti");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $requiredColumns = ['id_utente', 'nome', 'cognome', 'email', 'password', 'email_verificata', 'token', 'scadenza_verifica'];
    $foundColumns = [];

    foreach ($columns as $col) {
        $foundColumns[] = $col['Field'];
        echo sprintf("  %-30s %-20s %s\n",
            $col['Field'],
            $col['Type'],
            in_array($col['Field'], $requiredColumns) ? '✅' : ''
        );
    }

    echo "\n" . str_repeat("-", 60) . "\n";
    echo "Verifica colonne necessarie:\n\n";

    $missing = [];
    foreach ($requiredColumns as $req) {
        if (in_array($req, $foundColumns)) {
            echo "  ✅ $req\n";
        } else {
            echo "  ❌ $req (MANCANTE!)\n";
            $missing[] = $req;
        }
    }

    if (!empty($missing)) {
        echo "\n⚠️ COLONNE MANCANTI RILEVATE!\n\n";
        echo "Esegui queste query per aggiungere le colonne:\n\n";

        $alterQueries = [
            'token' => "ALTER TABLE Utenti ADD COLUMN token VARCHAR(255) NULL;",
            'scadenza_verifica' => "ALTER TABLE Utenti ADD COLUMN scadenza_verifica DATETIME NULL;",
            'email_verificata' => "ALTER TABLE Utenti ADD COLUMN email_verificata TINYINT(1) DEFAULT 0;",
            'tentativi_login_falliti' => "ALTER TABLE Utenti ADD COLUMN tentativi_login_falliti INT DEFAULT 0;",
            'blocco_account_fino_al' => "ALTER TABLE Utenti ADD COLUMN blocco_account_fino_al DATETIME NULL;"
        ];

        foreach ($missing as $col) {
            if (isset($alterQueries[$col])) {
                echo $alterQueries[$col] . "\n";
            }
        }
    }

    // Check tabella Logs_Audit
    echo "\n\n📊 Verifica tabella Logs_Audit:\n";
    echo str_repeat("-", 60) . "\n";

    try {
        $stmt = $db->query("DESCRIBE Logs_Audit");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Tabella Logs_Audit esiste\n";
        echo "  Colonne presenti: " . count($columns) . "\n\n";

        foreach ($columns as $col) {
            echo sprintf("  %-30s %-20s\n", $col['Field'], $col['Type']);
        }

    } catch (Exception $e) {
        echo "❌ Tabella Logs_Audit NON esiste!\n\n";
        echo "Esegui questa query per crearla:\n\n";
        echo "CREATE TABLE Logs_Audit (\n";
        echo "    id_log INT AUTO_INCREMENT PRIMARY KEY,\n";
        echo "    id_utente INT NULL,\n";
        echo "    azione VARCHAR(100) NOT NULL,\n";
        echo "    dettagli TEXT NULL,\n";
        echo "    ip_address INT UNSIGNED NULL,\n";
        echo "    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        echo "    FOREIGN KEY (id_utente) REFERENCES Utenti(id_utente) ON DELETE SET NULL\n";
        echo ");\n";
    }

    // Test queries
    echo "\n\n🧪 Test Query:\n";
    echo str_repeat("-", 60) . "\n";

    // Count users
    $stmt = $db->query("SELECT COUNT(*) as count FROM Utenti");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  Totale utenti: $count\n";

    // Count verified users
    $stmt = $db->query("SELECT COUNT(*) as count FROM Utenti WHERE email_verificata = 1");
    $verified = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  Utenti verificati: $verified\n";

    // Sample user
    $stmt = $db->query("SELECT id_utente, nome, email, email_verificata FROM Utenti LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "\n  Esempio utente:\n";
        echo "    ID: " . $user['id_utente'] . "\n";
        echo "    Nome: " . $user['nome'] . "\n";
        echo "    Email: " . $user['email'] . "\n";
        echo "    Email Verificata: " . ($user['email_verificata'] ? 'SI' : 'NO') . "\n";
    }

    echo "\n" . str_repeat("-", 60) . "\n";
    echo "\n✅ VERIFICA COMPLETATA!\n";

} catch (Exception $e) {
    echo "\n❌ ERRORE: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<strong>⚠️ IMPORTANTE:</strong> Questo è un file di DEBUG. Rimuovilo prima di andare in produzione!";
echo "</div>";