<?php


namespace Ottaviodipisa\StackMasters\Services;
use Database;
use PDO;
use Exception;

use Ottaviodipisa\StackMasters\Models\NotificationManager;
/**
 * LoanService - Gestisce tutta la logica di business per prestiti e restituzioni
 * Adattato allo schema database biblioteca_db
 * Aggiunta logica per notifiche automatiche di scadenza e ritardo prestiti
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
            $this->db = Database::getInstance()->getConnection();

            $this->notifier = new NotificationManager();
        } catch (Exception $e) {
            // Gestione degli errori
            throw new Exception("Errore durante l'inizializzazione del controller: " . $e->getMessage());
        }
    }

    /**
     * Registra un nuovo prestito con tutti i controlli automatici
     *
     * @param int $utenteId ID dell'utente (id_utente)
     * @param int $inventarioId ID della copia fisica (id_inventario)
     * @return array Dettagli del prestito registrato
     * @throws Exception Se i controlli preliminari falliscono
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        $this->db->beginTransaction();

        try {
            // 1. RECUPERA DATI UTENTE E LIBRO (Queste righe sono FONDAMENTALI per evitare l'errore)
            $utente = $this->getUtenteCompleto($utenteId);
            if (!$utente) {
                throw new Exception("Utente non trovato (ID: {$utenteId})");
            }

            $copia = $this->getCopiaConLibro($inventarioId);
            if (!$copia) {
                throw new Exception("Copia libro non trovata (ID Inventario: {$inventarioId})");
            }

            // 2. CONTROLLI PRELIMINARI AUTOMATICI
            $this->verificaMultePendenti($utenteId);
            $this->verificaBloccoAccount($utente);
            $this->verificaLimitiPrestito($utente);
            $this->verificaDisponibilitaCopia($copia);

            // 3. GESTIONE PRENOTAZIONI
            $prenotazione = $this->verificaPrenotazione($utenteId, $copia['id_libro']);

            // 4. CALCOLA DATA SCADENZA IN BASE AL RUOLO
            $dataScadenza = $this->calcolaDataScadenza($utente['durata_prestito']);

            // 5. REGISTRA IL PRESTITO (Transazione atomica)
            $prestitoId = $this->creaPrestito($utenteId, $inventarioId, $dataScadenza);

            // 6. AGGIORNA STATO COPIA
            $this->aggiornaStatoCopia($inventarioId, 'IN_PRESTITO');

            // 7. INCREMENTA CONTATORE PRESTITI UTENTE
            $this->incrementaPrestitiUtente($utenteId, $utente['id_ruolo']);

            // 8. GESTISCI PRENOTAZIONE SE PRESENTE
            $messaggioPrenotazione = '';
            if ($prenotazione) {
                $this->completaPrenotazione($prenotazione['id_prenotazione']);
                $messaggioPrenotazione = "Prenotazione #{$prenotazione['id_prenotazione']} completata";
            }

            // 9. NOTIFICA SUCCESSIVO IN CODA (se esistono altre prenotazioni)
            $this->notificaSuccessivoInCoda($copia['id_libro'], $inventarioId);

            // 10. LOG AUDIT
            $this->logAzione($utenteId, 'MODIFICA_PRESTITO', "Prestito #{$prestitoId} registrato - Libro: {$copia['titolo']}");

            // 11. COMMIT TRANSAZIONE
            $this->db->commit();


            try {

                $this->notifier->send(
                    $utenteId,
                    NotificationManager::TYPE_INFO,
                    NotificationManager::URGENCY_LOW,
                    "Prestito Confermato",
                    "Hai preso in prestito '{$copia['titolo']}'. Scadenza prevista: " . date('d/m/Y', strtotime($dataScadenza)),
                    "/dashboard/student/index.php"
                );
            } catch (Exception $e) {
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
     * Registra una restituzione con calcolo multe e gestione prenotazioni
     *
     * @param int $inventarioId ID della copia restituita
     * @param string $condizione Condizione fisica (BUONO, DANNEGGIATO)
     * @param string|null $commentoDanno Descrizione eventuali danni
     * @return array Dettagli della restituzione
     * @throws Exception Se il prestito non viene trovato
     */
    public function registraRestituzione(int $inventarioId, string $condizione = 'BUONO', ?string $commentoDanno = null): array
    {
        $this->db->beginTransaction();

        try {
            // 1. TROVA PRESTITO ATTIVO
            $prestito = $this->getPrestitoAttivo($inventarioId);
            if (!$prestito) {
                throw new Exception("Nessun prestito attivo trovato per questa copia (ID: {$inventarioId})");
            }

            // 2. CALCOLA RITARDO E MULTA
            $giorniRitardo = $this->calcolaGiorniRitardo($prestito['scadenza_prestito']);
            $importoMulta = $this->calcolaMulta($giorniRitardo);

            $messaggi = [];
            $multaTotale = 0;

            // 3. REGISTRA MULTA PER RITARDO SE PRESENTE
            if ($importoMulta > 0) {
                $this->registraMulta($prestito['id_utente'], $giorniRitardo, $importoMulta, 'RITARDO', null);
                $multaTotale += $importoMulta;
                $messaggi[] = "⚠️ Multa per ritardo: €{$importoMulta} ({$giorniRitardo} giorni)";
            }

            // 4. GESTISCI DANNI ALLA COPIA
            if ($condizione === 'DANNEGGIATO') {
                $costoDanno = $this->calcolaCostoDanno($prestito['id_libro']);
                $this->registraMulta($prestito['id_utente'], null, $costoDanno, 'DANNI', $commentoDanno);
                $multaTotale += $costoDanno;
                $messaggi[] = "⚠️ Costo riparazione danni: €{$costoDanno}";
            }

            // 5. AGGIORNA PRESTITO COME RESTITUITO
            $this->completaPrestito($prestito['id_prestito']);

            // 6. AGGIORNA STATO E CONDIZIONE COPIA
            $this->aggiornaStatoCopia($inventarioId, 'DISPONIBILE', $condizione);

            // 7. INCREMENTA STREAK RESTITUZIONI (se in tempo)
            if ($giorniRitardo <= 0) {
                $this->incrementaStreakRestituzioni($prestito['id_utente'], $prestito['id_ruolo']);
            } else {
                $this->resetStreakRestituzioni($prestito['id_utente'], $prestito['id_ruolo']);
            }

            // 8. GESTISCI PRENOTAZIONI IN CODA
            $prenotazioneSuccessiva = $this->getPrenotazioneSuccessiva($prestito['id_libro']);
            if ($prenotazioneSuccessiva) {
                $this->assegnaPrenotazione($prenotazioneSuccessiva['id_prenotazione'], $inventarioId);
                $this->aggiornaStatoCopia($inventarioId, 'PRENOTATO');
                // Nota: $this->inviaNotificaDisponibilita() inviava solo email.
                // Il nuovo NotificationManager gestirà anche la campanella (vedi sotto).
                $messaggi[] = "📢 Libro riservato per: {$prenotazioneSuccessiva['nome']} {$prenotazioneSuccessiva['cognome']} (48h)";
            } else {
                $messaggi[] = "✅ Libro disponibile per nuovi prestiti";
            }

            // 9. SBLOCCA UTENTE SE NON HA ALTRI RITARDI
            $this->verificaSbloccaUtente($prestito['id_utente']);

            // 10. LOG AUDIT
            $this->logAzione($prestito['id_utente'], 'MODIFICA_PRESTITO', "Restituzione prestito #{$prestito['id_prestito']} - Multa: €{$multaTotale}");

            // 11. COMMIT TRANSAZIONE
            $this->db->commit();

            // -------------------------------------------------------------------------
            // NOVITÀ EPIC 8: SISTEMA NOTIFICHE (Post-Commit)
            // -------------------------------------------------------------------------
            // Usiamo un try-catch separato per non bloccare il flusso se le notifiche falliscono
            try {
                // A. Notifica per chi RESTITUISCE (Se c'è multa o ritardo)
                if ($multaTotale > 0 || $giorniRitardo > 0) {
                    $this->notifier->send(
                        $prestito['id_utente'],
                        NotificationManager::TYPE_REMINDER,
                        NotificationManager::URGENCY_HIGH, // Urgente: Multe
                        "Restituzione Registrata (Con Addebiti)",
                        "Libro restituito. Sono stati rilevati {$giorniRitardo} giorni di ritardo o danni. Totale addebitato: €" . number_format($multaTotale, 2),
                        "/dashboard/student/index.php" // Link per vedere dettagli/multe
                    );
                }

                // B. Notifica per chi PRENOTA (Se il libro è passato al prossimo)
                if ($prenotazioneSuccessiva) {
                    // Recuperiamo ID utente dalla prenotazione successiva
                    $nextUserId = $prenotazioneSuccessiva['id_utente'];

                    $this->notifier->send(
                        $nextUserId,
                        NotificationManager::TYPE_INFO,
                        NotificationManager::URGENCY_LOW, // Bassa urgenza notturna
                        "Il libro che aspettavi è disponibile!",
                        "È arrivato il tuo turno. Hai 48 ore per passare in biblioteca a ritirarlo.",
                        "/dashboard/student/index.php"
                    );
                }
            } catch (Exception $e) {
                error_log("[WARNING] Errore invio notifiche restituzione: " . $e->getMessage());
            }
            // -------------------------------------------------------------------------

            // 12. INVIO EMAIL RICEVUTA RESTITUZIONE (Tuo vecchio metodo, opzionale se usi notifier)
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
    // =====================================================================
    // METODI DI CONTROLLO PRELIMINARE
    // =====================================================================

    /**
     * Verifica se l'utente ha multe non pagate
     */
    private function verificaMultePendenti(int $utenteId): void
    {
        $query = "
            SELECT SUM(importo) as totale_multe
            FROM multe
            WHERE id_utente = ? AND data_pagamento IS NULL
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $totaleMulte = (float)($result['totale_multe'] ?? 0);

        if ($totaleMulte > 0) {
            throw new Exception("Impossibile effettuare il prestito. Multe pendenti: €" . number_format($totaleMulte, 2) . ". Recarsi alla cassa per il pagamento.");
        }
    }

    /**
     * Verifica se l'account utente è bloccato
     */
    private function verificaBloccoAccount(array $utente): void
    {
        if ($utente['blocco_account_fino_al'] && strtotime($utente['blocco_account_fino_al']) > time()) {
            $scadenza = date('d/m/Y H:i', strtotime($utente['blocco_account_fino_al']));
            throw new Exception("Account temporaneamente bloccato fino al {$scadenza}. Contattare l'amministrazione.");
        }
    }

    /**
     * Verifica se l'utente ha raggiunto il limite di prestiti attivi
     */
    private function verificaLimitiPrestito(array $utente): void
    {
        $limite = (int)$utente['limite_prestiti'];

        $query = "
            SELECT COUNT(*) as prestiti_attivi
            FROM prestiti
            WHERE id_utente = ? AND data_restituzione IS NULL
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utente['id_utente']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $prestitiAttivi = (int)$result['prestiti_attivi'];

        if ($prestitiAttivi >= $limite) {
            throw new Exception("Limite prestiti raggiunto ({$prestitiAttivi}/{$limite}). Restituire un libro prima di procedere.");
        }
    }

    /**
     * Verifica se la copia è disponibile per il prestito
     */
    private function verificaDisponibilitaCopia(array $copia): void
    {
        if ($copia['stato'] !== 'DISPONIBILE') {
            $stato = $copia['stato'];
            throw new Exception("La copia non è disponibile. Stato attuale: {$stato}");
        }

        if ($copia['condizione'] === 'DANNEGGIATO') {
            throw new Exception("La copia è danneggiata e non può essere prestata.");
        }

        if ($copia['condizione'] === 'PERSO') {
            throw new Exception("La copia risulta smarrita.");
        }
    }

    /**
     * Verifica se l'utente aveva prenotato questo libro
     */
    private function verificaPrenotazione(int $utenteId, int $libroId): ?array
    {
        $query = "
            SELECT *
            FROM prenotazioni
            WHERE id_utente = ?
              AND id_libro = ?
              AND copia_libro IS NOT NULL
              AND data_disponibilita IS NOT NULL
              AND scadenza_ritiro > NOW()
            ORDER BY data_richiesta ASC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId, $libroId]);
        $prenotazione = $stmt->fetch(PDO::FETCH_ASSOC);

        return $prenotazione ?: null;
    }

    // =====================================================================
    // METODI DI GESTIONE PRESTITO
    // =====================================================================

    /**
     * Crea un nuovo record prestito
     */
    private function creaPrestito(int $utenteId, int $inventarioId, string $dataScadenza): int
    {
        $query = "
            INSERT INTO prestiti (id_utente, id_inventario, data_prestito, scadenza_prestito)
            VALUES (?, ?, NOW(), ?)
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId, $inventarioId, $dataScadenza]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Calcola la data di scadenza in base alla durata del ruolo
     */
    private function calcolaDataScadenza(int $giorniDurata): string
    {
        return date('Y-m-d H:i:s', strtotime("+{$giorniDurata} days"));
    }

    /**
     * Aggiorna lo stato di una copia nell'inventario
     */
    private function aggiornaStatoCopia(int $inventarioId, string $nuovoStato, ?string $condizione = null): void
    {
        $query = "UPDATE inventari SET stato = ?";
        $params = [$nuovoStato];

        if ($condizione !== null) {
            $query .= ", condizione = ?";
            $params[] = $condizione;
        }

        $query .= " WHERE id_inventario = ?";
        $params[] = $inventarioId;

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
    }

    /**
     * Incrementa il contatore prestiti totali dell'utente
     */
    private function incrementaPrestitiUtente(int $utenteId, int $ruoloId): void
    {
        $query = "
            UPDATE utenti_ruoli
            SET prestiti_tot = prestiti_tot + 1
            WHERE id_utente = ? AND id_ruolo = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId, $ruoloId]);
    }

    /**
     * Completa una prenotazione
     */
    private function completaPrenotazione(int $prenotazioneId): void
    {
        $query = "
            UPDATE prenotazioni
            SET copia_libro = NULL,
                data_disponibilita = NULL,
                scadenza_ritiro = NULL
            WHERE id_prenotazione = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$prenotazioneId]);
    }

    /**
     * Notifica il successivo utente in coda
     */
    private function notificaSuccessivoInCoda(int $libroId, int $inventarioId): void
    {
        $prenotazione = $this->getPrenotazioneSuccessiva($libroId);
        if ($prenotazione) {
            $this->assegnaPrenotazione($prenotazione['id_prenotazione'], $inventarioId);
            $this->aggiornaStatoCopia($inventarioId, 'PRENOTATO');
            $this->inviaNotificaDisponibilita($prenotazione);
        }
    }

    // =====================================================================
    // METODI DI GESTIONE RESTITUZIONE
    // =====================================================================

    /**
     * Trova un prestito attivo per una copia
     */
    private function getPrestitoAttivo(int $inventarioId): ?array
    {
        $query = "
            SELECT 
                p.*,
                u.nome, u.cognome, u.email,
                l.id_libro, l.titolo,
                ur.id_ruolo
            FROM prestiti p
            INNER JOIN utenti u ON p.id_utente = u.id_utente
            INNER JOIN inventari i ON p.id_inventario = i.id_inventario
            INNER JOIN libri l ON i.id_libro = l.id_libro
            LEFT JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente
            WHERE p.id_inventario = ?
              AND p.data_restituzione IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$inventarioId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Calcola i giorni di ritardo
     */
    private function calcolaGiorniRitardo(string $dataScadenza): int
    {
        $oggi = new \DateTime();
        $scadenza = new \DateTime($dataScadenza);

        if ($oggi <= $scadenza) {
            return 0;
        }

        $intervallo = $oggi->diff($scadenza);
        return $intervallo->days;
    }

    /**
     * Calcola l'importo della multa
     */
    private function calcolaMulta(int $giorniRitardo): float
    {
        if ($giorniRitardo <= self::GIORNI_TOLLERANZA) {
            return 0.0;
        }

        $giorniMulta = $giorniRitardo - self::GIORNI_TOLLERANZA;
        return round($giorniMulta * self::IMPORTO_MULTA_GIORNALIERA, 2);
    }

    /**
     * Registra una multa
     */
    private function registraMulta(int $utenteId, ?int $giorni, float $importo, string $causa, ?string $commento): void
    {
        $query = "
            INSERT INTO multe (id_utente, giorni, importo, causa, commento, data_creazione)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId, $giorni, $importo, $causa, $commento]);
    }

    /**
     * Completa un prestito
     */
    private function completaPrestito(int $prestitoId): void
    {
        $query = "UPDATE prestiti SET data_restituzione = NOW() WHERE id_prestito = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$prestitoId]);
    }

    /**
     * Incrementa lo streak di restituzioni puntuali
     */
    private function incrementaStreakRestituzioni(int $utenteId, int $ruoloId): void
    {
        $query = "
            UPDATE utenti_ruoli
            SET streak_restituzioni = streak_restituzioni + 1
            WHERE id_utente = ? AND id_ruolo = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId, $ruoloId]);
    }

    /**
     * Resetta lo streak di restituzioni per ritardo
     */
    private function resetStreakRestituzioni(int $utenteId, int $ruoloId): void
    {
        $query = "
            UPDATE utenti_ruoli
            SET streak_restituzioni = 0
            WHERE id_utente = ? AND id_ruolo = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId, $ruoloId]);
    }

    // =====================================================================
    // METODI DI SUPPORTO
    // =====================================================================

    /**
     * Recupera dati completi utente con ruolo
     */
    private function getUtenteCompleto(int $utenteId): ?array
    {
        $query = "
            SELECT 
                u.*,
                r.id_ruolo,
                r.nome as nome_ruolo,
                r.durata_prestito,
                r.limite_prestiti,
                ur.prestiti_tot,
                ur.streak_restituzioni
            FROM utenti u
            INNER JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente
            INNER JOIN ruoli r ON ur.id_ruolo = r.id_ruolo
            WHERE u.id_utente = ?
            ORDER BY r.priorita ASC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Recupera dati copia con informazioni libro e autori
     */
    private function getCopiaConLibro(int $inventarioId): ?array
    {
        $query = "
            SELECT 
                i.*,
                l.id_libro, l.titolo, l.valore_copertina,
                GROUP_CONCAT(CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
            FROM inventari i
            INNER JOIN libri l ON i.id_libro = l.id_libro
            LEFT JOIN libri_autori la ON l.id_libro = la.id_libro
            LEFT JOIN autori a ON la.id_autore = a.id
            WHERE i.id_inventario = ?
            GROUP BY i.id_inventario
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$inventarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ottiene la prenotazione successiva in coda
     */
    private function getPrenotazioneSuccessiva(int $libroId): ?array
    {
        $query = "
            SELECT 
                p.*,
                u.nome, u.cognome, u.email
            FROM prenotazioni p
            INNER JOIN utenti u ON p.id_utente = u.id_utente
            WHERE p.id_libro = ?
              AND p.copia_libro IS NULL
              AND p.scadenza_ritiro IS NULL
            ORDER BY p.data_richiesta ASC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$libroId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Assegna una copia riservata a una prenotazione
     */
    private function assegnaPrenotazione(int $prenotazioneId, int $inventarioId): void
    {
        $dataDisponibilita = date('Y-m-d H:i:s');
        $scadenzaRiserva = date('Y-m-d H:i:s', strtotime('+' . self::ORE_RISERVA_PRENOTAZIONE . ' hours'));

        $query = "
            UPDATE prenotazioni
            SET copia_libro = ?,
                data_disponibilita = ?,
                scadenza_ritiro = ?
            WHERE id_prenotazione = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$inventarioId, $dataDisponibilita, $scadenzaRiserva, $prenotazioneId]);
    }

    /**
     * Calcola il costo del danno (valore di copertina del libro)
     */
    private function calcolaCostoDanno(int $libroId): float
    {
        $query = "SELECT valore_copertina FROM libri WHERE id_libro = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$libroId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float)($result['valore_copertina'] ?? 10.00);
    }

    /**
     * Verifica e sblocca utente se non ha altri prestiti in ritardo
     */
    private function verificaSbloccaUtente(int $utenteId): void
    {
        $query = "
            SELECT COUNT(*) as prestiti_in_ritardo
            FROM prestiti
            WHERE id_utente = ?
              AND data_restituzione IS NULL
              AND scadenza_prestito < NOW()
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se non ha più prestiti in ritardo, rimuove il blocco
        if ((int)$result['prestiti_in_ritardo'] === 0) {
            $query = "UPDATE utenti SET blocco_account_fino_al = NULL WHERE id_utente = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$utenteId]);
        }
    }

    /**
     * Log delle azioni per audit
     */
    private function logAzione(int $utenteId, string $azione, string $dettagli): void
    {
        $ipAddress = null;
        $ipv6 = null;

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipAddress = ip2long($ip);
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ipv6 = inet_pton($ip);
            }
        }

        $query = "
            INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address, ipv6, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$utenteId, $azione, $dettagli, $ipAddress, $ipv6]);
    }

    // =====================================================================
    // METODI DI NOTIFICA (stub - da implementare con EmailService)
    // =====================================================================

    private function inviaEmailConferma(array $utente, array $copia, string $dataScadenza, int $prestitoId): void
    {
        // TODO: Implementare con EmailService
    }

    private function inviaEmailRestituzione(array $prestito, float $multaTotale): void
    {
        // TODO: Implementare con EmailService
    }

    private function inviaNotificaDisponibilita(array $prenotazione): void
    {
        // TODO: Implementare con EmailService
    }
}
