<?php
/**
 * Loan.php - Modello di Business Avanzato per i Prestiti e Restituzioni
 * Percorso: src/Models/Loan.php
 */

namespace Ottaviodipisa\StackMasters\Models;

use Database;
use Exception;
use PDO;

class Loan
{
    private PDO $db;
    private const float MULTA_GIORNALIERA = 0.50;
    private const int TOLLERANZA_RITARDO_GG = 3;
    private const int RITIRO_PRENOTAZIONE_ORE = 48;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * @throws Exception
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        $ruoli = $this->getRuoliUtente($utenteId);
        $limitePrestiti = 0;
        $durataPrestitoGiorni = 0;

        if (empty($ruoli)) {
            throw new Exception("L'utente non ha ruoli assegnati. Impossibile stabilire i limiti.");
        }

        foreach ($ruoli as $ruolo) {
            $limitePrestiti = max($limitePrestiti, (int)$ruolo['limite_prestiti']);
            $durataPrestitoGiorni = max($durataPrestitoGiorni, (int)$ruolo['durata_prestito']);
        }

        $this->db->beginTransaction();

        try {
            if ($this->checkMultePendenti($utenteId)) {
                throw new Exception("Blocco preventivo: Utente ha multe non saldate o prestiti scaduti.");
            }

            $prestitiAttivi = $this->getConteggioPrestitiAttivi($utenteId);
            if ($prestitiAttivi >= $limitePrestiti) {
                throw new Exception("L'utente ha raggiunto il limite massimo di $limitePrestiti prestiti.");
            }

            $copia = $this->getCopiaInfo($inventarioId);
            if ($copia['stato'] !== 'DISPONIBILE' && $copia['stato'] !== 'PRENOTATO') {
                throw new Exception("La copia #$inventarioId non è disponibile (Stato: {$copia['stato']}).");
            }

            $dataScadenza = date('Y-m-d H:i:s', strtotime("+$durataPrestitoGiorni days"));
            $this->gestisciPrenotazioniPrimaDelPrestito((int)$copia['id_libro'], $inventarioId, $utenteId);

            $sqlP = "INSERT INTO prestiti (id_inventario, id_utente, data_prestito, scadenza_prestito) VALUES (:iid, :uid, NOW(), :scad)";
            $this->db->prepare($sqlP)->execute(['iid' => $inventarioId, 'uid' => $utenteId, 'scad' => $dataScadenza]);
            $prestitoId = $this->db->lastInsertId();

            $this->db->prepare("UPDATE inventari SET stato = 'IN_PRESTITO' WHERE id_inventario = ?")->execute([$inventarioId]);

            $sqlStats = "UPDATE utenti_ruoli ur JOIN ruoli r ON ur.id_ruolo = r.id_ruolo SET ur.prestiti_tot = ur.prestiti_tot + 1 WHERE ur.id_utente = :uid AND r.durata_prestito = :durata";
            $this->db->prepare($sqlStats)->execute(['uid' => $utenteId, 'durata' => $durataPrestitoGiorni]);

            $this->db->commit();

            return ['status' => 'success', 'prestito_id' => $prestitoId, 'data_scadenza' => $dataScadenza, 'messaggio' => "Prestito registrato per $durataPrestitoGiorni giorni."];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function registraRestituzione(int $inventarioId, string $condizioneRientro, ?string $dannoCommento = null): array
    {
        $this->db->beginTransaction();

        try {
            $sqlP = "SELECT p.*, l.valore_copertina, l.id_libro, i.condizione as condizione_partenza
                     FROM prestiti p 
                     JOIN inventari i ON p.id_inventario = i.id_inventario
                     JOIN libri l ON i.id_libro = l.id_libro
                     WHERE p.id_inventario = :iid AND p.data_restituzione IS NULL";
            $stmtP = $this->db->prepare($sqlP);
            $stmtP->execute(['iid' => $inventarioId]);
            $prestito = $stmtP->fetch(PDO::FETCH_ASSOC);

            if (!$prestito) {
                throw new Exception("Nessun prestito attivo trovato per la copia #$inventarioId.");
            }

            $utenteId = $prestito['id_utente'];
            $multaTotale = 0.0;
            $messaggi = [];
            $condizionePartenza = $prestito['condizione_partenza'] ?? 'BUONO';

            $scadenzaTS = strtotime($prestito['scadenza_prestito']);
            $oggiTS = time();
            $giorniRitardo = 0;

            if ($oggiTS > $scadenzaTS) {
                $diff = $oggiTS - $scadenzaTS;
                $giorniRitardo = (int)ceil($diff / 86400);
                if ($giorniRitardo > self::TOLLERANZA_RITARDO_GG) {
                    $applicabili = $giorniRitardo - self::TOLLERANZA_RITARDO_GG;
                    $multaRitardo = $applicabili * self::MULTA_GIORNALIERA;
                    $this->registraMulta($utenteId, $multaRitardo, 'RITARDO', "Ritardo di $giorniRitardo gg.", $giorniRitardo);
                    $multaTotale += $multaRitardo;
                    $messaggi[] = "Ritardo di $giorniRitardo giorni. Multa: $multaRitardo €.";
                }
            }

            $valore = (float)($prestito['valore_copertina'] ?? 0);

            $condizioniMap = ['BUONO' => 0, 'USURATO' => 1, 'DANNEGGIATO' => 2, 'SMARRITO' => 3];
            $livelloPartenza = $condizioniMap[$condizionePartenza] ?? 0;
            $livelloRientro = $condizioniMap[strtoupper($condizioneRientro)] ?? 0;

            if ($livelloRientro > $livelloPartenza) {
                if ($valore <= 0) {
                    throw new Exception("Danno rilevato ($condizioneRientro), ma impossibile calcolare la penale: il valore di copertina del libro non è impostato.");
                }

                $penaleStato = 0.0;
                switch (strtoupper($condizioneRientro)) {
                    case 'USURATO':
                        $penaleStato = round($valore * 0.10, 2);
                        break;
                    case 'DANNEGGIATO':
                        $penaleStato = round($valore * 0.50, 2);
                        break;
                    case 'SMARRITO':
                        $penaleStato = $valore;
                        break;
                }

                if ($penaleStato > 0) {
                    $this->registraMulta($utenteId, $penaleStato, 'DANNI', "Stato: $condizioneRientro. $dannoCommento");
                    $multaTotale += $penaleStato;
                    $messaggi[] = "Penale per $condizioneRientro: $penaleStato €.";
                }
            }

            $this->db->prepare("UPDATE prestiti SET data_restituzione = NOW() WHERE id_prestito = ?")->execute([$prestito['id_prestito']]);

            $successore = $this->assegnaProssimaPrenotazione((int)$prestito['id_libro'], $inventarioId);

            if (!$successore) {
                $this->db->prepare("UPDATE inventari SET stato = 'DISPONIBILE', condizione = :cond, ultimo_aggiornamento = NOW() WHERE id_inventario = :iid")->execute(['cond' => $condizioneRientro, 'iid' => $inventarioId]);
            } else {
                $messaggi[] = "Riservato a Utente ID: {$successore['id_utente']} per 48h.";
            }

            $this->aggiornaStatisticheRestituzione($utenteId, $giorniRitardo);
            $this->db->commit();

            return [
                'status' => 'success',
                'multa_generata' => $multaTotale,
                'messaggi' => $messaggi,
                'assegnato_a_prenotazione' => $successore !== null,
                'condizione_partenza' => $condizionePartenza
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function getRuoliUtente(int $utenteId): array
    {
        $stmt = $this->db->prepare("SELECT r.durata_prestito, r.limite_prestiti FROM utenti_ruoli ur JOIN ruoli r ON ur.id_ruolo = r.id_ruolo WHERE ur.id_utente = ?");
        $stmt->execute([$utenteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function checkMultePendenti(int $utenteId): bool
    {
        $stmt = $this->db->prepare("SELECT ((SELECT COUNT(*) FROM multe WHERE id_utente = ? AND data_pagamento IS NULL) + (SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL AND scadenza_prestito < NOW())) as blocchi");
        $stmt->execute([$utenteId, $utenteId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getConteggioPrestitiAttivi(int $utenteId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL");
        $stmt->execute([$utenteId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @throws Exception
     */
    private function getCopiaInfo(int $inventarioId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM inventari WHERE id_inventario = ?");
        $stmt->execute([$inventarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new Exception("Copia fisica non trovata.");
    }

    private function registraMulta(int $uid, float $amt, string $causa, string $note, int $gg = 0): void
    {
        $this->db->prepare("INSERT INTO multe (id_utente, importo, causa, commento, giorni, data_creazione) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$uid, $amt, $causa, $note, $gg]);
    }

    private function assegnaProssimaPrenotazione(int $libroId, int $inventarioId): ?array
    {
        $stmt = $this->db->prepare("SELECT id_prenotazione, id_utente FROM prenotazioni WHERE id_libro = ? AND copia_libro IS NULL ORDER BY data_richiesta LIMIT 1");
        $stmt->execute([$libroId]);
        $pren = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pren) return null;

        $scad = date('Y-m-d H:i:s', strtotime("+" . self::RITIRO_PRENOTAZIONE_ORE . " hours"));
        $this->db->prepare("UPDATE prenotazioni SET copia_libro = ?, data_disponibilita = NOW(), scadenza_ritiro = ? WHERE id_prenotazione = ?")
            ->execute([$inventarioId, $scad, $pren['id_prenotazione']]);
        $this->db->prepare("UPDATE inventari SET stato = 'PRENOTATO' WHERE id_inventario = ?")->execute([$inventarioId]);

        return $pren + ['scadenza_ritiro' => $scad];
    }

    /**
     * @throws Exception
     */
    private function gestisciPrenotazioniPrimaDelPrestito(int $libroId, int $inventarioId, int $utenteId): void
    {
        $this->db->prepare("DELETE FROM prenotazioni WHERE id_libro = ? AND id_utente = ? AND copia_libro IS NULL")
            ->execute([$libroId, $utenteId]);
        $stmt = $this->db->prepare("SELECT id_utente FROM prenotazioni WHERE copia_libro = ? AND scadenza_ritiro > NOW() AND id_utente != ?");
        $stmt->execute([$inventarioId, $utenteId]);
        if ($id = $stmt->fetchColumn()) throw new Exception("Questa copia è riservata per il ritiro dell'utente ID: $id");
    }

    private function aggiornaStatisticheRestituzione(int $utenteId, int $ritardo): void
    {
        $sql = "UPDATE utenti_ruoli SET streak_restituzioni = IF(:r = 0, streak_restituzioni + 1, 0) WHERE id_utente = :u";
        $this->db->prepare($sql)->execute(['r' => $ritardo, 'u' => $utenteId]);
    }
}