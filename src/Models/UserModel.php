<?php
/**
 * UserModel - Gestione Utenti e Autenticazione
 * File: src/Models/UserModel.php
 */

require_once __DIR__ . '/../config/database.php';

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Tenta il login
     * Restituisce l'array utente se ok, o una stringa di errore
     */
    public function login(string $email, string $password): array|string
    {
        // 1. Cerca utente
        $sql = "SELECT id_utente, nome, cognome, email, password, email_verificata, 
                       tentativi_login_falliti, blocco_account_fino_al 
                FROM utenti 
                WHERE email = :email 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return "Credenziali non valide.";
        }

        // 2. Verifica blocco account (Brute Force)
        if ($user['blocco_account_fino_al'] && strtotime($user['blocco_account_fino_al']) > time()) {
            $minuti = ceil((strtotime($user['blocco_account_fino_al']) - time()) / 60);
            return "Account temporaneamente bloccato. Riprova tra $minuti minuti.";
        }

        // 3. Verifica Password
        if (!password_verify($password, $user['password'])) {
            // Gestione tentativi falliti
            $attempts = ($user['tentativi_login_falliti'] ?? 0) + 1;

            if ($attempts >= 5) {
                // Blocca per 15 minuti
                $blockUntil = date('Y-m-d H:i:s', time() + 900);
                $upd = $this->db->prepare("UPDATE utenti SET tentativi_login_falliti = ?, blocco_account_fino_al = ? WHERE id_utente = ?");
                $upd->execute([$attempts, $blockUntil, $user['id_utente']]);
                return "Troppi tentativi falliti. Account bloccato per 15 minuti.";
            } else {
                // Incrementa contatore
                $upd = $this->db->prepare("UPDATE utenti SET tentativi_login_falliti = ? WHERE id_utente = ?");
                $upd->execute([$attempts, $user['id_utente']]);
                $rimasti = 5 - $attempts;
                return "Credenziali non valide. Tentativi rimasti: $rimasti";
            }
        }

        // 4. Verifica Email
        if (!$user['email_verificata']) {
            return "Devi verificare la tua email prima di accedere.";
        }

        // 5. Login OK: Reset tentativi
        $upd = $this->db->prepare("UPDATE utenti SET tentativi_login_falliti = 0, blocco_account_fino_al = NULL WHERE id_utente = ?");
        $upd->execute([$user['id_utente']]);

        // Rimuovi la password per sicurezza
        unset($user['password']);

        // 6. Recupera e Gestisce i Ruoli (Cruciale!)
        $user['roles'] = $this->getUserRolesAndFixMissing($user['id_utente']);

        return $user;
    }

    /**
     * Recupera i ruoli. Se non ne ha, assegna 'Studente' nel DB (come faceva il file originale)
     */
    private function getUserRolesAndFixMissing(int $userId): array
    {
        // Query originale
        $sql = "SELECT r.id_ruolo, r.nome, r.priorita, r.durata_prestito, r.limite_prestiti
                FROM ruoli r
                INNER JOIN utenti_ruoli ur ON r.id_ruolo = ur.id_ruolo
                WHERE ur.id_utente = ?
                ORDER BY r.priorita ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $ruoli = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Se vuoto, logica di riparazione originale
        if (empty($ruoli)) {
            $stmtDef = $this->db->prepare("SELECT * FROM ruoli WHERE nome = 'Studente' LIMIT 1");
            $stmtDef->execute();
            $defaultRole = $stmtDef->fetch(PDO::FETCH_ASSOC);

            if ($defaultRole) {
                // Inserisce nel DB
                $ins = $this->db->prepare("INSERT INTO utenti_ruoli (id_utente, id_ruolo) VALUES (?, ?)");
                $ins->execute([$userId, $defaultRole['id_ruolo']]);

                // Imposta l'array di ritorno
                $ruoli = [$defaultRole];
            }
        }

        return $ruoli;
    }

    /**
     * Log per audit (usato nel process login)
     */
    public function logAudit(int $userId, string $action, string $details): void
    {
        try {
            $sql = "INSERT INTO logs_audit (id_utente, azione, dettagli, ip_address)
                    VALUES (?, ?, ?, INET_ATON(?))";
            $this->db->prepare($sql)->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {
            // Ignora errori di log
        }
    }
}