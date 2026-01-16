<?php
/**
 * Seeder FULL TEST v2 - Compatibile con UNIQUE constraint e Novità Realistiche
 * Utilizzo: localhost/StackMasters/db/seeder_full_test.php
 */

// --- REAL-TIME OUTPUT SETUP ---
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) {
    ob_end_flush();
}
ob_implicit_flush(1);
// -----------------------------

set_time_limit(900);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/Models/BookModel.php';
require_once __DIR__ . '/../src/Services/GoogleBooksService.php';
require_once __DIR__ . '/../src/Services/OpenLibraryService.php';

// --- CONFIGURAZIONE ---
$CONFIG = [
    'NUM_USERS' => 30,
    'LOANS_PER_BOOK_MIN' => 2,
    'LOANS_PER_BOOK_MAX' => 15,
    'REVIEWS_CHANCE' => 70,
];

// --- DATI FINTI ---
$reviewTitles = [
    5 => ['Capolavoro assoluto', 'Incredibile', 'Da leggere assolutamente', 'Magnifico', 'Indimenticabile'],
    4 => ['Molto bello', 'Lettura piacevole', 'Consigliato', 'Ben scritto', 'Interessante'],
    3 => ['Carino ma...', 'Senza infamia e senza lode', 'Lettura scorrevole', 'Ok per passare il tempo'],
    2 => ['Noioso', 'Deludente', 'Mi aspettavo di meglio', 'Lento', 'Difficile da finire'],
    1 => ['Terribile', 'Soldi buttati', 'Illeggibile', 'Pessimo', 'Non lo consiglio']
];
$reviewBodies = [
    "Ho amato ogni pagina di questo libro. I personaggi sono vivi e la trama ti tiene incollato fino alla fine.",
    "Una lettura che fa riflettere. Lo stile dell'autore è unico.",
    "Non riuscivo a smettere di leggere! Consigliato a tutti gli amanti del genere.",
    "Un classico intramontabile che merita la sua fama.",
    "La prima parte è lenta, ma poi decolla. Il finale mi ha sorpreso.",
    "Sinceramente mi aspettavo di più visto l'hype, ma rimane un buon libro.",
    "Non è il mio genere preferito, ma devo ammettere che è scritto bene.",
    "Una storia toccante che mi ha fatto piangere."
];

$isbnList = [
    '9788804668237', '9788806216467', '9788824730990', '9788806207717', '9788804523772',
    '9788807900068', '9788806227715', '9788807900365', '9788806173760', '9788807033483',
    '9788804667926', '9788804668282', '9788807901355', '9788804668008', '9788807900341',
    '9788804679233', '9788807900402', '9788806218201', '9788806219208', '9788804667889',
    '9788807018282', '9788869183157', '9788804702177', '9788804616764', '9788804688228',
    '9788863554149', '9788856667820', '9788834734667', '9788804712534', '9788806202477',
    '9788893441310', '9788830104716', '9788806225919', '9788807881602', '9788845292613',
    '9788806229559', '9788817127486', '9788845930065', '9788811606567', '9788845263675'
];

function safeTruncate($string, $length) {
    return (strlen($string) > $length) ? substr($string, 0, $length) : $string;
}

echo '<body style="font-family: sans-serif; background: #222; color: #eee; padding: 20px;">';
echo '<div style="max-width: 900px; margin: 0 auto;">';
echo "<h1 style='color: #4caf50; border-bottom: 2px solid #4caf50; padding-bottom: 10px;'>🚀 Seeder FULL TEST v2</h1>";

try {
    $db = Database::getInstance()->getConnection();
    $gbService = new GoogleBooksService();
    $bookModel = new BookModel();

    // 1. PULIZIA TOTALE
    echo "<p>🧹 Pulizia database...</p>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['prestiti', 'inventari', 'prenotazioni', 'recensioni', 'notifiche_web', 'libri_autori', 'libri', 'rfid', 'utenti', 'utenti_ruoli', 'cache_stats_libri', 'cache_correlazioni'];
    foreach ($tables as $t) $db->exec("TRUNCATE TABLE $t");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. CREAZIONE UTENTI
    echo "<p>👥 Creazione {$CONFIG['NUM_USERS']} utenti...</p>";
    $usersIds = [];
    $password = password_hash('password', PASSWORD_DEFAULT);

    // Admin e Studente Demo
    $db->prepare("INSERT INTO utenti (cf, nome, cognome, email, password, email_verificata, id_rfid) VALUES (?, ?, ?, ?, ?, 1, NULL)")
        ->execute(['ADM0000000000001', 'Admin', 'Sistema', 'admin@biblio.it', $password]);
    $uidAdmin = $db->lastInsertId();
    $db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, 1)")->execute([$uidAdmin]);
    $usersIds[] = $uidAdmin;

    $db->prepare("INSERT INTO utenti (cf, nome, cognome, email, password, email_verificata, id_rfid) VALUES (?, ?, ?, ?, ?, 1, NULL)")
        ->execute(['STD0000000000001', 'Mario', 'Rossi', 'mario@biblio.it', $password]);
    $uidStd = $db->lastInsertId();
    $db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, 4)")->execute([$uidStd]);
    $usersIds[] = $uidStd;

    // Utenti Random
    $names = ['Luca', 'Anna', 'Marco', 'Sofia', 'Giulia', 'Francesco', 'Alessandro', 'Martina', 'Chiara', 'Matteo'];
    $surnames = ['Bianchi', 'Verdi', 'Neri', 'Gialli', 'Russo', 'Esposito', 'Colombo', 'Romano', 'Ricci', 'Marino'];

    for ($i = 0; $i < $CONFIG['NUM_USERS'] - 2; $i++) {
        $nome = $names[array_rand($names)];
        $cognome = $surnames[array_rand($surnames)];
        $email = strtolower($nome . '.' . $cognome . $i . '@test.com');
        $cf = 'CF' . rand(1000000000, 9999999999) . $i;

        $db->prepare("INSERT INTO utenti (cf, nome, cognome, email, password, email_verificata) VALUES (?, ?, ?, ?, ?, 1)")
            ->execute([$cf, $nome, $cognome, $email, $password]);
        $uid = $db->lastInsertId();
        $db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, 4)")->execute([$uid]);
        $usersIds[] = $uid;
    }

    echo "<div style='background: #111; padding: 15px; height: 500px; overflow-y: scroll; border: 1px solid #444;'>";

    // 3. INSERIMENTO LIBRI
    $scaffaleChar = 'A'; $ripiano = 1; $posizione = 1;
    $totalLoans = 0; $totalReviews = 0;

    foreach ($isbnList as $index => $isbn) {
        $data = $gbService->fetchByIsbn($isbn);
        if (!$data) continue;

        if (empty($data['autore'])) $data['autore'] = 'Autore Sconosciuto';
        if (empty($data['editore'])) $data['editore'] = 'Editore Generico';
        if (empty($data['anno'])) $data['anno'] = rand(2000, 2023);
        if (empty($data['pagine'])) $data['pagine'] = 200;

        $dbData = [
            'titolo' => safeTruncate($data['titolo'], 250),
            'descrizione' => $data['descrizione'] ?? "Nessuna descrizione.",
            'isbn' => $data['isbn'],
            'anno' => $data['anno'],
            'editore' => safeTruncate($data['editore'], 100),
            'pagine' => $data['pagine'],
            'copertina_url' => safeTruncate($data['copertina'], 495),
            'autore' => $data['autore']
        ];

        $idLibro = $bookModel->create($dbData);

        // UPDATE DATA CREAZIONE: Randomizziamo per popolare "Nuovi Arrivi" in modo realistico
        // Alcuni libri aggiunti oggi, altri mesi fa
        $randomDaysAgo = rand(0, 60);
        $randomDate = date('Y-m-d H:i:s', strtotime("-$randomDaysAgo days"));
        $db->prepare("UPDATE libri SET data_creazione = ? WHERE id_libro = ?")->execute([$randomDate, $idLibro]);

        // COPIE
        $isPopular = ($index % 4 == 0);
        $numCopie = $isPopular ? rand(5, 8) : rand(1, 2);
        $copieIds = [];
        for ($c = 0; $c < $numCopie; $c++) {
            $codicePos = sprintf("%s%d-%02d", $scaffaleChar, $ripiano, $posizione++);
            if ($posizione > 10) { $posizione = 1; $ripiano++; }
            $stmt = $db->prepare("INSERT INTO inventari (id_libro, collocazione, stato) VALUES (?, ?, 'DISPONIBILE')");
            $stmt->execute([$idLibro, $codicePos]);
            $copieIds[] = $db->lastInsertId();
        }

        // PRESTITI STORICI
        $numLoans = $isPopular ? rand(15, 30) : rand(2, 8);
        $ratingSum = 0;
        $ratingCount = 0;

        for ($l = 0; $l < $numLoans; $l++) {
            $idUtente = $usersIds[array_rand($usersIds)];
            $idCopia = $copieIds[array_rand($copieIds)];

            $isRecent = (rand(1, 100) <= 30);
            if ($isRecent) {
                $start = date('Y-m-d H:i:s', strtotime('-' . rand(1, 6) . ' days'));
                $end = date('Y-m-d H:i:s', strtotime($start . ' + ' . rand(1, 4) . ' days'));
            } else {
                $start = date('Y-m-d H:i:s', strtotime('-' . rand(30, 365) . ' days'));
                $end = date('Y-m-d H:i:s', strtotime($start . ' + ' . rand(5, 20) . ' days'));
            }

            $db->prepare("INSERT INTO prestiti (id_inventario, id_utente, data_prestito, data_restituzione, scadenza_prestito) VALUES (?, ?, ?, ?, ?)")
                ->execute([$idCopia, $idUtente, $start, $end, $end]);
            $totalLoans++;

            // RECENSIONI (Con INSERT IGNORE per evitare crash su UNIQUE constraint)
            if (rand(1, 100) <= $CONFIG['REVIEWS_CHANCE'] && $ratingCount < 15) {
                $voto = rand(3, 5);
                if (rand(1, 10) == 1) $voto = rand(1, 2);

                $titoloRec = $reviewTitles[$voto][array_rand($reviewTitles[$voto])];
                $bodyRec = $reviewBodies[array_rand($reviewBodies)];

                // NOTA: INSERT IGNORE è fondamentale qui!
                $stmtRec = $db->prepare("INSERT IGNORE INTO recensioni (id_libro, id_utente, voto, descrizione, data_creazione) VALUES (?, ?, ?, ?, ?)");
                $stmtRec->execute([$idLibro, $idUtente, $voto, "$titoloRec. $bodyRec", $end]);

                // Contiamo solo se ha inserito davvero (rowCount > 0), ma per semplicità statistica approssimiamo
                if ($stmtRec->rowCount() > 0) {
                    $ratingSum += $voto;
                    $ratingCount++;
                    $totalReviews++;
                }
            }
        }

        // UPDATE RATING
        if ($ratingCount > 0) {
            $avg = $ratingSum / $ratingCount;
            $db->prepare("UPDATE libri SET rating = ? WHERE id_libro = ?")->execute([$avg, $idLibro]);
        }

        // CODE PRENOTAZIONI
        if ($isPopular && rand(1, 2) == 1) {
            foreach ($copieIds as $cid) {
                $uid = $usersIds[array_rand($usersIds)];
                $now = date('Y-m-d H:i:s');
                $due = date('Y-m-d H:i:s', strtotime('+15 days'));

                $db->prepare("UPDATE inventari SET stato = 'IN_PRESTITO' WHERE id_inventario = ?")->execute([$cid]);
                $db->prepare("INSERT INTO prestiti (id_inventario, id_utente, data_prestito, scadenza_prestito) VALUES (?, ?, ?, ?)")
                    ->execute([$cid, $uid, $now, $due]);
            }
            $queueSize = rand(3, 5);
            for ($q = 0; $q < $queueSize; $q++) {
                $uidQ = $usersIds[array_rand($usersIds)];
                // INSERT IGNORE anche qui per sicurezza
                $db->prepare("INSERT IGNORE INTO prenotazioni (id_utente, id_libro, data_richiesta) VALUES (?, ?, NOW())")
                    ->execute([$uidQ, $idLibro]);
            }
            echo "<span style='color:orange;'> [QUEUE] $queueSize code</span> ";
        }

        echo "<span style='color:#81c784;'>[OK] {$dbData['titolo']}</span> <br>";
        flush();
        usleep(50000);
    }

    echo "</div>";

    // 4. AGGIORNAMENTO CACHE
    echo "<p>⚙️ Aggiornamento Cache...</p>";
    $db->query("CALL Job_AggiornaStatsLibri()");
    $db->query("CALL Job_AggiornaCorrelazioni()");

    echo "<div style='background: #333; padding: 20px; margin-top: 20px; border-radius: 8px;'>";
    echo "<h2 style='color: #4caf50;'>✅ Completato con successo!</h2>";
    echo "<ul>";
    echo "<li>Utenti creati: <strong>{$CONFIG['NUM_USERS']}</strong></li>";
    echo "<li>Libri inseriti: <strong>" . count($isbnList) . "</strong></li>";
    echo "<li>Prestiti: <strong>$totalLoans</strong></li>";
    echo "<li>Recensioni: <strong>$totalReviews</strong></li>";
    echo "</ul>";
    echo "<a href='../public/home.php' style='display:inline-block; background:#2196f3; color:white; padding:10px 20px; text-decoration:none; border-radius:4px; font-weight:bold;'>VAI ALLA HOME</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h1 style='color:red'>ERRORE</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
echo "</div></body>";
?>