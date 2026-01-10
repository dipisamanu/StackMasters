<?php
namespace Ottaviodipisa\StackMasters\Models;

use PDO;
use Exception;

// === FIX IMPORTANTE ===
// Includiamo manualmente i file di configurazione perché le classi Database ed EmailService
// nel tuo progetto sono definite nel namespace globale (senza namespace), quindi l'autoloader
// PSR-4 di Composer non riesce a trovarle automaticamente in src/Core.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

class NotificationManager {
    private PDO $pdo;
    private ?\EmailService $emailService = null;

    // Costanti per definire il tipo di notifica
    const TYPE_REMINDER = 'REMINDER';  // Scadenze, Multe (Priorità Alta)
    const TYPE_INFO = 'INFO';          // Prenotazioni, Info generali (Priorità Normale)

    // Costanti per l'urgenza dell'email
    const URGENCY_HIGH = 'HIGH';       // Invia subito, ignora le ore notturne
    const URGENCY_LOW = 'LOW';         // Rispetta le "Quiet Hours" (differisce l'invio)

    public function __construct() {
        // === FIX ===
        // Usiamo \Database (con la barra davanti) per indicare la classe Globale
        $this->pdo = \Database::getInstance()->getConnection();

        // Verifica se la classe EmailService esiste
        if (class_exists('EmailService')) {
            $this->emailService = new \EmailService();
        }
    }

    /**
     * Metodo principale per inviare una notifica.
     * Salva sempre nel DB e tenta l'invio email in base alle regole.
     */
    public function send(int $id_utente, string $category, string $urgency, string $titolo, string $messaggio, string $link = null): bool {
        // 1. Recupera i dati dell'utente e le sue preferenze
        $stmt = $this->pdo->prepare("SELECT email, nome, notifiche_attive, quiet_hours_start, quiet_hours_end FROM utenti WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false; // Utente non trovato

        // 2. Calcola lo stato dell'invio Email
        $statoEmail = 'NON_RICHIESTA';

        // Se l'utente ha le notifiche attive
        if ($user['notifiche_attive']) {
            // Controlla se siamo nell'orario silenzioso
            $isQuiet = $this->isQuietHour($user['quiet_hours_start'], $user['quiet_hours_end']);

            // Se è urgente OPPURE non è orario silenzioso -> Invia Subito
            if ($urgency === self::URGENCY_HIGH || !$isQuiet) {
                $statoEmail = 'DA_INVIARE';
            } else {
                // Altrimenti metti in coda per la mattina successiva
                $statoEmail = 'DA_INVIARE_DIFFERITO';
            }
        }

        // 3. Determina il tipo visivo (colore) per la campanella
        $visualType = 'INFO';
        if ($category === self::TYPE_REMINDER) $visualType = 'WARNING';
        if (strpos(strtolower($titolo), 'multa') !== false || strpos(strtolower($titolo), 'scaduto') !== false) $visualType = 'DANGER';
        if (strpos(strtolower($titolo), 'confermato') !== false || strpos(strtolower($titolo), 'pronto') !== false) $visualType = 'SUCCESS';

        // 4. Inserisci la notifica nel Database
        $sql = "INSERT INTO notifiche_web 
                (id_utente, tipo, titolo, messaggio, link_azione, stato_email, data_creazione) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $this->pdo->prepare($sql)->execute([$id_utente, $visualType, $category, $titolo, $messaggio, $link, $statoEmail]);
        $notificaId = $this->pdo->lastInsertId();

        // 5. Se lo stato è 'DA_INVIARE', prova a mandare la mail subito
        if ($statoEmail === 'DA_INVIARE' && $this->emailService) {
            $this->deliverEmail($notificaId, $user['email'], $titolo, $messaggio);
        }

        return true;
    }

    /**
     * Esegue l'invio fisico dell'email e aggiorna lo stato nel DB.
     */
    private function deliverEmail($id, $email, $subject, $body) {
        try {
            $sent = $this->emailService->send($email, $subject, $body);

            $status = $sent ? 'INVIATA' : 'FALLITA';
            $stmt = $this->pdo->prepare("UPDATE notifiche_web SET stato_email = ?, data_invio_email = NOW() WHERE id_notifica = ?");
            $stmt->execute([$status, $id]);
        } catch (Exception $e) {
            error_log("NotificationManager Error: " . $e->getMessage());
        }
    }

    private function isQuietHour($start, $end) {
        if (!$start || !$end) return false;
        $now = date('H:i:s');
        if ($start > $end) return ($now >= $start || $now < $end);
        return ($now >= $start && $now < $end);
    }

    /**
     * Recupera le ultime notifiche per l'utente (per la campanella)
     */
    public function getUserNotifications(int $userId, int $limit = 10): array {
        $stmt = $this->pdo->prepare("SELECT * FROM notifiche_web WHERE id_utente = ? ORDER BY data_creazione DESC LIMIT ?");
        // Bind diretto per evitare problemi con LIMIT in PDO
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta quante notifiche non sono ancora state lette (per il badge rosso)
     */
    public function getUnreadCount(int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifiche_web WHERE id_utente = ? AND letto = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Segna una notifica come letta
     */
    public function markAsRead(int $notificaId, int $userId): bool {
        $stmt = $this->pdo->prepare("UPDATE notifiche_web SET letto = 1 WHERE id_notifica = ? AND id_utente = ?");
        return $stmt->execute([$notificaId, $userId]);
    }

}