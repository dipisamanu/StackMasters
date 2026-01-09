<?php
/**
 * Questa API fornisce i seguenti KPI aggiornati in formato JSON:
 * - totale prestiti attivi
 * - nuovi utenti registrati l'ultimo mese
 * - utenti attivi l'ultimo mese
 * - libri più letti
 */

require_once "./../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

function retrieveKPI(): false|string
{
    $pdo = getDB();

    $response = [];

    try {
        // totale prestiti attivi
        $stmt = $pdo->query("SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL");
        $response['prestiti_attivi'] = (int)$stmt->fetchColumn();

        // nuovi utenti
        $stmt = $pdo->query("SELECT COUNT(*) FROM utenti WHERE data_creazione >= (NOW() - INTERVAL 30 DAY)");
        $response['nuovi_utenti'] = (int)$stmt->fetchColumn();

        // utenti attivi (che hanno avuto prestiti)
        $stmt = $pdo->query("SELECT COUNT(DISTINCT id_utente) FROM prestiti WHERE data_prestito >= (NOW() - INTERVAL 30 DAY)");
        $response['utenti_attivi'] = (int)$stmt->fetchColumn();

        // libri più letti (top 5 di sempre)
        $topBooks = "
            SELECT 
                l.titolo, 
                l.copertina_url,
                COUNT(p.id_prestito) as numero_prestiti
            FROM prestiti p
            JOIN inventari i ON p.id_inventario = i.id_inventario
            JOIN libri l ON i.id_libro = l.id_libro
            GROUP BY l.id_libro, l.titolo, l.copertina_url
            ORDER BY numero_prestiti DESC
            LIMIT 5
        ";
        $stmt = $pdo->query($topBooks);
        $response['top_libri'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['generated_at'] = date('Y-m-d H:i:s');
        $response['status'] = 'success';

    } catch (PDOException $e) {
        http_response_code(500);
        return json_encode([
            'status' => 'error',
            'message' => 'Errore nel recupero dei KPI: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }

    return json_encode($response, JSON_PRETTY_PRINT);
}

// esecuzione all'import
echo retrieveKPI();