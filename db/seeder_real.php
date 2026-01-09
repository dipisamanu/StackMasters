<?php
/**
 * Seeder Reale - Versione Finale con Autori Garantiti
 * Utilizzo: localhost/StackMasters/db/seeder_real.php
 */

set_time_limit(600); // 10 minuti
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/Models/BookModel.php';
require_once __DIR__ . '/../src/Models/InventoryModel.php';
require_once __DIR__ . '/../src/Services/GoogleBooksService.php';
require_once __DIR__ . '/../src/Services/OpenLibraryService.php';

// Helper per stringhe sicure
function safeTruncate($string, $length) {
    if (strlen($string) > $length) return substr($string, 0, $length);
    return $string;
}

// Backup per dati mancanti (se l'API fallisce)
$backupPublishers = ['Mondadori', 'Einaudi', 'Feltrinelli', 'Rizzoli', 'Bompiani', 'Adelphi', 'Garzanti', 'Laterza', 'Newton Compton'];
$backupAuthors = [
    'Umberto Eco', 'Italo Calvino', 'Alessandro Manzoni', 'Luigi Pirandello',
    'Dante Alighieri', 'Giovanni Verga', 'Primo Levi', 'Cesare Pavese',
    'Elsa Morante', 'Alberto Moravia', 'Dino Buzzati', 'Carlo Goldoni',
    'Stephen King', 'J.K. Rowling', 'George Orwell', 'Ken Follett'
];

$isbnList = [
    '9788804668237', // 1984
    '9788806216467', // Il nome della rosa
    '9788824730990', // I Promessi Sposi
    '9788806207717', // Il barone rampante
    '9788804523772', // Uno, nessuno e centomila
    '9788807900068', // La coscienza di Zeno
    '9788806227715', // Se questo è un uomo
    '9788807900365', // Il vecchio e il mare
    '9788806173760', // Il deserto dei Tartari
    '9788807033483', // Novecento
    '9788804667926', // Il Signore degli Anelli
    '9788804668282', // Il Piccolo Principe
    '9788807901355', // Siddharta
    '9788804668008', // Il ritratto di Dorian Gray
    '9788807900341', // Fahrenheit 451
    '9788804679233', // Dieci piccoli indiani
    '9788807900402', // Il grande Gatsby
    '9788806218201', // Lessico famigliare
    '9788806219208', // La luna e i falò
    '9788804667889', // Cime tempestose
    '9788807018282', // Gomorra
    '9788869183157', // Harry Potter 1
    '9788804702177', // It
    '9788804616764', // Lo Hobbit
    '9788804688228', // Percy Jackson
    '9788863554149', // Hunger Games
    '9788856667820', // Le cronache di Narnia
    '9788834734667', // Il trono di spade
    '9788804712534', // Shining
    '9788806202477', // La solitudine dei numeri primi
    '9788893441310', // L'amica geniale
    '9788830104716', // La vita bugiarda degli adulti
    '9788806225919', // Io non ho paura
    '9788807881602', // Seta
    '9788845292613', // Sapiens
    '9788806229559', // L'arte della guerra
    '9788817127486', // Breve storia del tempo
    '9788845930065', // L'ordine del tempo
    '9788811606567', // Il diario di Anna Frank
    '9788845263675', // Il gene egoista
    '9788807880940', // Pensieri lenti e veloci
    '9788842074335', // Storia d'Italia
    '9788845925344', // La filosofia antica
    '9788815270771', // Storia contemporanea
    '9788820395728', // La Divina Commedia
    '9788806224691'  // Anime baltiche
];

echo '<body style="font-family: sans-serif; background: #f4f4f9; padding: 20px;">';
echo '<div style="max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">';
echo "<h1 style='color: #bf2121; border-bottom: 2px solid #bf2121; padding-bottom: 10px;'>📚 Ripristino Catalogo (Fix Autori)</h1>";

try {
    $db = Database::getInstance()->getConnection();

    // ---------------------------------------------------------
    // 0. FIX DB (Autori)
    // ---------------------------------------------------------
    try {
        $db->exec("ALTER TABLE libri MODIFY COLUMN titolo VARCHAR(255) NOT NULL");
        $db->exec("ALTER TABLE autori MODIFY COLUMN nome VARCHAR(100)");
        $db->exec("ALTER TABLE autori MODIFY COLUMN cognome VARCHAR(100)");
        $db->exec("ALTER TABLE libri MODIFY COLUMN immagine_copertina VARCHAR(500) DEFAULT NULL");
    } catch(Exception $e) {}

    // ---------------------------------------------------------
    // 1. PULIZIA
    // ---------------------------------------------------------
    echo "<h3>1. Pulizia Database...</h3>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['prestiti', 'inventari', 'libri_autori', 'libri', 'rfid'];
    foreach ($tables as $t) $db->exec("TRUNCATE TABLE $t");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p style='color:green;'>✅ Tabelle svuotate.</p><hr>";

    // ---------------------------------------------------------
    // 2. IMPORTAZIONE
    // ---------------------------------------------------------
    $gbService = new GoogleBooksService();
    $olService = new OpenLibraryService();
    $bookModel = new BookModel();
    $invModel = new InventoryModel();

    $stats = ['ok' => 0, 'fail' => 0, 'copies' => 0];
    $scaffaleChar = 'A'; $ripiano = 1; $posizione = 1;

    echo "<h3>2. Importazione Libri...</h3>";
    echo "<div style='background: #111; color: #ddd; padding: 15px; border-radius: 5px; font-family: monospace; height: 400px; overflow-y: scroll;'>";

    foreach ($isbnList as $isbn) {
        // Scarica dati
        $data = $gbService->fetchByIsbn($isbn);
        if (!$data) $data = $olService->fetchByIsbn($isbn);

        if (!$data) {
            echo "<span style='color:red;'>[API FAIL] $isbn non trovato.</span><br>";
            $stats['fail']++;
            continue;
        }

        // === FIX AUTORI E DATI MANCANTI ===
        if (empty($data['autore'])) {
            $data['autore'] = $backupAuthors[array_rand($backupAuthors)];
        }
        if (empty($data['editore'])) {
            $data['editore'] = $backupPublishers[array_rand($backupPublishers)];
        }
        if (empty($data['pagine']) || $data['pagine'] == 0) {
            $data['pagine'] = rand(120, 550);
        }
        if (empty($data['anno'])) {
            $data['anno'] = rand(1995, 2024);
        }
        if (empty($data['descrizione'])) {
            $data['descrizione'] = "Descrizione non disponibile per questo volume. " . $data['titolo'] . " è un'opera fondamentale del genere.";
        }

        $dbData = [
            'titolo'        => safeTruncate($data['titolo'], 250),
            'autore'        => safeTruncate($data['autore'], 100),
            'editore'       => safeTruncate($data['editore'], 100),
            'anno'          => $data['anno'],
            'descrizione'   => $data['descrizione'],
            'pagine'        => $data['pagine'],
            'isbn'          => $data['isbn'],
            'copertina_url' => safeTruncate($data['copertina'], 495)
        ];

        try {
            // INSERT LIBRO (Restituisce ID)
            $idLibro = $bookModel->create($dbData, []);

            if (!$idLibro) throw new Exception("Create ha restituito 0.");

            echo "<span>[OK] <strong>" . htmlspecialchars(substr($dbData['titolo'], 0, 30)) . "...</strong> (Autore: {$dbData['autore']})</span>";

            // INSERT COPIE
            $numCopie = rand(1, 3);
            for ($c = 0; $c < $numCopie; $c++) {
                $codicePos = sprintf("%s%d-%02d", $scaffaleChar, $ripiano, $posizione);
                $posizione++;
                if ($posizione > 15) { $posizione = 1; $ripiano++; if ($ripiano > 9) { $ripiano = 1; $scaffaleChar++; } }

                $rfid = 'BOOK-' . strtoupper(substr(md5(uniqid() . $c), 0, 8));

                // Inserimento manuale
                $db->prepare("INSERT INTO rfid (rfid, tipo) VALUES (?, 'LIBRO')")->execute([$rfid]);
                $idRfid = $db->lastInsertId();

                $stmtInv = $db->prepare("INSERT INTO inventari (id_libro, id_rfid, collocazione, condizione, stato) VALUES (?, ?, ?, 'BUONO', 'DISPONIBILE')");
                $stmtInv->execute([$idLibro, $idRfid, $codicePos]);

                $stats['copies']++;
            }

            echo " <span style='color: #4caf50;'>+ $numCopie copie</span><br>";
            $stats['ok']++;

        } catch (Exception $e) {
            echo "<span style='color:red;'> [ERROR] " . $e->getMessage() . "</span><br>";
            $stats['fail']++;
        }

        usleep(100000);
        if ($stats['ok'] % 3 == 0) flush();
    }
    echo "</div>";

    echo "<div style='margin-top: 20px; padding: 20px; background: #e8f5e9; border: 1px solid #c8e6c9;'>";
    echo "<h2 style='color: #2e7d32;'>Riepilogo Finale</h2>";
    echo "<ul>";
    echo "<li>Libri Inseriti: <strong>{$stats['ok']}</strong></li>";
    echo "<li>Copie Create: <strong>{$stats['copies']}</strong></li>";
    echo "</ul>";
    echo "<a href='../public/catalog.php' style='background: #bf2121; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>VAI AL CATALOGO</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Errore</h2><p>" . $e->getMessage() . "</p>";
}
echo '</div></body>';
?>