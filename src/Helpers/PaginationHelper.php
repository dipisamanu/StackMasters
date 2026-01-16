<?php

namespace StackMasters\Helpers;

use PDO;
use PDOException;

class PaginationHelper
{
    /**
     * Esegue una query paginata con ricerca full-text simulata (LIKE su più colonne).
     *
     * @param PDO $db Connessione al database
     * @param string $baseSql Query SQL contenente il placeholder {WHERE}
     * @param array $searchColumns Array di colonne su cui cercare (es. ['u.nome', 'u.email'])
     * @param string $searchQuery Stringa di ricerca input utente
     * @param int $page Numero pagina corrente
     * @param int $perPage Record per pagina
     * @return array ['data' => array, 'total' => int, 'pages' => int, 'current_page' => int]
     */
    public static function paginate(PDO $db, string $baseSql, array $searchColumns, string $searchQuery, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $params = [];
        $whereClause = "";

        // Costruzione dinamica della ricerca
        if (!empty(trim($searchQuery))) {
            $keywords = array_filter(explode(' ', trim($searchQuery)));
            $conditions = [];

            foreach ($keywords as $index => $word) {
                $group = [];
                $paramName = ":word$index";
                foreach ($searchColumns as $col) {
                    // Usa CONCAT se la colonna contiene virgole (es. per cercare su più campi uniti)
                    // Ma qui assumiamo colonne singole. La logica OR tra colonne e AND tra parole.
                    $group[] = "$col LIKE $paramName";
                }
                $conditions[] = "(" . implode(' OR ', $group) . ")";
                $params[$paramName] = "%$word%";
            }

            if (!empty($conditions)) {
                // Se c'è già un WHERE nella query base (prima del placeholder), usiamo AND, altrimenti WHERE
                // Per semplicità, il placeholder {WHERE} deve gestire questo contesto o essere l'unico where.
                // Qui assumiamo che {WHERE} sostituisca l'intero blocco di filtro ricerca.
                $whereClause = "WHERE " . implode(' AND ', $conditions);
            }
        }

        // Sostituzione placeholder
        $finalSql = str_replace('{WHERE}', $whereClause, $baseSql);
        
        // Aggiunta Limit/Offset
        $finalSql .= " LIMIT :limit OFFSET :offset";

        try {
            $stmt = $db->prepare($finalSql);

            // Bind parametri ricerca
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            // Bind paginazione
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recupero totale (assumendo che la query usi COUNT(*) OVER() as total_records)
            $totalRecords = !empty($data) ? (int)$data[0]['total_records'] : 0;
            
            // Se non ci sono dati ma c'era una ricerca, il totale è 0
            if (empty($data) && $page > 1) {
                // Opzionale: gestire il caso di pagina fuori range, ma qui ritorniamo vuoto
            }

            return [
                'data' => $data,
                'total' => $totalRecords,
                'pages' => ceil($totalRecords / $perPage),
                'current_page' => $page
            ];

        } catch (PDOException $e) {
            error_log("Pagination Error: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'pages' => 0,
                'current_page' => 1
            ];
        }
    }
}
