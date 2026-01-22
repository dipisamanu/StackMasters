<?php

namespace Ottaviodipisa\StackMasters\Services;

use Database;
use DateMalformedStringException;
use DateTime;
use PDO;
use Exception;
use Ottaviodipisa\StackMasters\Models\NotificationManager;
use function getEmailService;

/**
 * LoanService - Gestisce tutta la logica di business per prestiti e restituzioni
 * Centralizza le operazioni precedentemente sparse tra Model e Controller.
 */
class LoanService
{
    private PDO $db;
    private NotificationManager $notifier;
    private const int GIORNI_TOLLERANZA = 3;
    private const float IMPORTO_MULTA_GIORNALIERA = 0.50;
    private const int ORE_RISERVA_PRENOTAZIONE = 48;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->notifier = new NotificationManager();
        } catch (Exception $e) {
            throw new Exception("Errore durante l'inizializzazione del service: " . $e->getMessage());
        }
    }

    /**
     * Registra un nuovo prestito con tutti i controlli automatici
     * @throws Exception
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        // Controlla se una transazione è già attiva (es. dal test)
        $isTransactionManagedExternally = $this->db->inTransaction();
        if (!$isTransactionManagedExternally) {
            $this->db->beginTransaction();
        }

        try {
            $utente = $this->getUtenteCompleto($utenteId);
            if (!$utente) {
                throw new Exception("Utente non trovato (ID: $utenteId)");
            }

            $copia = $this->getCopiaConLibro($inventarioId);
            if (!$copia) {
                throw new Exception("Copia libro non trovata (ID Inventario: $inventarioId)");
            }

            $this->verificaMultePendenti($utenteId);
            $this->verificaBloccoAccount($utente);
            $this->verificaLimitiPrestito($utente);
            $this->verificaDisponibilitaCopia($copia);

            // Verifica se la copia è riservata ad altri o se l'utente ha prenotazioni pendenti
            $this->gestisciPrenotazioniPrimaDelPrestito($copia['id_libro'], $inventarioId, $utenteId);

            // Verifica se l'utente aveva una prenotazione attiva per questo libro (per chiuderla)
            $prenotazione = $this->verificaPrenotazione($utenteId, $copia['id_libro']);

            $dataScadenza = $this->calcolaDataScadenza($utente['durata_prestito']);

            $prestitoId = $this->creaPrestito($utenteId, $inventarioId, $dataScadenza);

            $this->aggiornaStatoCopia($inventarioId, 'IN_PRESTITO');

            $this->incrementaPrestitiUtente($utenteId, $utente['id_ruolo']);

            $messaggioPrenotazione = '';
            if ($prenotazione) {
                $this->completaPrenotazione($prenotazione['id_prenotazione']);
                $messaggioPrenotazione = "Prenotazione #{$prenotazione['id_prenotazione']} completata";
            }

            // Se l'utente ha preso una copia diversa da quella assegnata, libera la sua vecchia assegnazione

            $this->logAzione($utenteId, 'MODIFICA_PRESTITO', "Prestito #$prestitoId registrato - Libro: {$copia['titolo']}");

            if (!$isTransactionManagedExternally) {
                $this->db->commit();
            }

            // Notifica Email Diretta (Stilizzata)
            $this->inviaEmailConferma($utente, $copia, $dataScadenza);

            // Notifica Interna (Campanella) - NO EMAIL (già inviata sopra)
            try {
                $this->notifier->send(
                    $utenteId,
                    NotificationManager::TYPE_INFO,
                    NotificationManager::URGENCY_LOW,
                    "Prestito Confermato",
                    "Hai preso in prestito '{$copia['titolo']}'. Scadenza prevista: " . date('d/m/Y', strtotime($dataScadenza)),
                    "/dashboard/student/index.php",
                    true // forceNoEmail
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
            if ($this->db->inTransaction() && !$isTransactionManagedExternally) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra una restituzione
     * @throws Exception
     */
    public function registraRestituzione(int $inventarioId, string $condizione = 'BUONO', ?string $commentoDanno = null): array
    {
        // Controlla se una transazione è già attiva (es. dal test)
        $isTransactionManagedExternally = $this->db->inTransaction();
        if (!$isTransactionManagedExternally) {
            $this->db->beginTransaction();
        }

        try {
            $prestito = $this->getPrestitoAttivo($inventarioId);
            if (!$prestito) {
                throw new Exception("Nessun prestito attivo trovato per questa copia (ID: $inventarioId)");
            }

            // Recupera la condizione di partenza dal prestito (o dall'inventario se non salvata nel prestito)
            $condizionePartenza = $prestito['condizione'] ?? 'BUONO';

            $giorniRitardo = $this->calcolaGiorniRitardo($prestito['scadenza_prestito']);
            $importoMulta = $this->calcolaMulta($giorniRitardo);

            $messaggi = [];
            $multaTotale = 0;

            if ($importoMulta > 0) {
                $this->registraMulta($prestito['id_utente'], $giorniRitardo, $importoMulta, 'RITARDO', "Ritardo di $giorniRitardo gg.");
                $multaTotale += $importoMulta;
                $messaggi[] = "<i class='fas fa-exclamation-triangle'></i> Multa per ritardo: €$importoMulta ($giorniRitardo giorni)";
            }

            // Mappa priorità condizioni per confronto
            $condizioniMap = ['BUONO' => 0, 'USURATO' => 1, 'DANNEGGIATO' => 2, 'SMARRITO' => 3];
            $livelloPartenza = $condizioniMap[strtoupper($condizionePartenza)] ?? 0;
            $livelloRientro = $condizioniMap[strtoupper($condizione)] ?? 0;

            // Controllo integrità: non si può migliorare lo stato (es. da USURATO a BUONO)
            if ($livelloRientro < $livelloPartenza) {
                throw new Exception("Stato non valido: impossibile passare da $condizionePartenza a $condizione.");
            }

            // Calcolo Multe Danni (solo se peggiora)
            if ($livelloRientro > $livelloPartenza) {
                $valoreLibro = (float)($prestito['valore_copertina'] ?? 10.00);
                $penaleStato = 0.0;

                if ($valoreLibro <= 0) {
                    throw new Exception("Danno rilevato ($condizione), ma impossibile calcolare la penale: il valore di copertina del libro non è impostato.");
                }

                switch (strtoupper($condizione)) {
                    case 'USURATO':
                        // 10% del valore
                        $penaleStato = round($valoreLibro * 0.10, 2);
                        break;
                    case 'DANNEGGIATO':
                        // 50% del valore
                        $penaleStato = round($valoreLibro * 0.50, 2);
                        break;
                    case 'SMARRITO':
                        // 100% del valore
                        $penaleStato = $valoreLibro;
                        break;
                }

                if ($penaleStato > 0) {
                    $this->registraMulta($prestito['id_utente'], null, $penaleStato, 'DANNI', "Stato: $condizione (da $condizionePartenza). " . $commentoDanno);
                    $multaTotale += $penaleStato;
                    $messaggi[] = "<i class='fas fa-exclamation-triangle'></i> Addebito per danni ($condizione): €$penaleStato";
                }
            }

            $this->completaPrestito($prestito['id_prestito']);

            // Se il libro è DANNEGGIATO o SMARRITO, non può essere rimesso in circolo
            if ($condizione === 'DANNEGGIATO' || $condizione === 'SMARRITO') {
                $nuovoStato = ($condizione === 'SMARRITO') ? 'SMARRITO' : 'NON_IN_PRESTITO';
                $this->aggiornaStatoCopia($inventarioId, $nuovoStato, $condizione);
                $messaggi[] = "<i class='fas fa-ban'></i> Libro ritirato dalla circolazione (Stato: $nuovoStato)";

                // Se c'erano prenotazioni, NON possiamo assegnare QUESTA copia.
                // Bisognerebbe cercare un'altra copia disponibile per la prenotazione, per ora la lasciamo in coda.
                // Il sistema assegnerà un'altra copia quando rientrerà
            } else {
                // Se il libro è BUONO o USURATO, può essere prestato
                $prenotazioneSuccessiva = $this->getPrenotazioneSuccessiva($prestito['id_libro']);

                if ($prenotazioneSuccessiva) {
                    $this->assegnaPrenotazione($prenotazioneSuccessiva['id_prenotazione'], $inventarioId);
                    $this->aggiornaStatoCopia($inventarioId, 'PRENOTATO', $condizione);
                    $messaggi[] = "<i class='fas fa-bullhorn'></i> Libro riservato per: {$prenotazioneSuccessiva['nome']} {$prenotazioneSuccessiva['cognome']} (48h)";

                    // Notifica Interna (Campanella) - NO EMAIL (invio quella stilizzata sotto)
                    $this->notifier->send(
                        $prenotazioneSuccessiva['id_utente'],
                        NotificationManager::TYPE_REMINDER,
                        NotificationManager::URGENCY_HIGH,
                        "Il libro che aspettavi è disponibile!",
                        "È arrivato il tuo turno per '{$prestito['titolo']}'. Hai 48 ore per passare in biblioteca.",
                        "/dashboard/student/index.php",
                        true // forceNoEmail
                    );

                    // Invio Email Prenotazione Disponibile (Stilizzata)
                    $scadenzaRitiro = date('d/m/Y H:i', strtotime('+' . self::ORE_RISERVA_PRENOTAZIONE . ' hours'));
                    $this->inviaEmailPrenotazioneDisponibile($prenotazioneSuccessiva, $prestito['titolo'], $scadenzaRitiro);

                } else {
                    $this->aggiornaStatoCopia($inventarioId, 'DISPONIBILE', $condizione);
                    $messaggi[] = "<i class='fas fa-check-circle'></i> Libro tornato disponibile";
                }
            }

            if ($giorniRitardo <= 0) {
                $this->incrementaStreakRestituzioni($prestito['id_utente'], $prestito['id_ruolo']);
            } else {
                $this->resetStreakRestituzioni($prestito['id_utente'], $prestito['id_ruolo']);
            }

            $this->verificaSbloccaUtente($prestito['id_utente']);
            $this->logAzione($prestito['id_utente'], 'MODIFICA_PRESTITO', "Restituzione prestito #{$prestito['id_prestito']} - Multa: €$multaTotale");

            // COMMIT TRANSAZIONE
            if (!$isTransactionManagedExternally) {
                $this->db->commit();
            }

            // Notifiche Post-Commit per chi restituisce (Multe)
            try {
                if ($multaTotale > 0) {
                    $this->notifier->send(
                        $prestito['id_utente'],
                        NotificationManager::TYPE_REMINDER,
                        NotificationManager::URGENCY_HIGH,
                        "Restituzione con Addebiti",
                        "Totale addebitato: €" . number_format($multaTotale, 2),
                        "/dashboard/student/index.php"
                    );
                }
            } catch (Exception $e) {
                error_log("[WARNING] Errore invio notifiche restituzione: " . $e->getMessage());
            }

            return [
                'status' => 'success',
                'prestito_id' => $prestito['id_prestito'],
                'multa_totale' => $multaTotale,
                'giorni_ritardo' => $giorniRitardo,
                'messaggi' => $messaggi
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction() && !$isTransactionManagedExternally) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Gestisce le prenotazioni scadute (CRON JOB)
     * @throws Exception
     */
    public function gestisciPrenotazioniScadute(): array
    {
        $this->db->beginTransaction();
        $log = [];
        try {
            $stmt = $this->db->prepare("SELECT * FROM prenotazioni WHERE copia_libro IS NOT NULL AND scadenza_ritiro < NOW()");
            $stmt->execute();
            $scadute = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($scadute as $pren) {
                // Notifica scadenza prenotazione
                $this->notifier->send(
                    (int)$pren['id_utente'],
                    'PRENOTAZIONE',
                    NotificationManager::URGENCY_HIGH,
                    'Prenotazione Scaduta',
                    "Non hai ritirato il libro in tempo. La prenotazione è decaduta."
                );

                // Cancella prenotazione
                $this->db->prepare("DELETE FROM prenotazioni WHERE id_prenotazione = ?")->execute([$pren['id_prenotazione']]);

                // Controlla stato copia
                $copiaInfo = $this->getCopiaConLibro((int)$pren['copia_libro']);
                if ($copiaInfo['stato'] === 'IN_PRESTITO') {
                    $log[] = "Prenotazione #{$pren['id_prenotazione']} scaduta. La copia #{$pren['copia_libro']} risulta già in prestito. Nessuna riassegnazione.";
                    continue;
                }

                // Cerca successore
                $prenotazioneSuccessiva = $this->getPrenotazioneSuccessiva((int)$pren['id_libro']);

                if ($prenotazioneSuccessiva) {
                    $this->assegnaPrenotazione($prenotazioneSuccessiva['id_prenotazione'], (int)$pren['copia_libro']);
                    $this->aggiornaStatoCopia((int)$pren['copia_libro'], 'PRENOTATO');

                    $log[] = "Prenotazione #{$pren['id_prenotazione']} scaduta. Copia #{$pren['copia_libro']} riassegnata a utente {$prenotazioneSuccessiva['id_utente']}.";

                    // Notifica Interna - NO EMAIL
                    $this->notifier->send(
                        $prenotazioneSuccessiva['id_utente'],
                        NotificationManager::TYPE_INFO,
                        NotificationManager::URGENCY_HIGH,
                        "Il libro che aspettavi è disponibile!",
                        "È arrivato il tuo turno. Hai 48 ore per passare in biblioteca.",
                        "/dashboard/student/index.php",
                        true // forceNoEmail
                    );

                    // Invio Email Prenotazione Disponibile (Stilizzata)
                    $scadenzaRitiro = date('d/m/Y H:i', strtotime('+' . self::ORE_RISERVA_PRENOTAZIONE . ' hours'));
                    $this->inviaEmailPrenotazioneDisponibile($prenotazioneSuccessiva, $copiaInfo['titolo'], $scadenzaRitiro);

                } else {
                    $this->aggiornaStatoCopia((int)$pren['copia_libro'], 'DISPONIBILE');
                    $log[] = "Prenotazione #{$pren['id_prenotazione']} scaduta. Copia #{$pren['copia_libro']} tornata disponibile.";
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $log;
    }

    // --- METODI PRIVATI DI SUPPORTO ---

    /**
     * @throws Exception
     */
    private function gestisciPrenotazioniPrimaDelPrestito(int $libroId, int $inventarioId, int $utenteId): void
    {
        // 1. Se l'utente ha prenotazioni per questo libro, cancellale (le sta ritirando ora)
        // Se la prenotazione aveva una copia assegnata DIVERSA da quella che sta prendendo, libera l'altra copia.
        $stmt = $this->db->prepare("SELECT id_prenotazione, copia_libro FROM prenotazioni WHERE id_libro = ? AND id_utente = ?");
        $stmt->execute([$libroId, $utenteId]);
        $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($existing as $res) {
            $this->db->prepare("DELETE FROM prenotazioni WHERE id_prenotazione = ?")->execute([$res['id_prenotazione']]);

            if ($res['copia_libro']) {
                $assignedCopyId = (int)$res['copia_libro'];
                // Se la copia assegnata non è quella che sta prendendo ora
                if ($assignedCopyId !== $inventarioId) {
                    // Cerca un altro utente a cui dare quella copia
                    $successore = $this->getPrenotazioneSuccessiva($libroId);
                    if ($successore) {
                        $this->assegnaPrenotazione($successore['id_prenotazione'], $assignedCopyId);
                        $this->aggiornaStatoCopia($assignedCopyId, 'PRENOTATO');
                    } else {
                        $this->aggiornaStatoCopia($assignedCopyId, 'DISPONIBILE');
                    }
                }
            }
        }

        // 2. Verifica che la copia che sta prendendo non sia riservata a qualcun altro
        $stmt = $this->db->prepare("SELECT id_utente FROM prenotazioni WHERE copia_libro = ? AND scadenza_ritiro > NOW() AND id_utente != ?");
        $stmt->execute([$inventarioId, $utenteId]);
        if ($id = $stmt->fetchColumn()) {
            throw new Exception("Questa copia è riservata per il ritiro dell'utente ID: $id");
        }
    }

    /**
     * @throws Exception
     */
    private function verificaMultePendenti(int $utenteId): void
    {
        $stmt = $this->db->prepare("SELECT SUM(importo) as totale_multe FROM multe WHERE id_utente = ? AND data_pagamento IS NULL");
        $stmt->execute([$utenteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (($result['totale_multe'] ?? 0) > 0) {
            throw new Exception("Multe pendenti: €" . number_format($result['totale_multe'], 2));
        }
    }

    /**
     * @throws Exception
     */
    private function verificaBloccoAccount(array $utente): void
    {
        if ($utente['blocco_account_fino_al'] && strtotime($utente['blocco_account_fino_al']) > time()) {
            throw new Exception("Account bloccato fino al " . date('d/m/Y H:i', strtotime($utente['blocco_account_fino_al'])));
        }
    }

    /**
     * @throws Exception
     */
    private function verificaLimitiPrestito(array $utente): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL");
        $stmt->execute([$utente['id_utente']]);
        $attivi = $stmt->fetchColumn();

        if ($attivi >= $utente['limite_prestiti']) {
            throw new Exception("Limite prestiti raggiunto ($attivi/{$utente['limite_prestiti']}).");
        }
    }

    /**
     * @throws Exception
     */
    private function verificaDisponibilitaCopia(array $copia): void
    {
        if ($copia['stato'] !== 'DISPONIBILE' && $copia['stato'] !== 'PRENOTATO') throw new Exception("Copia non disponibile (Stato: {$copia['stato']})");
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

    private function calcolaDataScadenza(int $giorni): string
    {
        return date('Y-m-d H:i:s', strtotime("+$giorni days"));
    }

    private function aggiornaStatoCopia(int $id, string $stato, ?string $cond = null): void
    {

        /** @noinspection SqlWithoutWhere */
        $sql = "UPDATE inventari SET stato = ?";
        $params = [$stato];
        if ($cond) {
            $sql .= ", condizione = ?";
            $params[] = strtoupper($cond);
        }
        $sql .= " WHERE id_inventario = ?";
        $params[] = $id;
        $this->db->prepare($sql)->execute($params);
    }

    private function incrementaPrestitiUtente(int $uid, int $rid): void
    {
        $this->db->prepare("UPDATE utenti_ruoli SET prestiti_tot = prestiti_tot + 1 WHERE id_utente = ? AND id_ruolo = ?")->execute([$uid, $rid]);
    }

    private function completaPrenotazione(int $pid): void
    {
        $this->db->prepare("UPDATE prenotazioni SET copia_libro = NULL, data_disponibilita = NULL, scadenza_ritiro = NULL WHERE id_prenotazione = ?")->execute([$pid]);
    }

    private function getPrestitoAttivo(int $iid): ?array
    {
        $sql = "SELECT p.*, u.nome, u.cognome, u.email, l.id_libro, l.titolo, l.valore_copertina, ur.id_ruolo, i.condizione
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

    /**
     * Calcola i giorni di ritardo usando i timestamp per massima compatibilità.
     * @param string $scadenza
     * @return int
     * @throws DateMalformedStringException
     */
    private function calcolaGiorniRitardo(string $scadenza): int
    {
        $diff = new DateTime()->diff(new DateTime($scadenza));
        return (new DateTime() > new DateTime($scadenza)) ? $diff->days : 0;
    }


    private function calcolaMulta(int $gg): float
    {
        return ($gg > self::GIORNI_TOLLERANZA) ? round(($gg - self::GIORNI_TOLLERANZA) * self::IMPORTO_MULTA_GIORNALIERA, 2) : 0.0;
    }

    private function registraMulta(int $uid, ?int $gg, float $imp, string $causa, ?string $comm): void
    {
        $this->db->prepare("INSERT INTO multe (id_utente, giorni, importo, causa, commento, data_creazione) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$uid, $gg, $imp, $causa, $comm]);
    }

    private function completaPrestito(int $pid): void
    {
        $this->db->prepare("UPDATE prestiti SET data_restituzione = NOW() WHERE id_prestito = ?")->execute([$pid]);
    }

    private function incrementaStreakRestituzioni(int $uid, int $rid): void
    {
        $this->db->prepare("UPDATE utenti_ruoli SET streak_restituzioni = streak_restituzioni + 1 WHERE id_utente = ? AND id_ruolo = ?")->execute([$uid, $rid]);
    }

    private function resetStreakRestituzioni(int $uid, int $rid): void
    {
        $this->db->prepare("UPDATE utenti_ruoli SET streak_restituzioni = 0 WHERE id_utente = ? AND id_ruolo = ?")->execute([$uid, $rid]);
    }

    private function getUtenteCompleto(int $uid): ?array
    {
        $sql = "SELECT u.*, r.id_ruolo, r.nome as nome_ruolo, r.durata_prestito, r.limite_prestiti FROM utenti u JOIN utenti_ruoli ur ON u.id_utente = ur.id_utente JOIN ruoli r ON ur.id_ruolo = r.id_ruolo WHERE u.id_utente = ? ORDER BY r.priorita LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getCopiaConLibro(int $iid): ?array
    {
        $sql = "SELECT i.*, l.id_libro, l.titolo, l.valore_copertina FROM inventari i JOIN libri l ON i.id_libro = l.id_libro WHERE i.id_inventario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$iid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getPrenotazioneSuccessiva(int $lid): ?array
    {
        $sql = "SELECT p.*, u.nome, u.cognome, u.email, u.id_utente FROM prenotazioni p JOIN utenti u ON p.id_utente = u.id_utente WHERE p.id_libro = ? AND p.copia_libro IS NULL AND p.scadenza_ritiro IS NULL ORDER BY p.data_richiesta LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function assegnaPrenotazione(int $pid, int $iid): void
    {
        $dispo = date('Y-m-d H:i:s');
        $scad = date('Y-m-d H:i:s', strtotime('+' . self::ORE_RISERVA_PRENOTAZIONE . ' hours'));
        $this->db->prepare("UPDATE prenotazioni SET copia_libro = ?, data_disponibilita = ?, scadenza_ritiro = ? WHERE id_prenotazione = ?")->execute([$iid, $dispo, $scad, $pid]);
    }

    private function verificaSbloccaUtente(int $uid): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM prestiti WHERE id_utente = ? AND data_restituzione IS NULL AND scadenza_prestito < NOW()");
        $stmt->execute([$uid]);
        if ($stmt->fetchColumn() == 0) {
            $this->db->prepare("UPDATE utenti SET blocco_account_fino_al = NULL WHERE id_utente = ?")->execute([$uid]);
        }
    }

    private function logAzione(int $uid, string $act, string $det): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ipLong = ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) ? ip2long($ip) : null;
        $this->db->prepare("INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())")->execute([$uid, $act, $det, $ipLong]);
    }

    // --- METODI NOTIFICA EMAIL ---

    private function inviaEmailConferma(array $utente, array $copia, string $dataScadenza): void
    {
        require_once __DIR__ . '/../config/email.php';
        try {
            $emailService = getEmailService(true);
            $emailService->sendLoanConfirmation(
                $utente['email'],
                $utente['nome'],
                $copia['titolo'],
                date('d/m/Y', strtotime($dataScadenza))
            );
        } catch (Exception $e) {
            error_log("Errore invio email di conferma prestito: " . $e->getMessage());
        }
    }

    private function inviaEmailPrenotazioneDisponibile(array $prenotazione, string $titoloLibro, string $scadenzaRitiro): void
    {
        require_once __DIR__ . '/../config/email.php';
        try {
            $emailService = getEmailService(true);
            $emailService->sendReservationAvailable(
                $prenotazione['email'],
                $prenotazione['nome'],
                $titoloLibro,
                $scadenzaRitiro
            );
        } catch (Exception $e) {
            error_log("Errore invio email di prenotazione disponibile: " . $e->getMessage());
        }
    }
}
