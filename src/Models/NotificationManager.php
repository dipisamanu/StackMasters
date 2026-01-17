<?php
namespace Ottaviodipisa\StackMasters\Models;

use PDO;
use Exception;

// Includiamo manualmente i file di configurazione
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

class NotificationManager {
    private PDO $pdo;
    private $emailService = null;

    const TYPE_REMINDER = 'REMINDER';
    const TYPE_INFO = 'INFO';
    const URGENCY_HIGH = 'HIGH';
    const URGENCY_LOW = 'LOW';

    public function __construct() {
        // FIX: Usa \Database globale
        $this->pdo = \Database::getInstance()->getConnection();

        // FIX: Controlla se esiste la classe globale \EmailService
        if (class_exists('\EmailService')) {
            $this->emailService = new \EmailService();
        }
    }

    public function send(int $id_utente, string $category, string $urgency, string $titolo, string $messaggio, string $link = null): bool {
        $stmt = $this->pdo->prepare("SELECT email, nome, notifiche_attive, quiet_hours_start, quiet_hours_end FROM utenti WHERE id_utente = ?");
        $stmt->execute([$id_utente]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $statoEmail = 'NON_RICHIESTA';

        if ($user['notifiche_attive']) {
            $statoEmail = 'DA_INVIARE'; // Semplificato per debug: prova sempre a inviare se attive
        }

        $visualType = 'INFO';

        $sql = "INSERT INTO notifiche_web (id_utente, tipo, titolo, messaggio, link_azione, stato_email, data_creazione) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $this->pdo->prepare($sql)->execute([$id_utente, $visualType, $category, $titolo, $messaggio, $link, $statoEmail]);
        $notificaId = $this->pdo->lastInsertId();

        // Tenta l'invio immediato
        if ($statoEmail === 'DA_INVIARE' && $this->emailService) {
            $this->deliverEmail($notificaId, $user['email'], $titolo, $messaggio);
        }

        return true;
    }

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

    // ... Altri metodi helper (getUserNotifications, ecc.)
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
?>