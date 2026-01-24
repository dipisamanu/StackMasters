<?php
/**
 * Script di debug per il sistema di prenotazioni (Epic 6)
 * Permette di testare le funzionalità principali delle prenotazioni:
 * - Creazione prenotazione (Coda FIFO)
 * - Visualizzazione lista d'attesa
 * - Simulazione rientro libro e assegnazione automatica (Trigger)
 */

// Abilita visualizzazione errori per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclusione dipendenze
require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

// Tenta di includere l'autoloader se esiste, altrimenti procedi manuale
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Inclusione manuale dei modelli e servizi necessari
require_once __DIR__ . '/../src/Models/BookModel.php';
require_once __DIR__ . '/../src/Models/UserModel.php';
require_once __DIR__ . '/../src/Models/NotificationManager.php'; // Richiesto da LoanService
require_once __DIR__ . '/../src/Services/LoanService.php';

use Ottaviodipisa\StackMasters\Services\LoanService;

// Verifica permessi admin/bibliotecario
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'student') {
    die("<h1>Accesso Negato</h1><p>Questo script è riservato a bibliotecari e amministratori.</p><a href='login.php'>Vai al login</a>");
}

$bookModel = new BookModel();
$userModel = new UserModel();
$db = Database::getInstance()->getConnection();

// Inizializza LoanService (gestisce la logica di business)
try {
    $loanService = new LoanService();
} catch (Exception $e) {
    die("Errore inizializzazione LoanService: " . $e->getMessage());
}

// Gestione azioni POST
$action = $_GET['action'] ?? '';
$message = '';
$msgType = 'info'; // info, success, error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'create_reservation':
                $bookId = $_POST['book_id'];
                $userId = $_POST['user_id'];
                
                // Verifica se esiste già
                $stmt = $db->prepare("SELECT id_prenotazione FROM prenotazioni WHERE id_utente = ? AND id_libro = ? AND scadenza_ritiro IS NULL");
                $stmt->execute([$userId, $bookId]);
                if ($stmt->fetch()) {
                    throw new Exception("L'utente ha già una prenotazione attiva per questo libro.");
                }

                // Inserimento manuale prenotazione
                $stmt = $db->prepare("INSERT INTO prenotazioni (id_utente, id_libro, data_richiesta) VALUES (?, ?, NOW())");
                $stmt->execute([$userId, $bookId]);
                
                $message = "✅ Prenotazione creata con successo per Utente ID $userId su Libro ID $bookId";
                $msgType = 'success';
                break;
                
            case 'cancel_reservation':
                $reservationId = $_POST['reservation_id'];
                $stmt = $db->prepare("DELETE FROM prenotazioni WHERE id_prenotazione = ?");
                $stmt->execute([$reservationId]);
                $message = "🗑️ Prenotazione #$reservationId cancellata.";
                $msgType = 'success';
                break;

            case 'simulate_return':
                $copyId = $_POST['copy_id'];
                
                // Verifica preliminare stato copia
                $stmt = $db->prepare("SELECT stato FROM inventari WHERE id_inventario = ?");
                $stmt->execute([$copyId]);
                $stato = $stmt->fetchColumn();
                
                if ($stato !== 'IN_PRESTITO') {
                    throw new Exception("La copia #$copyId non risulta 'IN_PRESTITO' (Stato attuale: $stato). Impossibile restituire.");
                }

                // Usa il metodo registraRestituzione del LoanService che contiene la logica di assegnazione
                $result = $loanService->registraRestituzione($copyId, 'BUONO', 'Simulazione debug da pannello');
                
                $message = "🔄 Restituzione registrata. ";
                
                // Analizza i messaggi restituiti dal service per capire se è scattata la prenotazione
                $assigned = false;
                foreach ($result['messaggi'] as $msg) {
                    if (strpos($msg, 'riservato per') !== false || strpos($msg, 'Prenotazione') !== false) {
                        $assigned = true;
                    }
                    $message .= " " . $msg;
                }
                
                if ($assigned) {
                    $message = "🎉 <strong>COPIA ASSEGNATA A PRENOTAZIONE!</strong> " . $message;
                    $msgType = 'success';
                } else {
                    $message .= " (Nessuna prenotazione in coda, copia tornata disponibile)";
                    $msgType = 'info';
                }
                break;
        }
    } catch (Exception $e) {
        $message = "❌ Errore: " . $e->getMessage();
        $msgType = 'error';
    }
}

// Recupera dati per la visualizzazione
try {
    // Recupera lista libri (per riferimento ID)
    $booksData = $bookModel->searchBooks(1, 50, [])['data'];
    
    // Recupera prenotazioni esistenti
    $stmt = $db->query("
        SELECT p.*, u.nome, u.cognome, l.titolo 
        FROM prenotazioni p
        JOIN utenti u ON p.id_utente = u.id_utente
        JOIN libri l ON p.id_libro = l.id_libro
        ORDER BY p.copia_libro DESC, p.data_richiesta ASC
    ");
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recupera copie in prestito (per testare il rientro)
    $stmt = $db->query("
        SELECT i.id_inventario, l.titolo, u.cognome 
        FROM inventari i
        JOIN libri l ON i.id_libro = l.id_libro
        JOIN prestiti p ON i.id_inventario = p.id_inventario
        JOIN utenti u ON p.id_utente = u.id_utente
        WHERE i.stato = 'IN_PRESTITO' AND p.data_restituzione IS NULL
        LIMIT 20
    ");
    $loanedCopies = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message .= " | Errore caricamento dati: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Debug Prenotazioni - Epic 6</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: sans-serif; background-color: #f4f6f9; padding: 20px; }
        .debug-container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 0; }
        .debug-section { border: 1px solid #e0e0e0; padding: 20px; margin-bottom: 25px; border-radius: 5px; background: #fff; }
        .debug-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .debug-table th, .debug-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .debug-table th { background-color: #f8f9fa; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { padding: 8px 15px; cursor: pointer; border-radius: 4px; border: none; font-size: 14px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-warning { background-color: #ffc107; color: #212529; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { padding: 8px; width: 100%; max-width: 300px; border: 1px solid #ccc; border-radius: 4px; }
        .badge { padding: 3px 8px; border-radius: 10px; font-size: 12px; color: white; }
        .badge-waiting { background-color: #ffc107; color: #333; }
        .badge-assigned { background-color: #28a745; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../src/Views/layout/header.php'; ?>

    <div class="debug-container">
        <h1>🛠️ Debug Sistema Prenotazioni (Epic 6)</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msgType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            
            <!-- Sezione 1: Crea Prenotazione -->
            <div class="debug-section" style="flex: 1; min-width: 300px;">
                <h2>1. Crea Nuova Prenotazione</h2>
                <p>Simula un utente che prenota un libro non disponibile.</p>
                <form method="POST" action="?action=create_reservation">
                    <div class="form-group">
                        <label>ID Utente:</label>
                        <input type="number" name="user_id" required placeholder="Es. 1">
                    </div>
                    
                    <div class="form-group">
                        <label>ID Libro (Titolo):</label>
                        <select name="book_id" required>
                            <option value="">-- Seleziona Libro --</option>
                            <?php foreach ($booksData as $book): ?>
                                <option value="<?php echo $book['id_libro']; ?>">
                                    <?php echo $book['id_libro'] . ' - ' . htmlspecialchars(substr($book['titolo'], 0, 40)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Crea Prenotazione</button>
                </form>
            </div>

            <!-- Sezione 3: Simulazione Rientro Libro -->
            <div class="debug-section" style="flex: 1; min-width: 300px; border-color: #ffc107;">
                <h2>2. Simula Rientro (Trigger)</h2>
                <p>Restituisci una copia per vedere se viene assegnata a una prenotazione.</p>
                <form method="POST" action="?action=simulate_return">
                    <div class="form-group">
                        <label>Copia in Prestito (Inventario):</label>
                        <select name="copy_id" required>
                            <option value="">-- Seleziona Copia da Restituire --</option>
                            <?php foreach ($loanedCopies as $copy): ?>
                                <option value="<?php echo $copy['id_inventario']; ?>">
                                    ID: <?php echo $copy['id_inventario']; ?> - <?php echo htmlspecialchars(substr($copy['titolo'], 0, 30)); ?> (Utente: <?php echo htmlspecialchars($copy['cognome']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-warning">Simula Rientro & Verifica Trigger</button>
                </form>
            </div>
        </div>

        <!-- Sezione 2: Lista Prenotazioni Attive -->
        <div class="debug-section">
            <h2>3. Monitoraggio Coda Prenotazioni</h2>
            <table class="debug-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utente</th>
                        <th>Libro</th>
                        <th>Data Richiesta</th>
                        <th>Stato</th>
                        <th>Scadenza Ritiro</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservations)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #777;">Nessuna prenotazione attiva nel sistema.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reservations as $res): ?>
                            <tr style="<?php echo $res['copia_libro'] ? 'background-color: #e8f5e9;' : ''; ?>">
                                <td><?php echo $res['id_prenotazione']; ?></td>
                                <td><?php echo htmlspecialchars($res['nome'] . ' ' . $res['cognome']); ?> <small>(ID: <?php echo $res['id_utente']; ?>)</small></td>
                                <td><?php echo htmlspecialchars($res['titolo']); ?> <small>(ID: <?php echo $res['id_libro']; ?>)</small></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($res['data_richiesta'])); ?></td>
                                <td>
                                    <?php if ($res['copia_libro']): ?>
                                        <span class="badge badge-assigned">PRONTO AL RITIRO</span><br>
                                        <small>Copia ID: <?php echo $res['copia_libro']; ?></small>
                                    <?php else: ?>
                                        <span class="badge badge-waiting">IN ATTESA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($res['scadenza_ritiro']) {
                                        $scad = strtotime($res['scadenza_ritiro']);
                                        echo date('d/m/Y H:i', $scad);
                                        if (time() > $scad) echo " <span style='color:red'>(SCADUTA)</span>";
                                    } else {
                                        echo "-";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <form method="POST" action="?action=cancel_reservation" style="display:inline;">
                                        <input type="hidden" name="reservation_id" value="<?php echo $res['id_prenotazione']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler cancellare questa prenotazione?')">Cancella</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <?php include __DIR__ . '/../src/Views/layout/footer.php'; ?>
</body>
</html>
