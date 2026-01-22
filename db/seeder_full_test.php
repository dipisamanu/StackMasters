<?php
/**
 * Seeder FULL TEST v8 - Prezzi, Generi, Recensioni Unique e Dati Reali
 * Utilizzo: localhost/StackMasters/db/seeder_full_test.php
 */

// --- REAL-TIME OUTPUT SETUP ---
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);
// -----------------------------

set_time_limit(900);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/Models/BookModel.php';
require_once __DIR__ . '/../src/Services/GoogleBooksService.php';
require_once __DIR__ . '/../src/Services/OpenLibraryService.php';

$CONFIG = [
    'NUM_USERS' => 30,
    'LOANS_PER_BOOK_MIN' => 2,
    'LOANS_PER_BOOK_MAX' => 15,
    'REVIEWS_CHANCE' => 70,
];

// --- SISTEMA DI TRADUZIONE GENERI (EN -> IT) ---
function translateGenre($englishGenre) {
    $map = [
        'Science Fiction' => 'Fantascienza', 'Sci-Fi' => 'Fantascienza', 'Fantasy' => 'Fantasy',
        'Thriller' => 'Thriller', 'Mystery' => 'Giallo', 'Detective' => 'Giallo', 'Crime' => 'Poliziesco',
        'Romance' => 'Rosa', 'Love' => 'Rosa', 'Historical' => 'Storico', 'History' => 'Storia',
        'Biography' => 'Biografia', 'Autobiography' => 'Autobiografia', 'Philosophy' => 'Filosofia',
        'Psychology' => 'Psicologia', 'Religion' => 'Religione', 'Computer' => 'Informatica',
        'Technology' => 'Tecnologia', 'Science' => 'Scienze', 'Poetry' => 'Poesia', 'Drama' => 'Teatro',
        'Comics' => 'Fumetti', 'Graphic Novels' => 'Graphic Novel', 'Juvenile' => 'Ragazzi',
        'Young Adult' => 'Ragazzi', 'Children' => 'Bambini', 'Education' => 'Didattica',
        'Business' => 'Economia', 'Cooking' => 'Cucina', 'Travel' => 'Viaggi', 'Art' => 'Arte',
        'Music' => 'Musica', 'Fiction' => 'Narrativa', 'Literature' => 'Letteratura'
    ];
    foreach ($map as $key => $val) {
        if (stripos($englishGenre, $key) !== false) return $val;
    }
    return ucfirst($englishGenre);
}

// --- DATI FINTI RECENSIONI ---
$reviewTitles = [
    5 => ['Capolavoro', 'Incredibile', 'Da leggere', 'Magnifico', 'Indimenticabile'],
    4 => ['Molto bello', 'Piacevole', 'Consigliato', 'Ben scritto', 'Interessante'],
    3 => ['Carino', 'Senza lode', 'Scorrevole', 'Passabile'],
    2 => ['Noioso', 'Deludente', 'Lento', 'Difficile'],
    1 => ['Pessimo', 'Sconsigliato', 'Illeggibile', 'Brutto']
];
$reviewBodies = [
    "La trama ti tiene incollato fino alla fine.", "Uno stile unico.", "Consigliato agli amanti del genere.",
    "Un classico intramontabile.", "Il finale mi ha sorpreso.", "Mi aspettavo di più.", "Scritto bene ma non il mio genere.",
    "Una storia toccante."
];

$isbnList = [
    '9788804668237', '9788806216467', '9788824730990', '9788806207717', '9788804523772',
    '9788807900068', '9788806227715', '9788807900365', '9788806173760', '9788807033483',
    '9788804667926', '9788804668282', '9788807901355', '9788804668008', '9788807900341',
    '9788804679233', '9788807900402', '9788806218201', '9788806219208', '9788804667889',
    '9788807018282', '9788869183157', '9788804702177', '9788804616764', '9788804688228',
    '9788863554149', '9788856667820', '9788834734667', '9788804712534', '9788806202477',
    '9788893441310', '9788830104716', '9788806225919', '9788807881602', '9788845292613',
    '9788806229559', '9788811606567', '9788845930065', '9788811606567', '9788845263675'
];

function safeTruncate($string, $length) {
    return (strlen($string) > $length) ? substr($string, 0, $length) : $string;
}

echo '<body style="font-family: sans-serif; background: #222; color: #eee; padding: 20px;">';
echo '<div style="max-width: 900px; margin: 0 auto;">';
echo "<h1 style='color: #4caf50; border-bottom: 2px solid #4caf50; padding-bottom: 10px;'>🚀 Seeder FULL TEST v8 (Prezzi & Generi)</h1>";

try {
    $db = Database::getInstance()->getConnection();
    $gbService = new GoogleBooksService();
    $olService = new OpenLibraryService();
    $bookModel = new BookModel();

    // 1. PULIZIA
    echo "<p>🧹 Pulizia database...</p>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['prestiti', 'inventari', 'prenotazioni', 'recensioni', 'notifiche_web', 'libri_autori', 'libri_generi', 'libri', 'rfid', 'utenti', 'utenti_ruoli', 'cache_stats_libri', 'cache_correlazioni', 'generi'];
    foreach ($tables as $t) $db->exec("TRUNCATE TABLE $t");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. UTENTI
    echo "<p>👥 Creazione {$CONFIG['NUM_USERS']} utenti...</p>";
    $usersIds = [];
    $password = password_hash('password', PASSWORD_DEFAULT);

    // Admin
    $db->prepare("INSERT INTO utenti (cf, nome, cognome, email, password, email_verificata, id_rfid) VALUES (?, ?, ?, ?, ?, 1, NULL)")
        ->execute(['ADM0000000000001', 'Admin', 'Sistema', 'admin@biblio.it', $password]);
    $uidAdmin = $db->lastInsertId();
    $db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, 1)")->execute([$uidAdmin]);
    $usersIds[] = $uidAdmin;

    // Studente
    $db->prepare("INSERT INTO utenti (cf, nome, cognome, email, password, email_verificata, id_rfid) VALUES (?, ?, ?, ?, ?, 1, NULL)")
        ->execute(['STD0000000000001', 'Mario', 'Rossi', 'mario@biblio.it', $password]);
    $uidStd = $db->lastInsertId();
    $db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, 4)")->execute([$uidStd]);
    $usersIds[] = $uidStd;

    // Random Users
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

    // 3. LIBRI
    $scaffaleChar = 'A'; $ripiano = 1; $posizione = 1;
    $totalLoans = 0; $totalReviews = 0;

    foreach ($isbnList as $index => $isbn) {
        $data = $gbService->fetchByIsbn($isbn);
        if (!$data) $data = $olService->fetchByIsbn($isbn);
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

        // Creazione libro
        $idLibro = $bookModel->create($dbData);

        // --- GESTIONE PREZZO E DATA ---
        // Se l'API ha restituito un prezzo, usalo, altrimenti genera
        $prezzo = $data['prezzo'] ?? 0;
        if ($prezzo <= 0) {
            $prezzo = rand(10, 35) + (rand(0, 99) / 100);
        }

        // Randomizza data creazione (per "Novità")
        $randomDaysAgo = rand(0, 60);
        $randomDate = date('Y-m-d H:i:s', strtotime("-$randomDaysAgo days"));

        // Update diretto per garantire l'inserimento
        $db->prepare("UPDATE libri SET valore_copertina = ?, data_creazione = ? WHERE id_libro = ?")
            ->execute([$prezzo, $randomDate, $idLibro]);

        // --- GESTIONE GENERI ---
        if (!empty($data['categorie'])) {
            foreach ($data['categorie'] as $rawCat) {
                $catName = translateGenre($rawCat);
                $catName = trim(safeTruncate($catName, 50));
                if (empty($catName)) continue;

                $stmtChk = $db->prepare("SELECT id FROM generi WHERE nome = ?");
                $stmtChk->execute([$catName]);
                $catId = $stmtChk->fetchColumn();

                if (!$catId) {
                    $stmtIns = $db->prepare("INSERT INTO generi (nome) VALUES (?)");
                    $stmtIns->execute([$catName]);
                    $catId = $db->lastInsertId();
                }

                $db->prepare("INSERT IGNORE INTO libri_generi (id_libro, id_genere) VALUES (?, ?)")
                    ->execute([$idLibro, $catId]);
            }
        } else {
            $db->prepare("INSERT IGNORE INTO generi (nome) VALUES ('Narrativa')")->execute();
            $catId = $db->lastInsertId() ?: $db->query("SELECT id FROM generi WHERE nome='Narrativa'")->fetchColumn();
            $db->prepare("INSERT IGNORE INTO libri_generi (id_libro, id_genere) VALUES (?, ?)")->execute([$idLibro, $catId]);
        }

        // COPIE
        $isPopular = ($index % 4 == 0);
        $numCopie = $isPopular ? rand(5, 8) : rand(1, 2);
        $copieIds = [];
        for ($c = 0; $c < $numCopie; $c++) {
            $codicePos = sprintf("%s%d-%02d", $scaffaleChar, $ripiano, $posizione++);
            if ($posizione > 10) { $posizione = 1; $ripiano++; }
            
            // Logica condizione e stato coerente
            $condizioneFisica = (rand(1, 10) == 1) ? 'DANNEGGIATO' : 'BUONO';
            $statoIniziale = ($condizioneFisica === 'DANNEGGIATO') ? 'NON_IN_PRESTITO' : 'DISPONIBILE';

            $stmt = $db->prepare("INSERT INTO inventari (id_libro, collocazione, stato, condizione) VALUES (?, ?, ?, ?)");
            $stmt->execute([$idLibro, $codicePos, $statoIniziale, $condizioneFisica]);
            
            // Aggiungiamo ai prestiti solo se NON è danneggiato
            if ($statoIniziale === 'DISPONIBILE') {
                $copieIds[] = $db->lastInsertId();
            }
        }

        // PRESTITI (Solo su copie disponibili)
        if (!empty($copieIds)) {
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

                // Inserimento prestito
                $db->prepare("INSERT INTO prestiti (id_inventario, id_utente, data_prestito, data_restituzione, scadenza_prestito) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$idCopia, $idUtente, $start, $end, $end]);
                $totalLoans++;

                // RECENSIONI
                if (rand(1, 100) <= $CONFIG['REVIEWS_CHANCE'] && $ratingCount < 15) {
                    $voto = rand(3, 5);
                    if (rand(1, 10) == 1) $voto = rand(1, 2);

                    $titoloRec = $reviewTitles[$voto][array_rand($reviewTitles[$voto])];
                    $bodyRec = $reviewBodies[array_rand($reviewBodies)];

                    $stmtRec = $db->prepare("INSERT IGNORE INTO recensioni (id_libro, id_utente, voto, descrizione, data_creazione) VALUES (?, ?, ?, ?, ?)");
                    $stmtRec->execute([$idLibro, $idUtente, $voto, "$titoloRec. $bodyRec", $end]);

                    if ($stmtRec->rowCount() > 0) {
                        $ratingSum += $voto;
                        $ratingCount++;
                        $totalReviews++;
                    }
                }
            }

            if ($ratingCount > 0) {
                $avg = $ratingSum / $ratingCount;
                $db->prepare("UPDATE libri SET rating = ? WHERE id_libro = ?")->execute([$avg, $idLibro]);
            }
        }

        // CODE PRENOTAZIONI
        if ($isPopular && rand(1, 2) == 1 && !empty($copieIds)) {
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
                $db->prepare("INSERT IGNORE INTO prenotazioni (id_utente, id_libro, data_richiesta) VALUES (?, ?, NOW())")
                    ->execute([$uidQ, $idLibro]);
            }
            echo "<span style='color:orange;'> [QUEUE] $queueSize code</span> ";
        }

        // Output
        $printCat = !empty($data['categorie']) ? translateGenre($data['categorie'][0]) : 'Narrativa';
        echo "<span style='color:#81c784;'>[OK] {$dbData['titolo']}</span> <span style='font-size:0.8em; color:#888'>($printCat) - €" . number_format($prezzo, 2) . "</span><br>";
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