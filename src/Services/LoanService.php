<?php

namespace Ottaviodipisa\StackMasters\Services;
use Database;
use PDO;
use Exception;
use Ottaviodipisa\StackMasters\Models\NotificationManager;

/**
 * LoanService - Gestisce tutta la logica di business per prestiti e restituzioni
 * Adattato allo schema database biblioteca_db
 */
class LoanService
{
    private PDO $db;
    private NotificationManager $notifier;

    // Configurazione multe
    private const GIORNI_TOLLERANZA = 3;
    private const IMPORTO_MULTA_GIORNALIERA = 0.50;

    // Configurazione prenotazioni
    private const ORE_RISERVA_PRENOTAZIONE = 48;

    public function __construct()
    {
        try {
            $this->db = \Database::getInstance()->getConnection();
            $this->notifier = new NotificationManager();
        } catch (Exception $e) {
            throw new Exception("Errore durante l'inizializzazione del controller: " . $e->getMessage());
        }
    }

    /**
     * Registra un nuovo prestito con tutti i controlli automatici
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        $this->db->beginTransaction();

        try {
            // 1. RECUPERA DATI UTENTE E LIBRO
            $utente = $this->getUtenteCompleto($utenteId);
            if (!$utente) {
                throw new Exception("Utente non trovato (ID: {$utenteId})");
            }

            $copia = $this->getCopiaConLibro($inventarioId);
            if (!$copia) {
                throw new Exception("Copia libro non trovata (ID Inventario: {$inventarioId})");
            }

            // 2. CONTROLLI PRELIMINARI
            $this->verificaMultePendenti($utenteId);
            $this->verificaBloccoAccount($utente);
            $this->verificaLimitiPrestito($utente);
            $this->verificaDisponibilitaCopia($copia);

            // 3. GESTIONE PRENOTAZIONI
            $prenotazione = $this->verificaPrenotazione($utenteId, $copia['id_libro']);

            // 4. CALCOLA DATA SCADENZA
            $dataScadenza = $this->calcolaDataScadenza($utente['durata_prestito']);

            // 5. REGISTRA IL PRESTITO
            $prestitoId = $this->creaPrestito($utenteId, $inventarioId, $dataScadenza);

            // 6. AGGIORNA STATO COPIA
            $this->aggiornaStatoCopia($inventarioId, 'IN_PRESTITO');

            // 7. INCREMENTA CONTATORE PRESTITI
            $this->incrementaPrestitiUtente($utenteId, $utente['id_ruolo']);

            // 8. GESTISCI PRENOTAZIONE
            $messaggioPrenotazione = '';
            if ($prenotazione) {
                $this->completaPrenotazione($prenotazione['id_prenotazione']);
                $messaggioPrenotazione = "Prenotazione #{$prenotazione['id_prenotazione']} completata";
            }

            // 9. NOTIFICA SUCCESSIVO IN CODA
            $this->notificaSuccessivoInCoda($copia['id_libro'], $inventarioId);

            // 10. LOG AUDIT
            $this->logAzione($utenteId, 'MODIFICA_PRESTITO', "Prestito #{$prestitoId} registrato - Libro: {$copia['titolo']}");

            // 11. COMMIT TRANSAZIONE
            $this->db->commit();

            // Notifica Email Diretta
            $this->inviaEmailConferma($utente, $copia, $dataScadenza, $prestitoId);
            // --- SISTEMA DI NOTIFICHE ---
            try {
                // Notifica interna (Campanella)
                $this->notifier->send(
                    $utenteId,
                    NotificationManager::TYPE_INFO,
                    NotificationManager::URGENCY_LOW,
                    "Prestito Confermato",
                    "Hai preso in prestito '{$copia['titolo']}'. Scadenza prevista: " . date('d/m/Y', strtotime($dataScadenza)),
                    "/dashboard/student/index.php"
                );



            } catch (Exception $e) {
                // Logghiamo l'errore ma non blocchiamo il successo del prestito
                error_log("Errore invio notifica prestito: " . $e->getMessage());
            }

            return [
                'status' => 'success',
                'message' => "Prestito registrato con successo",
                'details' => [
                    'utente' => $utente,
                    'copia' => $copia,
                    'data_scadenza' => $dataScadenza,
                    'messaggio_prenotazione' => $messaggioPrenotazione
                ]
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra una restituzione
     */
    public function registraRestituzione(int $inventarioId, string $condizione = 'BUONO', ?string $commentoDanno = null): array
    {
        $this->db->beginTransaction();

        try {
            $prestito = $this->getPrestitoAttivo($inventarioId);
            if (!$prestito) {
                throw new Exception("Nessun prestito attivo trovato per questa copia (ID: {$inventarioId})");
            }

            $giorniRitardo = $this->calcolaGiorniRitardo($prestito['scadenza_prestito']);
            $importoMulta = $this->calcolaMulta($giorniRitardo);

            $messaggi = [];
            $multaTotale = 0;

            if ($importoMulta > 0) {
                $this->registraMulta($prestito['id_utente'], $giorniRitardo, $importoMulta, 'RITARDO', null);
                $multaTotale += $importoMulta;
                $messaggi[] = "⚠️ Multa per ritardo: €{$importoMulta} ({$giorniRitardo} giorni)";
            }

            if ($condizione === 'DANNEGGIATO') {
                $costoDanno = $this->calcolaCostoDanno($prestito['id_libro']);
                $this->registraMulta($prestito['id_utente'], null, $costoDanno, 'DANNI', $commentoDanno);
                $multaTotale += $costoDanno;
                $messaggi[] = "⚠️ Costo riparazione danni: €{$costoDanno}";
            }

            $this->completaPrestito($prestito['id_prestito']);
            $this->aggiornaStatoCopia($inventarioId, 'DISPONIBILE', $condizione);

            if ($giorniRitardo <= 0) {
                $this->incrementaStreakRestituzioni($prestito['id_utente'], $prestito['id_ruolo']);
            } else {
                $this->resetStreakRestituzioni($prestito['id_utente'], $prestito['id_ruolo']);
            }

            $prenotazioneSuccessiva = $this->getPrenotazioneSuccessiva($prestito['id_libro']);
            if ($prenotazioneSuccessiva) {
                $this->assegnaPrenotazione($prenotazioneSuccessiva['id_prenotazione'], $inventarioId);
                $this->aggiornaStatoCopia($inventarioId, 'PRENOTATO');
                $messaggi[] = "📢 Libro riservato per: {$prenotazioneSuccessiva['nome']} {$prenotazioneSuccessiva['cognome']} (48h)";
            } else {
                $messaggi[] = "✅ Libro disponibile per nuovi prestiti";
            }

            $this->verificaSbloccaUtente($prestito['id_utente']);
            $this->logAzione($prestito['id_utente'], 'MODIFICA_PRESTITO', "Restituzione prestito #{$prestito['id_prestito']} - Multa: €{$multaTotale}");

            $this->db->commit();

            // Notifiche Post-Commit
            try {
                if ($multaTotale > 0 || $giorniRitardo > 0) {
                    $this->notifier->send(
                        $prestito['id_utente'],
                        NotificationManager::TYPE_REMINDER,
                        NotificationManager::URGENCY_HIGH,
                        "Restituzione Registrata (Con Addebiti)",
                        "Libro restituito. Ritardo: {$giorniRitardo}gg. Totale addebitato: €" . number_format($multaTotale, 2),
                        "/dashboard/student/index.php"
                    );
                }

                if ($prenotazioneSuccessiva) {
                    $this->notifier->send(
                        $prenotazioneSuccessiva['id_utente'],
                        NotificationManager::TYPE_INFO,
                        NotificationManager::URGENCY_LOW,
                        "Il libro che aspettavi è disponibile!",
                        "È arrivato il tuo turno. Hai 48 ore per passare in biblioteca.",
                        "/dashboard/student/index.php"
                    );
                }
            } catch (Exception $e) {
                error_log("[WARNING] Errore invio notifiche restituzione: " . $e->getMessage());
            }

            $this->inviaEmailRestituzione($prestito, $multaTotale);

            return [
                'prestito_id' => $prestito['id_prestito'],
                'multa_totale' => $multaTotale,
                'giorni_ritardo' => $giorniRitardo,
                'messaggi' => $messaggi
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // --- METODI PRIVATI DI SUPPORTO ---

    private function verificaMultePendenti(int $utenteId): void
    {
        $stmt = $this->db->prepare("SELECT SUM(importo) as totale_multe FROM multe WHERE id_utente = ? AND data_pagamento IS NULL");
        $stmt->execute([$utenteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (($result['totale_multe'] ?? 0) > 0) {
            throw new Exception("Multe pendenti: €" . number_format($result['totale_multe'], 2));
        }
    }

    private function verificaBloccoAccount(array $utente): void
    {
        if ($utente['blocco_account_fino_al'] && strtotime($utente['blocco_account_fino_al']) > time()) {
            throw new Exception("Account bloccato fino al " . date('d/m/Y H:i', strtotime($utente['blocco_account_fino_al'])));
        }
    }

    private function verificaLimitiPrestito(array $utente): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL");
        $stmt->execute([$utente['id_utente']]);
        $attivi = $stmt->fetchColumn();

        if ($attivi >= $utente['limite_prestiti']) {
            throw new Exception("Limite prestiti raggiunto ({$attivi}/{$utente['limite_prestiti']}).");
        }
    }

    private function verificaDisponibilitaCopia(array $copia): void
    {
        if ($copia['stato'] !== 'DISPONIBILE') throw new Exception("Copia non disponibile (Stato: {$copia['stato']})");
        if ($copia['condizione'] === 'DANNEGGIATO') throw new Exception("Copia danneggiata, impossibile prestare.");
        if ($copia['condizione'] === 'SMARRITO') throw new Exception("Copia smarrita.");
    }

    private function verificaPrenotazione(int $utenteId, int $libroId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM prenotazioni WHERE id_utente = ? AND id_libro = ? AND copia_libro IS NOT NULL AND scadenza_ritiro > NOW() LIMIT 1");
        $stmt->execute([$utenteId, $libroId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function creaPrestito(int $utenteId, int $inventarioId, string $dataScadenza): int
    {
        $stmt = $this->db->prepare("INSERT INTO prestiti (id_utente, id_inventario, data_prestito, scadenza_prestito) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$utenteId, $inventarioId, $dataScadenza]);
        return (int)$this->db->lastInsertId();
    }

    private function calcolaDataScadenza(int $giorni): string {
        return date('Y-m-d H:i:s', strtotime("+{$giorni} days"));
    }

    private function aggiornaStatoCopia(int $id, string $stato, ?string $cond = null): void {
        $sql = "UPDATE inventari SET stato = ?";
        $params = [$stato];
        if ($cond) { $sql .= ", condizione = ?"; $params[] = $cond; }
        $sql .= " WHERE id_inventario = ?";
        $params[] = $id;
        $this->db->prepare($sql)->execute($params);
    }

    private function incrementaPrestitiUtente(int $uid, int $rid): void {
        $this->db->prepare("UPDATE utenti_ruoli SET prestiti_tot = prestiti_tot + 1 WHERE id_utente = ? AND id_ruolo = ?")->execute([$uid, $rid]);
    }

    private function completaPrenotazione(int $pid): void {
        $this->db->prepare("UPDATE prenotazioni SET copia_libro = NULL, data_disponibilita = NULL, scadenza_ritiro = NULL WHERE id_prenotazione = ?")->execute([$pid]);
    }

    private function notificaSuccessivoInCoda(int $lid, int $iid): void {
        $pren = $this->getPrenotazioneSuccessiva($lid);
        if ($pren) {
            $this->assegnaPrenotazione($pren['id_prenotazione'], $iid);
            $this->aggiornaStatoCopia($iid, 'PRENOTATO');
            // Qui andrebbe inviata la mail di notifica disponibilità
        }
    }

    private function getPrestitoAttivo(int $iid): ?array {
        $sql = "SELECT p.*, u.nome, u.cognome, u.email, l.id_libro, l.titolo, ur.id_ruolo 
                FROM prestiti p 
                JOIN utenti u ON p.id_utente = u.id_utente 
                JOIN inventari i ON p.id_inventario = i.id_inventario 
                JOIN libri l ON i.id_libro = l.id_libro 
                LEFT JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente 
                WHERE p.id_inventario = ? AND p.data_restituzione IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$iid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function calcolaGiorniRitardo(string $scadenza): int {
        $diff = (new \DateTime())->diff(new \DateTime($scadenza));
        return (new \DateTime() > new \DateTime($scadenza)) ? $diff->days : 0;
    }

    private function calcolaMulta(int $gg): float {
        return ($gg > self::GIORNI_TOLLERANZA) ? round(($gg - self::GIORNI_TOLLERANZA) * self::IMPORTO_MULTA_GIORNALIERA, 2) : 0.0;
    }

    private function registraMulta(int $uid, ?int $gg, float $imp, string $causa, ?string $comm): void {
        $this->db->prepare("INSERT INTO multe (id_utente, giorni, importo, causa, commento, data_creazione) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$uid, $gg, $imp, $causa, $comm]);
    }

    private function completaPrestito(int $pid): void {
        $this->db->prepare("UPDATE prestiti SET data_restituzione = NOW() WHERE id_prestito = ?")->execute([$pid]);
    }

    private function incrementaStreakRestituzioni(int $uid, int $rid): void {
        $this->db->prepare("UPDATE utenti_ruoli SET streak_restituzioni = streak_restituzioni + 1 WHERE id_utente = ? AND id_ruolo = ?")->execute([$uid, $rid]);
    }

    private function resetStreakRestituzioni(int $uid, int $rid): void {
        $this->db->prepare("UPDATE utenti_ruoli SET streak_restituzioni = 0 WHERE id_utente = ? AND id_ruolo = ?")->execute([$uid, $rid]);
    }

    private function getUtenteCompleto(int $uid): ?array {
        $sql = "SELECT u.*, r.id_ruolo, r.nome as nome_ruolo, r.durata_prestito, r.limite_prestiti FROM utenti u JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente JOIN ruoli r ON ur.id_ruolo = r.id_ruolo WHERE u.id_utente = ? ORDER BY r.priorita ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getCopiaConLibro(int $iid): ?array {
        $sql = "SELECT i.*, l.id_libro, l.titolo, l.valore_copertina FROM inventari i JOIN libri l ON i.id_libro = l.id_libro WHERE i.id_inventario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$iid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getPrenotazioneSuccessiva(int $lid): ?array {
        $sql = "SELECT p.*, u.nome, u.cognome, u.email FROM prenotazioni p JOIN utenti u ON p.id_utente = u.id_utente WHERE p.id_libro = ? AND p.copia_libro IS NULL AND p.scadenza_ritiro IS NULL ORDER BY p.data_richiesta ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function assegnaPrenotazione(int $pid, int $iid): void {
        $dispo = date('Y-m-d H:i:s');
        $scad = date('Y-m-d H:i:s', strtotime('+' . self::ORE_RISERVA_PRENOTAZIONE . ' hours'));
        $this->db->prepare("UPDATE prenotazioni SET copia_libro = ?, data_disponibilita = ?, scadenza_ritiro = ? WHERE id_prenotazione = ?")->execute([$iid, $dispo, $scad, $pid]);
    }

    private function calcolaCostoDanno(int $lid): float {
        $stmt = $this->db->prepare("SELECT valore_copertina FROM libri WHERE id_libro = ?");
        $stmt->execute([$lid]);
        return (float)($stmt->fetchColumn() ?: 10.00);
    }

    private function verificaSbloccaUtente(int $uid): void {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL AND scadenza_prestito < NOW()");
        $stmt->execute([$uid]);
        if ($stmt->fetchColumn() == 0) {
            $this->db->prepare("UPDATE utenti SET blocco_account_fino_al = NULL WHERE id_utente = ?")->execute([$uid]);
        }
    }

    private function logAzione(int $uid, string $act, string $det): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ipLong = ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) ? ip2long($ip) : null;
        $this->db->prepare("INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())")->execute([$uid, $act, $det, $ipLong]);
    }

    // --- METODI NOTIFICA EMAIL ---

    private function inviaEmailConferma(array $utente, array $copia, string $dataScadenza, int $prestitoId): void
    {
        // Percorso per includere email.php che contiene la classe EmailService globale
        require_once __DIR__ . '/../config/email.php';

        try {
            // FIX: Usare \getEmailService() con la backslash perché la funzione è globale
            $emailService = \getEmailService(true);

            $emailService->sendLoanConfirmation(
                $utente['email'],
                $utente['nome'],
                $copia['titolo'],
                date('d/m/Y', strtotime($dataScadenza))
            );
        } catch (Exception $e) {
            error_log("Errore invio email di conferma prestito: " . $e->getMessage());
            // Non rilanciamo l'eccezione per non bloccare la transazione già committata
        }
    }

    private function inviaEmailRestituzione(array $prestito, float $multaTotale): void
    {
        // TODO: Implementare simile a sopra
    }
}
?>