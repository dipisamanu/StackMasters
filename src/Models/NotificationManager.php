<?php

namespace Ottaviodipisa\StackMasters\Models;

use Database;
use EmailService;
use PDO;
use Exception;

// Includiamo manualmente i file di configurazione
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

class NotificationManager
{
    private PDO $pdo;
    private ?EmailService $emailService = null;

    const string TYPE_REMINDER = 'REMINDER';
    const string TYPE_INFO = 'INFO';
    const string URGENCY_HIGH = 'HIGH';
    const string URGENCY_LOW = 'LOW';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();

        if (class_exists('EmailService')) {
            $this->emailService = new EmailService();
        }
    }

    /**
     * Invia una notifica all'utente.
     *
     * @param int $id_utente ID destinatario
     * @param string $category Categoria (usata per logica interna, non salvata direttamente)
     * @param string $urgency Livello urgenza (HIGH/LOW)
     * @param string $titolo Titolo notifica
     * @param string $messaggio Corpo notifica
     * @param string|null $link Link azione (opzionale)
     * @param bool $forceNoEmail Se true, non invia l'email automatica (utile se si invia un template custom a parte)
     * @return bool
     */
    public function send(int $id_utente, string $category, string $urgency, string $titolo, string $messaggio, ?string $link = null, bool $forceNoEmail = false): bool
    {
        $stmt = $this->pdo->prepare("SELECT email, nome, notifiche_attive, quiet_hours_start, quiet_hours_end FROM utenti WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $statoEmail = 'NON_RICHIESTA';

        // Se l'utente ha le notifiche attive E non è stato richiesto di sopprimere l'email
        if ($user['notifiche_attive'] && !$forceNoEmail) {
            $statoEmail = 'DA_INVIARE';
        }

        // Mappiamo l'urgenza o la categoria al tipo visivo (INFO, WARNING, DANGER, SUCCESS)
        $visualType = 'INFO';
        if ($urgency === self::URGENCY_HIGH) {
            $visualType = 'DANGER';
        } elseif ($category === 'SUCCESS') {
            $visualType = 'SUCCESS';
        }

        $sql = "INSERT INTO notifiche_web (id_utente, tipo, titolo, messaggio, link_azione, stato_email, data_creazione) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $this->pdo->prepare($sql)->execute([$id_utente, $visualType, $titolo, $messaggio, $link, $statoEmail]);
        $notificaId = $this->pdo->lastInsertId();

        // Tenta l'invio immediato solo se richiesto
        if ($statoEmail === 'DA_INVIARE' && $this->emailService) {
            $this->deliverEmail($notificaId, $user['email'], $titolo, $messaggio);
        }

        return true;
    }

    private function deliverEmail($id, $email, $subject, $body): void
    {
        try {
            $sent = $this->emailService->send($email, $subject, $body);
            $status = $sent ? 'INVIATA' : 'FALLITA';
            $stmt = $this->pdo->prepare("UPDATE notifiche_web SET stato_email = ?, data_invio_email = NOW() WHERE id_notifica = ?");
            $stmt->execute([$status, $id]);
        } catch (Exception $e) {
            error_log("NotificationManager Error: " . $e->getMessage());
        }
    }

    // Altri metodi helper (getUserNotifications, ecc.)
    private function isQuietHour($start, $end): bool
    {
        if (!$start || !$end) return false;
        $now = date('H:i:s');
        if ($start > $end) return ($now >= $start || $now < $end);
        return ($now >= $start && $now < $end);
    }

    /**
     * Recupera le ultime notifiche per l'utente (per la campanella)
     */
    public function getUserNotifications(int $userId, int $limit = 10): array
    {
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
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifiche_web WHERE id_utente = ? AND letto = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Segna una notifica come letta
     */
    public function markAsRead(int $notificaId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE notifiche_web SET letto = 1 WHERE id_notifica = ? AND id_utente = ?");
        return $stmt->execute([$notificaId, $userId]);
    }
}

?>