<?php

namespace Ottaviodipisa\StackMasters\Models;

/**
 * Modello per la gestione finanziaria (Epic 9).
 * Coerente con lo schema install.sql: tabelle e campi in minuscolo.
 */
class Fine
{
    private \PDO $db;

    private const MULTA_GIORNALIERA = 0.50;
    private const TOLLERANZA_RITARDO_GG = 3;

    public function __construct()
    {
        // Utilizziamo il Singleton Database configurato nel progetto
        $this->db = \Database::getInstance()->getConnection();
    }

    /** Recupera saldo e anagrafica utente dallo schema 'utenti' e 'multe' */
    public function getUserBalance(int $userId): array
    {
        // CORREZIONE: Usati due placeholder distinti (:uid1, :uid2) per evitare problemi
        // con la riutilizzazione dello stesso placeholder in alcune configurazioni PDO.
        $sql = "SELECT id_utente, nome, cognome, email, 
                (SELECT IFNULL(SUM(importo), 0) FROM multe WHERE id_utente = :uid1 AND data_pagamento IS NULL) as debito_totale
                FROM utenti WHERE id_utente = :uid2";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId]);
        return $stmt->fetch() ?: [];
    }

    /** Dettaglio pendenze non saldate (dove data_pagamento IS NULL) */
    public function getPendingDetails(int $userId): array
    {
        $sql = "SELECT id_multa, importo, causa, data_creazione, commento, importo as residuo
                FROM multe 
                WHERE id_utente = :uid AND data_pagamento IS NULL 
                ORDER BY data_creazione ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Algoritmo Affidabilità: basato sulla tabella 'prestiti' */
    public function getLoyaltyDiscount(int $userId): float
    {
        $sql = "SELECT COUNT(*) as tot, 
                SUM(CASE WHEN data_restituzione > scadenza_prestito THEN 1 ELSE 0 END) as rit
                FROM prestiti 
                WHERE id_utente = :uid AND data_prestito > DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $s = $stmt->fetch();
        return ($s && $s['tot'] >= 15 && $s['rit'] == 0) ? 0.20 : 0.0;
    }

    /** Aggiunta addebito manuale rispettando l'ENUM 'causa' ('RITARDO', 'DANNI') */
    public function addManualCharge(int $userId, float $amt, string $reason, ?string $commento = null): bool
    {
        // CORREZIONE: Aggiunto il campo 'commento' alla query e ai parametri.
        $sql = "INSERT INTO multe (id_utente, importo, causa, commento, data_creazione) 
                VALUES (:uid, :amt, :re, :commento, NOW())";
        return $this->db->prepare($sql)->execute([
            'uid' => $userId,
            'amt' => $amt,
            're' => strtoupper($reason),
            'commento' => $commento
        ]);
    }

    /** Registrazione pagamento: imposta data_pagamento = NOW() sulle multe più vecchie */
    public function processPayment(int $userId, float $amount): array
    {
        // CORREZIONE: Controlla se una transazione è già attiva prima di iniziarne una nuova.
        $isTransactionManagedExternally = $this->db->inTransaction();

        if (!$isTransactionManagedExternally) {
            $this->db->beginTransaction();
        }
        
        try {
            $pending = $this->getPendingDetails($userId);
            $rem = $amount;
            $paid = [];

            foreach ($pending as $f) {
                if ($rem < $f['importo']) break; // Supporta solo saldi totali per singola multa visto lo schema attuale

                $this->db->prepare("UPDATE multe SET data_pagamento = NOW() WHERE id_multa = :id")
                    ->execute(['id' => $f['id_multa']]);

                $rem -= $f['importo'];
                $paid[] = ['id' => $f['id_multa'], 'versato' => $f['importo']];
            }

            if (!$isTransactionManagedExternally) {
                $this->db->commit();
            }
            
            return ['success' => true, 'total' => ($amount - $rem), 'details' => $paid];
        } catch (\Exception $e) {
            if (!$isTransactionManagedExternally) {
                $this->db->rollBack();
            }
            throw $e; // Rilancia l'eccezione per farla gestire dal chiamante (es. il test)
        }
    }

    /** Report aggregato per data_pagamento */
    public function getAccountingReport(string $start, string $end): array
    {
        $sql = "SELECT DATE(data_pagamento) as data, SUM(importo) as incasso, COUNT(*) as trans 
                FROM multe 
                WHERE data_pagamento BETWEEN :s AND :e 
                GROUP BY DATE(data_pagamento) 
                ORDER BY data DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['s' => $start, 'e' => $end]);
        return $stmt->fetchAll();
    }

    /** Top 10 debitori (somma importi dove data_pagamento IS NULL) */
    public function getTopDebtors(): array
    {
        $sql = "SELECT u.id_utente, u.nome, u.cognome, SUM(m.importo) as deb 
                FROM utenti u 
                JOIN multe m ON u.id_utente = m.id_utente 
                WHERE m.data_pagamento IS NULL
                GROUP BY u.id_utente 
                ORDER BY deb DESC 
                LIMIT 10";
        return $this->db->query($sql)->fetchAll();
    }
}