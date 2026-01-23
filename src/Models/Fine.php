<?php

namespace Ottaviodipisa\StackMasters\Models;

use PDO;

/**
 * Modello per la gestione finanziaria (Epic 9).
 * Coerente con lo schema install.sql: tabelle e campi in minuscolo.
 */
class Fine
{
    private PDO $db;

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
        $sql = "SELECT id_utente, nome, cognome, email, 
                (SELECT IFNULL(SUM(importo), 0) FROM multe WHERE id_utente = :uid1 AND data_pagamento IS NULL) as debito_totale
                FROM utenti WHERE id_utente = :uid2";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Recupera solo i dati anagrafici dell'utente.
     * Utile per la generazione di ricevute PDF.
     */
    public function getUserData(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT id_utente, nome, cognome, email, cf FROM utenti WHERE id_utente = :uid");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Calcola il totale del debito pendente per un utente.
     */
    public function getTotalPendingAmount(int $userId): float
    {
        $stmt = $this->db->prepare("SELECT IFNULL(SUM(importo), 0) FROM multe WHERE id_utente = :uid AND data_pagamento IS NULL");
        $stmt->execute(['uid' => $userId]);
        return (float)$stmt->fetchColumn();
    }

    /** Dettaglio pendenze non saldate (dove data_pagamento IS NULL) */
    public function getPendingDetails(int $userId): array
    {
        $sql = "SELECT id_multa, importo, causa, data_creazione, commento, importo as residuo
                FROM multe 
                WHERE id_utente = :uid AND data_pagamento IS NULL 
                ORDER BY data_creazione";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Algoritmo Affidabilità: basato sulla tabella 'prestiti' */
    public function getLoyaltyDiscount(int $userId): float
    {
        $sql = "SELECT COUNT(*) as tot, 
                SUM(IF(data_restituzione > scadenza_prestito, 1, 0)) as rit
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
        $sql = "INSERT INTO multe (id_utente, importo, causa, commento, data_creazione) 
                VALUES (:uid, :amt, :re, :commento, NOW())";
        return $this->db->prepare($sql)->execute([
            'uid' => $userId,
            'amt' => $amt,
            're' => strtoupper($reason),
            'commento' => $commento
        ]);
    }

    /** Registrazione pagamento: imposta data_pagamento = NOW() sulle multe più vecchie
     * @throws \Exception
     */
    public function processPayment(int $userId, float $amount): array
    {
        // Controlla se una transazione è già attiva prima di iniziarne una nuova.
        $isTransactionManagedExternally = $this->db->inTransaction();

        if (!$isTransactionManagedExternally) {
            $this->db->beginTransaction();
        }

        try {
            $pending = $this->getPendingDetails($userId);
            $rem = $amount;
            $paid = [];

            foreach ($pending as $f) {
                if ($rem <= 0) break;

                $importoMulta = (float)$f['importo'];

                if ($rem >= $importoMulta) {
                    // Pagamento totale della multa
                    $this->db->prepare("UPDATE multe SET data_pagamento = NOW() WHERE id_multa = :id")
                        ->execute(['id' => $f['id_multa']]);
                    
                    $rem -= $importoMulta;
                    $paid[] = ['id' => $f['id_multa'], 'versato' => $importoMulta];
                } else {
                    // Pagamento parziale
                    // 1. Riduciamo l'importo della multa originale (che rimane non pagata)
                    $nuovoImporto = $importoMulta - $rem;
                    $this->db->prepare("UPDATE multe SET importo = :imp WHERE id_multa = :id")
                        ->execute(['imp' => $nuovoImporto, 'id' => $f['id_multa']]);

                    // 2. Creiamo una nuova entry per la parte pagata (così appare nei report)
                    $this->db->prepare("INSERT INTO multe (id_utente, importo, causa, commento, data_creazione, data_pagamento) 
                                        VALUES (:uid, :imp, :causa, :comm, :creata, NOW())")
                        ->execute([
                            'uid' => $userId,
                            'imp' => $rem,
                            'causa' => $f['causa'],
                            'comm' => $f['commento'] . " (Pagamento Parziale)",
                            'creata' => $f['data_creazione']
                        ]);

                    $paid[] = ['id' => $f['id_multa'], 'versato' => $rem, 'parziale' => true];
                    $rem = 0;
                }
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

    /**
     * Salda TUTTE le multe pendenti di un utente in un colpo solo.
     * Usato dal controller per il pagamento rapido totale.
     */
    public function settleAllFines(int $userId): bool
    {
        return $this->db->prepare("UPDATE multe SET data_pagamento = NOW() WHERE id_utente = :uid AND data_pagamento IS NULL")
            ->execute(['uid' => $userId]);
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