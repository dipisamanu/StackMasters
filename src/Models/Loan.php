<?php
/**
 * Loan.php - Modello di Business Avanzato per i Prestiti e Restituzioni
 * Percorso: src/Models/Loan.php
 * Sincronizzato con install.sql e struttura Git: utenti_ruoli, multe, prestiti, inventari, libri, prenotazioni.
 */

namespace Ottaviodipisa\StackMasters\Models;

// La classe Database è globale (definita in src/config/database.php)
use \Database;
use \Exception;
use \PDO;

class Loan
{
    private PDO $db;

    // Costanti di configurazione business (Regolamento Biblioteca)
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
    // FUNZIONE 1: REGISTRA PRESTITO (Epic 5.2 - 5.6)
    // =========================================================================================

    /**
     * Registra un nuovo prestito in modo atomico con controlli su limiti, multe e prenotazioni.
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
    // FUNZIONE 2: REGISTRA RESTITUZIONE (Epic 5.9 - 5.11)
    // =========================================================================================

    /**
     * Registra il rientro fisico, calcola ritardi e danni, e gestisce la coda prenotazioni.
     */
    public function registraRestituzione(int $inventarioId, string $condizione, ?string $dannoCommento = null): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Recupera il Prestito Attivo e il VALORE LIBRO (fondamentale per le penali nel PDF)
            $sqlP = "SELECT p.*, l.valore_copertina, l.id_libro 
                     FROM prestiti p 
                     JOIN inventari i ON p.id_inventario = i.id_inventario
                     JOIN libri l ON i.id_libro = l.id_libro
                     WHERE p.id_inventario = :iid AND p.data_restituzione IS NULL";
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
                    $multaRitardo = $applicabili * self::MULTA_GIORNALIERA;
                    $this->registraMulta($utenteId, $multaRitardo, 'RITARDO', "Ritardo di {$giorniRitardo} gg.", $giorniRitardo);
                    $multaTotale += $multaRitardo;
                    $messaggi[] = "Ritardo di {$giorniRitardo} giorni. Multa: {$multaRitardo} €.";
                }
            }

            // 3. Calcolo Penali per Danni/Perdita (5.11)
            $valore = (float)($prestito['valore_copertina'] ?? 0);
            $penaleStato = 0.0;

            if ($valore > 0) {
                switch (strtoupper($condizione)) {
                    case 'USURATO':    $penaleStato = round($valore * 0.10, 2); break;
                    case 'DANNEGGIATO': $penaleStato = round($valore * 0.50, 2); break;
                    case 'SMARRITO':      $penaleStato = $valore; break;
                }
            }

            if ($penaleStato > 0) {
                $this->registraMulta($utenteId, $penaleStato, 'DANNI', "Stato: {$condizione}. {$dannoCommento}");
                $multaTotale += $penaleStato;
                $messaggi[] = "Penale per {$condizione}: {$penaleStato} €.";
            }

            // 4. Chiusura record Prestito
            $this->db->prepare("UPDATE prestiti SET data_restituzione = NOW() WHERE id_prestito = ?")
                ->execute([$prestito['id_prestito']]);

            // 5. Gestione Coda Prenotazioni (FIFO)
            $successore = $this->assegnaProssimaPrenotazione((int)$prestito['id_libro'], $inventarioId);

            if (!$successore) {
                // Se non ci sono prenotazioni, torna DISPONIBILE con la nuova condizione
                $this->db->prepare("UPDATE inventari SET stato = 'DISPONIBILE', condizione = :cond, ultimo_aggiornamento = NOW() WHERE id_inventario = :iid")
                    ->execute(['cond' => $condizione, 'iid' => $inventarioId]);
            } else {
                $messaggi[] = "Riservato a Utente ID: {$successore['id_utente']} per 48h.";
            }

            // 6. Gamification: Aggiornamento streak
            $this->aggiornaStatisticheRestituzione($utenteId, $giorniRitardo);

            $this->db->commit();

            return [
                'status' => 'success',
                'multa_generata' => (float)$multaTotale,
                'messaggi' => $messaggi,
                'assegnato_a_prenotazione' => $successore !== null
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =========================================================================================
    // FUNZIONE 3: RINNOVO E DASHBOARD (Epic 5.12 - 5.13)
    // =========================================================================================

    public function rinnovaPrestito(int $prestitoId, int $utenteId): array
    {
        $this->db->beginTransaction();
        try {
            $sql = "SELECT p.*, r.durata_prestito FROM prestiti p 
                    JOIN inventari i ON p.id_inventario = i.id_inventario
                    JOIN utenti_ruoli ur ON p.id_utente = ur.id_utente
                    JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
                    WHERE p.id_prestito = :pid AND p.id_utente = :uid AND p.data_restituzione IS NULL";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['pid' => $prestitoId, 'uid' => $utenteId]);
            $pInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pInfo) throw new Exception("Rinnovo non possibile.");

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
        $sql = "SELECT p.*, l.titolo, l.immagine_copertina, GROUP_CONCAT(a.cognome SEPARATOR ', ') as autori,
                       TIMESTAMPDIFF(DAY, NOW(), p.scadenza_prestito) as giorni_rimanenti
                FROM prestiti p 
                JOIN inventari i ON p.id_inventario = i.id_inventario 
                JOIN libri l ON i.id_libro = l.id_libro
                LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
                LEFT JOIN autori a ON la.id_autore = a.id
                WHERE p.id_utente = ? AND p.data_restituzione IS NULL
                GROUP BY p.scadenza_prestito
                ORDER BY p.scadenza_prestito";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$utenteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================================
    // FUNZIONE 4: REPORTING (Epic 10)
    // =========================================================================================

    /**
     * Recupera il trend dei prestiti per i KPI amministrativi.
     */
    public function getLoanTrend(): array
    {
        $sql = "SELECT DATE_FORMAT(data_prestito, '%b') as mese, COUNT(*) as totale 
                FROM prestiti 
                WHERE data_prestito > DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY MONTH(data_prestito) 
                ORDER BY data_prestito ASC";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'labels' => array_column($results, 'mese'),
            'data' => array_column($results, 'totale')
        ];
    }

    // =========================================================================================
    // METODI HELPER PRIVATI
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
        // FIX HY093: Parametri posizionali per evitare collisioni in emulazione OFF
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
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new Exception("Copia fisica non trovata.");
    }

    private function registraMulta(int $uid, float $amt, string $causa, string $note, int $gg = 0): void {
        $sql = "INSERT INTO multe (id_utente, importo, causa, commento, giorni, data_creazione) VALUES (?, ?, ?, ?, ?, NOW())";
        $this->db->prepare($sql)->execute([$uid, $amt, $causa, $note, $gg]);
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

    private function gestisciPrenotazioniPrimaDelPrestito(int $libroId, int $inventarioId, int $utenteId): void {
        $this->db->prepare("DELETE FROM prenotazioni WHERE id_libro = ? AND id_utente = ? AND copia_libro IS NULL")
            ->execute([$libroId, $utenteId]);

        $sqlCheck = "SELECT id_utente FROM prenotazioni WHERE copia_libro = ? AND scadenza_ritiro > NOW() AND id_utente != ?";
        $stmt = $this->db->prepare($sqlCheck);
        $stmt->execute([$inventarioId, $utenteId]);
        if ($id = $stmt->fetchColumn()) throw new Exception("Questa copia è riservata per il ritiro dell'utente ID: $id");
    }

    private function aggiornaStatisticheRestituzione(int $utenteId, int $ritardo): void {
        $sql = "UPDATE utenti_ruoli SET streak_restituzioni = CASE WHEN :r = 0 THEN streak_restituzioni + 1 ELSE 0 END WHERE id_utente = :u";
        $this->db->prepare($sql)->execute(['r' => $ritardo, 'u' => $utenteId]);
    }
}