<?php
/**
 * Dashboard Bibliotecario
 * File: dashboard/librarian/index.php
 */

session_start();

require_once '../../src/config/database.php';

// Richiedi login
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /StackMasters/public/login.php');
    exit;
}

// Verifica ruolo bibliotecario
$mainRole = $_SESSION['main_role'] ?? 'Studente';
if ($mainRole !== 'Bibliotecario' && $mainRole !== 'Admin') {
    http_response_code(403);
    die("Accesso negato. Questa pagina è riservata ai bibliotecari.");
}

$db = getDB();
$userId = $_SESSION['user_id'] ?? null;
$nomeCompleto = ($_SESSION['nome'] ?? '') . ' ' . ($_SESSION['cognome'] ?? '');

if (!$userId) {
    header('Location: /StackMasters/public/login.php');
    exit;
}

// Recupera prestiti attivi
try {
    $stmt = $db->prepare("
        SELECT 
            p.id_prestito,
            u.nome, u.cognome, u.email,
            l.titolo,
            p.scadenza_prestito,
            DATEDIFF(p.scadenza_prestito, CURDATE()) as giorni_rimanenti
        FROM Prestiti p
        INNER JOIN Utenti u ON p.id_utente = u.id_utente
        INNER JOIN Inventari i ON p.id_inventario = i.id_inventario
        INNER JOIN Libri l ON i.id_libro = l.id_libro
        WHERE p.data_restituzione IS NULL
        ORDER BY p.scadenza_prestito ASC
        LIMIT 10
    ");
    $stmt->execute();
    $presitiAttivi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Errore nel recuperare prestiti: " . $e->getMessage());
    $presitiAttivi = [];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bibliotecario - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .user-info {
            text-align: right;
        }

        .user-info p {
            color: #666;
            margin-bottom: 5px;
        }

        .btn {
            background: #bf2121;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #931b1b;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-weight: 600;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .status-late {
            color: #dc3545;
            font-weight: bold;
        }

        .status-soon {
            color: #ffc107;
            font-weight: bold;
        }

        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📖 Dashboard Bibliotecario</h1>
        <div class="user-info">
            <p>Benvenuto, <strong><?= htmlspecialchars($nomeCompleto) ?></strong></p>
            <div style="margin-top: 10px;">
                <a href="profile.php" class="btn" style="margin-right: 10px; background: #0066cc;">👤 Profilo</a>
                <a href="/StackMasters/public/logout.php" class="btn" style="background: #dc3545;">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>📋 Prestiti Attivi (Ultimi 10)</h2>
        <table>
            <thead>
                <tr>
                    <th>Utente</th>
                    <th>Email</th>
                    <th>Libro</th>
                    <th>Scadenza</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($presitiAttivi as $prestito): ?>
                    <tr>
                        <td><?= htmlspecialchars($prestito['nome'] . ' ' . $prestito['cognome']) ?></td>
                        <td><?= htmlspecialchars($prestito['email']) ?></td>
                        <td><?= htmlspecialchars($prestito['titolo']) ?></td>
                        <td><?= date('d/m/Y', strtotime($prestito['scadenza_prestito'])) ?></td>
                        <td>
                            <?php if ($prestito['giorni_rimanenti'] < 0): ?>
                                <span class="status-late">❌ Scaduto (-<?= abs($prestito['giorni_rimanenti']) ?> giorni)</span>
                            <?php elseif ($prestito['giorni_rimanenti'] <= 3): ?>
                                <span class="status-soon">⚠️ In scadenza (<?= $prestito['giorni_rimanenti'] ?> giorni)</span>
                            <?php else: ?>
                                <span class="status-ok">✅ OK (<?= $prestito['giorni_rimanenti'] ?> giorni)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($presitiAttivi)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #999;">Nessun prestito attivo</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

