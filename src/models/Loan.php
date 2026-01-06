<?php
/**
 * Loan.php - Modello di Business Avanzato per i Prestiti
 * Percorso: src/Models/Loan.php
 * Sincronizzato con install.sql: utenti_ruoli, multe, prestiti, inventari, libri, prenotazioni.
 */

namespace Ottaviodipisa\StackMasters\Models;

// La classe Database è globale (definita in src/config/database.php)
use \Database;
use \Exception;
use \PDO;

class Loan
{
    private PDO $db;

    // Costanti di configurazione business
    private const MULTA_GIORNALIERA = 0.50;
    private const TOLLERANZA_RITARDO_GG = 3;
    private const RITIRO_PRENOTAZIONE_ORE = 48;

    /**
     * Costruttore: recupera l'istanza PDO tramite il Singleton Database.
     */
    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    // =========================================================================================
    // FUNZIONE 1: REGISTRA PRESTITO (Sub-issues 5.2 - 5.6)
    // =========================================================================================

    /**
     * Registra un nuovo prestito in modo atomico con tutti i controlli di business.
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        $ruoli = $this->getRuoliUtente($utenteId);
        $limitePrestiti = 0;
        $durataPrestitoGiorni = 0;

        if (empty($ruoli)) {
            throw new Exception("L'utente non ha ruoli assegnati. Impossibile stabilire i limiti.");
        }

        // Determina il limite e la durata più permissivi tra i ruoli posseduti
        foreach ($ruoli as $ruolo) {
            $limitePrestiti = max($limitePrestiti, (int)$ruolo['limite_prestiti']);
            $durataPrestitoGiorni = max($durataPrestitoGiorni, (int)$ruolo['durata_prestito']);
        }

        $this->db->beginTransaction();

        try {
            // 5.3: Blocco per multe o prestiti scaduti
            if ($this->checkMultePendenti($utenteId)) {
                throw new Exception("Blocco preventivo: Utente ha multe non saldate o prestiti scaduti.");
            }

            // 5.2: Check limiti prestiti attivi
            $prestitiAttivi = $this->getConteggioPrestitiAttivi($utenteId);
            if ($prestitiAttivi >= $limitePrestiti) {
                throw new Exception("L'utente ha raggiunto il limite massimo di {$limitePrestiti} prestiti.");
            }

            // Check disponibilità copia (tabella inventari)
            $copia = $this->getCopiaInfo($inventarioId);
            if ($copia['stato'] !== 'DISPONIBILE' && $copia['stato'] !== 'PRENOTATO') {
                throw new Exception("La copia #{$inventarioId} non è disponibile (Stato: {$copia['stato']}).");
            }

            // 5.5: Calcolo Scadenza
            $dataScadenza = date('Y-m-d H:i:s', strtotime("+$durataPrestitoGiorni days"));

            // 5.4: Gestione prenotazioni prioritarie
            $this->gestisciPrenotazioniPrimaDelPrestito((int)$copia['id_libro'], $inventarioId, $utenteId);

            // A. Inserimento record Prestito (tabella prestiti)
            $sqlP = "INSERT INTO prestiti (id_inventario, id_utente, data_prestito, scadenza_prestito) 
                     VALUES (:iid, :uid, NOW(), :scad)";
            $this->db->prepare($sqlP)->execute([
                'iid' => $inventarioId,
                'uid' => $utenteId,
                'scad' => $dataScadenza
            ]);
            $prestitoId = $this->db->lastInsertId();

            // B. Aggiornamento stato Copia (tabella inventari)
            $this->db->prepare("UPDATE inventari SET stato = 'IN_PRESTITO' WHERE id_inventario = ?")->execute([$inventarioId]);

            // C. Aggiornamento statistiche utente (tabella utenti_ruoli)
            $sqlStats = "UPDATE utenti_ruoli ur 
                         JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
                         SET ur.prestiti_tot = ur.prestiti_tot + 1 
                         WHERE ur.id_utente = :uid AND r.durata_prestito = :durata";
            $this->db->prepare($sqlStats)->execute(['uid' => $utenteId, 'durata' => $durataPrestitoGiorni]);

            $this->db->commit();

            return [
                'status' => 'success',
                'prestito_id' => $prestitoId,
                'data_scadenza' => $dataScadenza,
                'messaggio' => "Prestito registrato per {$durataPrestitoGiorni} giorni."
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =========================================================================================
    // FUNZIONE 2: REGISTRA RESTITUZIONE (Sub-issues 5.9 - 5.11)
    // =========================================================================================

    public function registraRestituzione(int $inventarioId, string $condizione, ?string $dannoCommento = null): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Recupera il Prestito Attivo
            $sqlP = "SELECT * FROM prestiti WHERE id_inventario = :iid AND data_restituzione IS NULL";
            $stmtP = $this->db->prepare($sqlP);
            $stmtP->execute(['iid' => $inventarioId]);
            $prestito = $stmtP->fetch(PDO::FETCH_ASSOC);

            if (!$prestito) {
                throw new Exception("Nessun prestito attivo trovato per la copia #{$inventarioId}.");
            }

            $utenteId = $prestito['id_utente'];
            $multaTotale = 0.0;
            $messaggi = [];

            // 2. Calcolo Ritardi (5.10)
            $scadenzaTS = strtotime($prestito['scadenza_prestito']);
            $oggiTS = time();
            $giorniRitardo = 0;

            if ($oggiTS > $scadenzaTS) {
                $diff = $oggiTS - $scadenzaTS;
                $giorniRitardo = (int)ceil($diff / 86400);

                if ($giorniRitardo > self::TOLLERANZA_RITARDO_GG) {
                    $applicabili = $giorniRitardo - self::TOLLERANZA_RITARDO_GG;
                    $multaR = $applicabili * self::MULTA_GIORNALIERA;
                    $this->registraMulta($utenteId, $multaR, 'RITARDO', "Ritardo di {$giorniRitardo} gg.", $giorniRitardo);
                    $multaTotale += $multaR;
                    $messaggi[] = "Ritardo di {$giorniRitardo} giorni. Multa: {$multaR} €.";
                }
            }

            // 3. Penali per Danni (5.11)
            if ($condizione === 'DANNEGGIATO' || $condizione === 'PERSO') {
                $costo = $this->calcolaCostoDanno($inventarioId, $condizione);
                $this->registraMulta($utenteId, $costo, 'DANNI', "Stato: {$condizione}. {$dannoCommento}");
                $multaTotale += $costo;
                $messaggi[] = "Penale per {$condizione}: {$costo} €.";
            }

            // 4. Chiusura Prestito
            $this->db->prepare("UPDATE prestiti SET data_restituzione = NOW() WHERE id_prestito = ?")
                ->execute([$prestito['id_prestito']]);

            // 5. Aggiornamento Inventario
            $this->db->prepare("UPDATE inventari SET stato = 'DISPONIBILE', condizione = :cond, ultimo_aggiornamento = NOW() WHERE id_inventario = :iid")
                ->execute(['cond' => $condizione, 'iid' => $inventarioId]);

            // 6. Gestione Coda Prenotazioni
            $copiaInfo = $this->getCopiaInfo($inventarioId);
            $successore = $this->assegnaProssimaPrenotazione((int)$copiaInfo['id_libro'], $inventarioId);
            if ($successore) {
                $messaggi[] = "Riservato a Utente ID: {$successore['id_utente']} fino al " . date('d/m H:i', strtotime($successore['scadenza_ritiro']));
            }

            // 7. Streak Statistiche
            $this->aggiornaStatisticheRestituzione($utenteId, $giorniRitardo);

            $this->db->commit();

            return [
                'status' => 'success',
                'multa_totale' => number_format($multaTotale, 2),
                'messaggi' => $messaggi,
                'assegnato_a_prenotazione' => $successore !== null
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =========================================================================================
    // RINNOVO E DASHBOARD (5.12 - 5.13)
    // =========================================================================================

    public function rinnovaPrestito(int $prestitoId, int $utenteId): array
    {
        $this->db->beginTransaction();
        try {
            // Verifica prestito (Max 1 rinnovo, non restituito)
            // Nota: aggiungi la colonna 'rinnovi' alla tabella prestiti se non presente!
            $sql = "SELECT p.*, r.durata_prestito FROM prestiti p 
                    JOIN inventari i ON p.id_inventario = i.id_inventario
                    JOIN utenti_ruoli ur ON p.id_utente = ur.id_utente
                    JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
                    WHERE p.id_prestito = :pid AND p.id_utente = :uid AND p.data_restituzione IS NULL";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['pid' => $prestitoId, 'uid' => $utenteId]);
            $pInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pInfo) throw new Exception("Rinnovo non possibile.");

            // Check prenotazioni in coda
            $stmtQ = $this->db->prepare("SELECT COUNT(*) FROM prenotazioni WHERE id_libro = (SELECT id_libro FROM inventari WHERE id_inventario = ?) AND copia_libro IS NULL");
            $stmtQ->execute([$pInfo['id_inventario']]);
            if ($stmtQ->fetchColumn() > 0) throw new Exception("Libro prenotato da altri.");

            $nuovaScadenza = date('Y-m-d H:i:s', strtotime("+{$pInfo['durata_prestito']} days"));
            $this->db->prepare("UPDATE prestiti SET scadenza_prestito = ? WHERE id_prestito = ?")->execute([$nuovaScadenza, $prestitoId]);

            $this->db->commit();
            return ['status' => 'success', 'nuova_scadenza' => $nuovaScadenza];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getPrestitiAttiviUtente(int $utenteId): array
    {
        $sql = "SELECT p.*, l.titolo, l.immagine_copertina, TIMESTAMPDIFF(DAY, NOW(), p.scadenza_prestito) as giorni_rimanenti
                FROM prestiti p 
                JOIN inventari i ON p.id_inventario = i.id_inventario 
                JOIN libri l ON i.id_libro = l.id_libro
                WHERE p.id_utente = ? AND p.data_restituzione IS NULL
                ORDER BY p.scadenza_prestito ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$utenteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================================
    // HELPERS (SINCRONIZZATI CON SQL)
    // =========================================================================================

    private function getRuoliUtente(int $utenteId): array {
        $sql = "SELECT r.durata_prestito, r.limite_prestiti 
                FROM utenti_ruoli ur JOIN ruoli r ON ur.id_ruolo = r.id_ruolo 
                WHERE ur.id_utente = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$utenteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    private function checkMultePendenti(int $utenteId): bool {

        $sql = "SELECT (
                    (SELECT COUNT(*) FROM multe WHERE id_utente = ? AND data_pagamento IS NULL)
                    +
                    (SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL AND scadenza_prestito < NOW())
                ) as blocchi";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$utenteId, $utenteId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getConteggioPrestitiAttivi(int $utenteId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL");
        $stmt->execute([$utenteId]);
        return (int)$stmt->fetchColumn();
    }

    private function getCopiaInfo(int $inventarioId): array {
        $stmt = $this->db->prepare("SELECT * FROM inventari WHERE id_inventario = ?");
        $stmt->execute([$inventarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new Exception("Copia non trovata.");
    }

    private function registraMulta(int $uid, float $amt, string $causa, string $note, int $gg = 0): void {
        $sql = "INSERT INTO multe (id_utente, importo, causa, commento, giorni, data_creazione) VALUES (?, ?, ?, ?, ?, NOW())";
        $this->db->prepare($sql)->execute([$uid, $amt, $causa, $note, $gg]);
    }

    private function calcolaCostoDanno(int $iid, string $cond): float {
        $stmt = $this->db->prepare("SELECT l.valore_copertina FROM libri l JOIN inventari i ON l.id_libro = i.id_libro WHERE i.id_inventario = ?");
        $stmt->execute([$iid]);
        $val = (float)$stmt->fetchColumn();
        return ($cond === 'PERSO') ? $val : round($val * 0.5, 2);
    }

    private function gestisciPrenotazioniPrimaDelPrestito(int $libroId, int $inventarioId, int $utenteId): void {
        $this->db->prepare("DELETE FROM prenotazioni WHERE id_libro = ? AND id_utente = ? AND copia_libro IS NULL")
            ->execute([$libroId, $utenteId]);

        $sqlCheck = "SELECT id_utente FROM prenotazioni WHERE copia_libro = ? AND scadenza_ritiro > NOW() AND id_utente != ?";
        $stmt = $this->db->prepare($sqlCheck);
        $stmt->execute([$inventarioId, $utenteId]);
        if ($id = $stmt->fetchColumn()) throw new Exception("Copia riservata all'utente ID: $id");
    }

    private function assegnaProssimaPrenotazione(int $libroId, int $inventarioId): ?array {
        $stmt = $this->db->prepare("SELECT id_prenotazione, id_utente FROM prenotazioni WHERE id_libro = ? AND copia_libro IS NULL ORDER BY data_richiesta ASC LIMIT 1");
        $stmt->execute([$libroId]);
        $pren = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pren) return null;

        $scad = date('Y-m-d H:i:s', strtotime("+" . self::RITIRO_PRENOTAZIONE_ORE . " hours"));
        $this->db->prepare("UPDATE prenotazioni SET copia_libro = ?, data_disponibilita = NOW(), scadenza_ritiro = ? WHERE id_prenotazione = ?")
            ->execute([$inventarioId, $scad, $pren['id_prenotazione']]);
        $this->db->prepare("UPDATE inventari SET stato = 'PRENOTATO' WHERE id_inventario = ?")->execute([$inventarioId]);

        $pren['scadenza_ritiro'] = $scad;
        return $pren;
    }

    private function aggiornaStatisticheRestituzione(int $utenteId, int $ritardo): void {
        $sql = "UPDATE utenti_ruoli SET streak_restituzioni = CASE WHEN :r = 0 THEN streak_restituzioni + 1 ELSE 0 END WHERE id_utente = :u";
        $this->db->prepare($sql)->execute(['r' => $ritardo, 'u' => $utenteId]);
    }
}