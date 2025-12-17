<?php
require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Carica .env dalla root del progetto
$projectRoot = dirname(__DIR__, 2);
if (file_exists($projectRoot . '/.env')) {
    try {
        Dotenv::createImmutable($projectRoot)->load();
    } catch (\Throwable $e) {
        throw new \Exception("Errore caricamento .env: " . $e->getMessage());
    }
}

// Leggi credenziali dal .env (senza fallback - obbligatorio)
if (empty($_ENV['DB_HOST'])) {
    throw new \Exception("Variabile DB_HOST non definita in .env");
}
if (empty($_ENV['DB_DATABASE'])) {
    throw new \Exception("Variabile DB_DATABASE non definita in .env");
}
if (empty($_ENV['DB_USERNAME'])) {
    throw new \Exception("Variabile DB_USERNAME non definita in .env");
}

$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_DATABASE'];
$user = $_ENV['DB_USERNAME'];
$pass = $_ENV['DB_PASSWORD'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Inserire l'id utente per cui generare la ricevuta
$id_utente = 1;

// Recupero dati utente
$stmt = $pdo->prepare('SELECT nome, cognome, email FROM Utenti WHERE id_utente = ?');
$stmt->execute([$id_utente]);
$utente = $stmt->fetch();

if (!$utente) {
    die('Utente non trovato');
}

// Recupero prestiti attivi
$stmt = $pdo->prepare('SELECT p.id_prestito, l.titolo, p.data_prestito, p.scadenza_prestito 
                       FROM Prestiti p
                       JOIN Inventari i ON p.id_inventario = i.id_inventario
                       JOIN Libri l ON i.id_libro = l.id_libro
                       WHERE p.id_utente = ? AND p.data_restituzione IS NULL');
$stmt->execute([$id_utente]);
$prestiti = $stmt->fetchAll();

// Creazione PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Biblioteca');
$pdf->SetTitle('Ricevuta Prestiti');
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Ricevuta Prestiti', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 6, 'Utente: ' . $utente['nome'] . ' ' . $utente['cognome'], 0, 1);
$pdf->Cell(0, 6, 'Email: ' . $utente['email'], 0, 1);
$pdf->Ln(5);

// Tabella prestiti
$html = '<table border="1" cellpadding="4">
<tr><th>Titolo</th><th>Data Prestito</th><th>Data Scadenza</th></tr>';

$qr_data = "Prestiti di " . $utente['nome'] . " " . $utente['cognome'] . "\n";

foreach ($prestiti as $p) {
    $html .= '<tr><td>' . htmlspecialchars($p['titolo']) . '</td>'
        . '<td>' . $p['data_prestito'] . '</td>'
        . '<td>' . $p['scadenza_prestito'] . '</td></tr>';

    $qr_data .= $p['titolo'] . ' - Scadenza: ' . $p['scadenza_prestito'] . '\n';
}

$html .= '</table>';
$pdf->writeHTML($html, true, false, true, false, '');

// QR Code
$pdf->Ln(5);
$pdf->write2DBarcode($qr_data, 'QRCODE,H', '', '', 50, 50, null, 'N');

// Output PDF
$pdf->Output('ricevuta_prestiti.pdf', 'I');

