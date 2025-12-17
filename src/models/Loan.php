<?php

// Riferimento al Singleton per la connessione al DB

class PrestitoManager
{
    private \PDO $db;
    // Costanti di configurazione
    private const MULTA_GIORNALIERA = 0.50;
    private const TOLLERANZA_RITARDO_GG = 3;
    private const RITIRO_PRENOTAZIONE_ORE = 48;

    /**
     * Costruttore: recupera l'istanza PDO tramite il Singleton Database.
     */
    public function __construct()
    {
        // Ottiene la connessione dal Singleton
        $this->db = Database::getInstance()->getConnection();
    }

    // =========================================================================================
    // FUNZIONE 1: REGISTRA PRESTITO (Sub-issues 5.2, 5.3, 5.4, 5.5, 5.6)
    // =========================================================================================

    /**
     * Registra un nuovo prestito in modo atomico, eseguendo tutti i controlli necessari.
     * @param int $utenteId ID dell'utente
     * @param int $inventarioId ID della copia fisica
     * @throws \Exception In caso di fallimento o violazione delle regole di business.
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        $ruoli = $this->getRuoliUtente($utenteId);
        $limitePrestiti = 0;
        $durataPrestitoGiorni = 0;

        if (empty($ruoli)) {
            throw new \Exception("L'utente non ha ruoli assegnati. Impossibile stabilire i limiti di prestito.");
        }

        // Determina il limite e la durata più permissivi
        foreach ($ruoli as $ruolo) {
            $limitePrestiti = max($limitePrestiti, $ruolo['limite_prestiti']);
            $durataPrestitoGiorni = max($durataPrestitoGiorni, $ruolo['durata_prestito']);
        }

        // Avvia la Transazione Atomica (Sub-issue 5.6)
        $this->db->beginTransaction();

        try {
            // --- CONTROLLI PRELIMINARI ---
            // 5.3: Blocco preventivo per multe/prestiti scaduti
            if ($this->checkMultePendenti($utenteId)) {
                throw new \Exception("Blocco preventivo: Utente ha multe non saldate o prestiti scaduti.");
            }

            // 5.2: Check limiti prestiti per ruolo
            $prestitiAttivi = $this->getConteggioPrestitiAttivi($utenteId);
            if ($prestitiAttivi >= $limitePrestiti) {
                throw new \Exception("L'utente ha raggiunto il limite massimo di {$limitePrestiti} prestiti attivi.");
            }

            // Check disponibilità copia
            $copia = $this->getCopiaInfo($inventarioId);
            if ($copia['stato'] !== 'DISPONIBILE' && $copia['stato'] !== 'PRENOTATO') {
                throw new \Exception("La copia (ID: {$inventarioId}) non è disponibile per il prestito.");
            }

            // 5.5: Calcolo Scadenza
            $dataScadenza = date('Y-m-d H:i:s', strtotime("+$durataPrestitoGiorni days"));

            // 5.4: Check prenotazioni prioritarie (gestisce se la copia era riservata ad altri)
            $this->gestisciPrenotazioniPrimaDelPrestito($copia['id_libro'], $inventarioId, $utenteId);


            // --- ESECUZIONE TRANSAZIONE ---

            // A. Inserimento del record Prestito
            $sqlPrestito = "INSERT INTO Prestiti (id_inventario, id_utente, data_prestito, scadenza_prestito) 
                            VALUES (:inventario_id, :utente_id, NOW(), :data_scadenza)";
            $stmtPrestito = $this->db->prepare($sqlPrestito);
            $stmtPrestito->execute([
                'inventario_id' => $inventarioId,
                'utente_id' => $utenteId,
                'data_scadenza' => $dataScadenza
            ]);
            $prestitoId = $this->db->lastInsertId();

            // B. Aggiornamento stato della Copia (a IN_PRESTITO)
            $sqlCopia = "UPDATE Inventario SET stato = 'IN_PRESTITO' WHERE id_inventario = :inventario_id";
            $stmtCopia = $this->db->prepare($sqlCopia);
            $stmtCopia->execute(['inventario_id' => $inventarioId]);

            // C. Aggiornamento statistiche utente (prestiti totali)
            $sqlStats = "UPDATE Utente_Ruolo ur 
                         JOIN Ruolo r ON ur.id_ruolo = r.id_ruolo
                         SET ur.prestiti_tot = ur.prestiti_tot + 1 
                         WHERE ur.id_utente = :uid AND r.durata_prestito = :durata";
            $stmtStats = $this->db->prepare($sqlStats);
            $stmtStats->execute(['uid' => $utenteId, 'durata' => $durataPrestitoGiorni]);

            // Commit Transaction
            $this->db->commit();

            return [
                'status' => 'success',
                'prestito_id' => $prestitoId,
                'data_scadenza' => $dataScadenza,
                'messaggio' => "Prestito registrato per {$durataPrestitoGiorni} giorni."
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("ERRORE PRESTITO: " . $e->getMessage());
        }
    }


    // =========================================================================================
    // FUNCTION 2: REGISTRA RESTITUZIONE (Sub-issues 5.9, 5.10, 5.11)
    // =========================================================================================

    /**
     * Registra la restituzione di una copia e gestisce multe e prenotazioni.
     */
    public function registraRestituzione(int $inventarioId, string $condizione, ?string $dannoCommento = null): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Recupera il Prestito Attivo (Sub-issue 5.9)
            $sqlPrestito = "SELECT * FROM Prestiti 
                            WHERE id_inventario = :iid AND data_restituzione IS NULL";
            $stmtPrestito = $this->db->prepare($sqlPrestito);
            $stmtPrestito->execute(['iid' => $inventarioId]);
            $prestitoAttivo = $stmtPrestito->fetch();

            if (!$prestitoAttivo) {
                throw new \Exception("Nessun prestito attivo trovato per questa copia (ID: {$inventarioId}).");
            }

            $utenteId = $prestitoAttivo['id_utente'];
            $multaTotale = 0.0;
            $messaggi = [];

            // 2. Calcolo Ritardi e Multe (Sub-issue 5.10)
            $scadenzaTimestamp = strtotime($prestitoAttivo['scadenza_prestito']);
            $ritornoTimestamp = time();
            $giorniInRitardo = 0;

            if ($ritornoTimestamp > $scadenzaTimestamp) {
                $diffSeconds = $ritornoTimestamp - $scadenzaTimestamp;
                $giorniInRitardo = (int)ceil($diffSeconds / (60 * 60 * 24));

                if ($giorniInRitardo > self::TOLLERANZA_RITARDO_GG) {
                    $ritardoGiorniApplicabili = $giorniInRitardo - self::TOLLERANZA_RITARDO_GG;
                    $multaRitardo = $ritardoGiorniApplicabili * self::MULTA_GIORNALIERA;
                    $this->registraMultaRitardo($utenteId, $ritardoGiorniApplicabili, $multaRitardo);
                    $multaTotale += $multaRitardo;
                    $messaggi[] = "ATTENZIONE: Ritardo di {$giorniInRitardo} giorni. Multa per ritardo accumulata: {$multaRitardo} €.";
                }
            }

            // 3. Gestione Penale per Danni (Sub-issue 5.11)
            if ($condizione === 'DANNEGGIATO' || $condizione === 'PERSO') {
                $costoDanno = $this->calcolaCostoDanno($inventarioId, $condizione);
                $messaggi[] = "PENALE: Condizione '{$condizione}' registrata. Costo penale: {$costoDanno} €.";
                $this->registraMultaDanni($utenteId, $costoDanno, $condizione, $dannoCommento);
                $multaTotale += $costoDanno;
            }

            // 4. Aggiornamento record Prestito (Chiusura)
            $sqlChiusuraPrestito = "UPDATE Prestiti 
                                    SET data_restituzione = NOW()
                                    WHERE id_prestito = :pid";
            $stmtChiusuraPrestito = $this->db->prepare($sqlChiusuraPrestito);
            $stmtChiusuraPrestito->execute(['pid' => $prestitoAttivo['id_prestito']]);

            // 5. Aggiornamento stato Inventario e condizione
            $sqlAggiornaInventario = "UPDATE Inventario 
                                      SET stato = 'DISPONIBILE', condizione = :cond, ultimo_aggiornamento = NOW() 
                                      WHERE id_inventario = :iid";
            $stmtAggiornaInventario = $this->db->prepare($sqlAggiornaInventario);
            $stmtAggiornaInventario->execute(['cond' => $condizione, 'iid' => $inventarioId]);

            // 6. Gestione Coda di Prenotazione
            $copiaInfo = $this->getCopiaInfo($inventarioId);
            $successore = $this->assegnaProssimaPrenotazione($copiaInfo['id_libro'], $inventarioId);
            if ($successore) {
                $messaggi[] = "Prenotazione assegnata a Utente ID: {$successore['id_utente']}. Ritiro entro: " . date('d/m/Y H:i', strtotime($successore['scadenza_ritiro']));
            }

            // 7. Aggiornamento statistiche utente (streak restituzioni)
            $this->aggiornaStatisticheRestituzione($utenteId, $giorniInRitardo);

            // 8. Commit Transaction
            $this->db->commit();

            return [
                'status' => 'success',
                'multa_totale' => number_format($multaTotale, 2),
                'messaggi' => $messaggi,
                'assegnato_a_prenotazione' => $successore !== null
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("ERRORE RESTITUZIONE: " . $e->getMessage());
        }
    }

    // =========================================================================================
    // METODI DI SUPPORTO (Helpers)
    // =========================================================================================

    private function getRuoliUtente(int $utenteId): array {
        $sql = "SELECT r.nome, r.durata_prestito, r.limite_prestiti 
                FROM Utente_Ruolo ur JOIN Ruolo r ON ur.id_ruolo = r.id_ruolo 
                WHERE ur.id_utente = :uid ORDER BY r.priorita ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $utenteId]);
        return $stmt->fetchAll();
    }

    private function checkMultePendenti(int $utenteId): bool {
        // Verifica sia multe non saldate CHE prestiti scaduti e non restituiti
        $sql = "SELECT (
                    (SELECT COUNT(id_multa) FROM Multe WHERE id_utente = :uid AND data_pagamento IS NULL)
                    +
                    (SELECT COUNT(id_prestito) FROM Prestiti WHERE id_utente = :uid AND data_restituzione IS NULL AND scadenza_prestito < NOW())
                ) AS count_blocks";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $utenteId]);
        return $stmt->fetchColumn() > 0;
    }

    private function getConteggioPrestitiAttivi(int $utenteId): int {
        $sql = "SELECT COUNT(id_prestito) FROM Prestiti WHERE id_utente = :uid AND data_restituzione IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $utenteId]);
        return (int)$stmt->fetchColumn();
    }

    private function getCopiaInfo(int $inventarioId): array {
        $stmt = $this->db->prepare("SELECT id_inventario, id_libro, stato, condizione FROM Inventario WHERE id_inventario = :iid");
        $stmt->execute(['iid' => $inventarioId]);
        $data = $stmt->fetch();
        if (!$data) throw new \Exception("Copia fisica (Inventario ID) non trovata.");
        return $data;
    }

    private function registraMultaRitardo(int $utenteId, int $giorniRitardo, float $importo): void {
        $sql = "INSERT INTO Multe (id_utente, giorni, importo, causa, commento)
                VALUES (:uid, :giorni, :importo, 'RITARDO', :commento)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'uid' => $utenteId,
            'giorni' => $giorniRitardo,
            'importo' => $importo,
            'commento' => "Multa generata per {$giorniRitardo} giorni di ritardo (tolleranza esclusa)."
        ]);
    }

    private function registraMultaDanni(int $utenteId, float $importo, string $condizione, ?string $commento): void {
        $sql = "INSERT INTO Multe (id_utente, importo, causa, commento)
                VALUES (:uid, :importo, 'DANNI', :commento)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'uid' => $utenteId,
            'importo' => $importo,
            'commento' => "Penale per libro reso come '{$condizione}'. Dettagli: {$commento}"
        ]);
    }

    private function calcolaCostoDanno(int $inventarioId, string $condizione): float {
        // Retrieve the cover value from the Book
        $sql = "SELECT l.valore_copertina FROM Libro l JOIN Inventario i ON l.id_libro = i.id_libro WHERE i.id_inventario = :iid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['iid' => $inventarioId]);
        $valoreCopertina = (float)$stmt->fetchColumn();

        if ($condizione === 'PERSO') return $valoreCopertina;
        // 50% of the value for serious damage
        if ($condizione === 'DANNEGGIATO') return round($valoreCopertina * 0.50, 2);
        return 0.0;
    }

    private function gestisciPrenotazioniPrimaDelPrestito(int $libroId, int $inventarioId, int $utenteId): void {
        // 1. Rimuove eventuali prenotazioni attive dell'utente per quel libro
        $sqlDelete = "DELETE FROM Prenotazioni WHERE id_libro = :lid AND id_utente = :uid AND copia_libro IS NULL";
        $stmtDelete = $this->db->prepare($sqlDelete);
        $stmtDelete->execute(['lid' => $libroId, 'uid' => $utenteId]);

        // 2. Verifica se la copia era già riservata a un altro utente
        $sqlCheck = "SELECT id_utente FROM Prenotazioni WHERE copia_libro = :iid AND scadenza_ritiro > NOW() AND id_utente != :uid";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute(['iid' => $inventarioId, 'uid' => $utenteId]);

        if ($idUtenteRiservato = $stmtCheck->fetchColumn()) {
            throw new \Exception("Questa copia è riservata per il ritiro dell'Utente ID {$idUtenteRiservato}.");
        }
    }

    private function assegnaProssimaPrenotazione(int $libroId, int $inventarioId): ?array {
        // 1. Trova il prossimo utente in coda per il titolo (FIFO)
        $sqlProssimo = "SELECT id_prenotazione, id_utente FROM Prenotazioni 
                        WHERE id_libro = :lid AND copia_libro IS NULL
                        ORDER BY data_richiesta ASC 
                        LIMIT 1";
        $stmtProssimo = $this->db->prepare($sqlProssimo);
        $stmtProssimo->execute(['lid' => $libroId]);
        $prenotazione = $stmtProssimo->fetch();

        if (!$prenotazione) return null;

        // 2. Assegna la copia e imposta la scadenza ritiro (48 ore)
        $scadenzaRitiro = date('Y-m-d H:i:s', strtotime("+" . self::RITIRO_PRENOTAZIONE_ORE . " hours"));

        $sqlUpdate = "UPDATE Prenotazioni SET 
                      copia_libro = :iid, 
                      data_disponibilita = NOW(), 
                      scadenza_ritiro = :scadenza 
                      WHERE id_prenotazione = :pid";

        $stmtUpdate = $this->db->prepare($sqlUpdate);
        $stmtUpdate->execute([
            'iid' => $inventarioId,
            'scadenza' => $scadenzaRitiro,
            'pid' => $prenotazione['id_prenotazione']
        ]);

        // 3. Update copy status to 'PRENOTATO'
        $sqlUpdateCopia = "UPDATE Inventario SET stato = 'PRENOTATO' WHERE id_inventario = :iid";
        $this->db->prepare($sqlUpdateCopia)->execute(['iid' => $inventarioId]);

        $prenotazione['scadenza_ritiro'] = $scadenzaRitiro;
        return $prenotazione;
    }

    private function aggiornaStatisticheRestituzione(int $utenteId, int $giorniRitardo): void {
        // Update "streak_restituzioni" in the Utente_Ruolo table
        $sql = "UPDATE Utente_Ruolo SET streak_restituzioni = 
                CASE 
                    WHEN :ritardo = 0 THEN streak_restituzioni + 1 
                    ELSE 0 
                END
                WHERE id_utente = :uid";
        $this->db->prepare($sql)->execute(['ritardo' => $giorniRitardo, 'uid' => $utenteId]);
    }

    // =========================================================================================
    // NUOVA FUNZIONE: RINNOVO PRESTITO (Sub-issues 5.12, 5.13)
    // =========================================================================================

    /**
     * Tenta di rinnovare un prestito attivo.
     * @param int $prestitoId ID del prestito da rinnovare.
     * @param int $utenteId ID dell'utente (per verifica sicurezza).
     * @throws \Exception In caso di violazione dei criteri di rinnovo.
     */
    public function rinnovaPrestito(int $prestitoId, int $utenteId): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Verifica Prestito, Utente e Stato
            $sqlCheck = "SELECT p.scadenza_prestito, p.id_inventario, r.durata_prestito
                         FROM Prestiti p
                         JOIN Inventario i ON p.id_inventario = i.id_inventario
                         JOIN Utente_Ruolo ur ON p.id_utente = ur.id_utente
                         JOIN Ruolo r ON ur.id_ruolo = r.id_ruolo
                         WHERE p.id_prestito = :pid 
                         AND p.id_utente = :uid 
                         AND p.data_restituzione IS NULL 
                         AND p.rinnovi < 1"; // Max un rinnovo (Regola 5.13)

            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->execute(['pid' => $prestitoId, 'uid' => $utenteId]);
            $prestitoInfo = $stmtCheck->fetch();

            if (!$prestitoInfo) {
                throw new \Exception("Rinnovo non consentito: Prestito inesistente, già rinnovato, o scaduto/restituito.");
            }

            // 2. Verifica se il libro ha prenotazioni in coda (Regola 5.13)
            $inventarioId = $prestitoInfo['id_inventario'];
            $copiaInfo = $this->getCopiaInfo($inventarioId); // Metodo esistente

            $sqlPrenotazioni = "SELECT COUNT(id_prenotazione) FROM Prenotazioni 
                                WHERE id_libro = :lid AND copia_libro IS NULL";
            $stmtPrenotazioni = $this->db->prepare($sqlPrenotazioni);
            $stmtPrenotazioni->execute(['lid' => $copiaInfo['id_libro']]);

            if ($stmtPrenotazioni->fetchColumn() > 0) {
                throw new \Exception("Rinnovo non consentito: Esistono prenotazioni in coda per questo titolo.");
            }

            // 3. Calcola nuova data di scadenza
            $durataGiorni = $prestitoInfo['durata_prestito'];
            $nuovaScadenza = date('Y-m-d H:i:s', strtotime("+" . $durataGiorni . " days"));

            // 4. Esegue l'aggiornamento
            $sqlUpdate = "UPDATE Prestiti 
                          SET scadenza_prestito = :nuova_scadenza, rinnovi = rinnovi + 1 
                          WHERE id_prestito = :pid";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute(['nuova_scadenza' => $nuovaScadenza, 'pid' => $prestitoId]);

            $this->db->commit();

            return [
                'status' => 'success',
                'nuova_scadenza' => $nuovaScadenza
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("Impossibile rinnovare: " . $e->getMessage());
        }
    }


    // =========================================================================================
    // NUOVA FUNZIONE: RECUPERA PRESTITI UTENTE (Per Dashboard)
    // =========================================================================================

    /**
     * Recupera i prestiti attivi di un utente per la dashboard.
     */
    public function getPrestitiAttiviUtente(int $utenteId): array
    {
        $sql = "SELECT p.id_prestito, p.scadenza_prestito, i.id_inventario, l.titolo, l.autore, 
                       TIMESTAMPDIFF(DAY, NOW(), p.scadenza_prestito) AS giorni_rimanenti,
                       p.rinnovi 
                FROM Prestiti p
                JOIN Inventario i ON p.id_inventario = i.id_inventario
                JOIN Libro l ON i.id_libro = l.id_libro
                WHERE p.id_utente = :uid AND p.data_restituzione IS NULL
                ORDER BY p.scadenza_prestito ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $utenteId]);

        return $stmt->fetchAll();
    }

}