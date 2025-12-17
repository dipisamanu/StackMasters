<?php


/**
 * UserModel - Gestisce le query relative agli utenti
 */
class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Recupera tutti i dati di un utente per ID
     *
     * @param int $userId ID dell'utente
     * @return array Dati utente
     * @throws \Exception Se l'utente non viene trovato
     */
    public function getUserById(int $userId): array
    {
        $sql = "SELECT
                    id_utente,
                    cf,
                    username,
                    nome,
                    cognome,
                    email,
                    data_nascita,
                    sesso,
                    comune_nascita,
                    email_verificata,
                    notifiche_attive,
                    quiet_hours_start,
                    quiet_hours_end,
                    livello_xp,
                    data_creazione,
                    ultimo_aggiornamento
                FROM Utenti
                WHERE id_utente = :userId";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $utente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$utente) {
                throw new \Exception("Utente non trovato");
            }

            return $utente;

        } catch (PDOException $e) {
            throw new \Exception("Errore nel recupero dei dati utente: " . $e->getMessage());
        }
    }

    /**
     * Recupera i ruoli di un utente
     *
     * @param int $userId ID dell'utente
     * @return array Lista dei ruoli
     */
    public function getUserRoles(int $userId): array
    {
        $sql = "SELECT
                    r.id_ruolo,
                    r.nome,
                    r.priorita,
                    r.durata_prestito,
                    r.limite_prestiti,
                    ur.prestiti_tot,
                    ur.streak_restituzioni
                FROM Utenti_Ruoli ur
                JOIN Ruoli r ON ur.id_ruolo = r.id_ruolo
                WHERE ur.id_utente = :userId
                ORDER BY r.priorita";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new \Exception("Errore nel recupero dei ruoli: " . $e->getMessage());
        }
    }

    /**
     * Recupera i prestiti attivi di un utente con calcolo giorni rimanenti
     *
     * @param int $userId ID dell'utente
     * @return array Lista prestiti attivi
     */
    public function getActiveLoans(int $userId): array
    {
        $sql = "SELECT
                    p.id_prestito,
                    p.data_prestito,
                    p.scadenza_prestito,
                    l.titolo,
                    l.copertina_url,
                    DATEDIFF(p.scadenza_prestito, NOW()) as giorni_rimanenti,
                    GROUP_CONCAT(CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autore
                FROM Prestiti p
                JOIN Inventari i ON p.id_inventario = i.id_inventario
                JOIN Libri l ON i.id_libro = l.id_libro
                LEFT JOIN Libri_Autori la ON l.id_libro = la.id_libro
                LEFT JOIN Autori a ON la.id_autore = a.id
                WHERE p.id_utente = :userId
                AND p.data_restituzione IS NULL
                GROUP BY p.id_prestito, p.data_prestito, p.scadenza_prestito, l.titolo, l.copertina_url
                ORDER BY p.scadenza_prestito";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new \Exception("Errore nel recupero dei prestiti: " . $e->getMessage());
        }
    }

    /**
     * Aggiorna le preferenze del profilo utente
     *
     * @param int $userId ID dell'utente
     * @param array $data Dati da aggiornare
     * @return bool True se aggiornamento riuscito
     */
    public function updateUserProfile(int $userId, array $data): bool
    {
        // Costruisce dinamicamente solo i campi da aggiornare e crea la query completa alla fine
        $fields = [];
        $params = [];

        // Email
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params[':email'] = $data['email'];
        }

        // Notifiche attive
        if (isset($data['notifiche_attive'])) {
            $fields[] = "notifiche_attive = :notifiche_attive";
            $params[':notifiche_attive'] = $data['notifiche_attive'] ? 1 : 0;
        }

        // Quiet hours
        if (isset($data['quiet_hours_start'])) {
            $fields[] = "quiet_hours_start = :quiet_hours_start";
            $params[':quiet_hours_start'] = $data['quiet_hours_start'];
        }

        if (isset($data['quiet_hours_end'])) {
            $fields[] = "quiet_hours_end = :quiet_hours_end";
            $params[':quiet_hours_end'] = $data['quiet_hours_end'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE Utenti SET ' . implode(', ', $fields) . ' WHERE id_utente = :userId';
        $params[':userId'] = $userId;

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            return $stmt->execute();

        } catch (PDOException $e) {
            throw new \Exception("Errore nell'aggiornamento del profilo: " . $e->getMessage());
        }
    }

    /**
     * Recupera le statistiche personali dell'utente (gamification)
     *
     * @param int $userId ID dell'utente
     * @return array Statistiche
     */
    public function getUserStats(int $userId): array
    {
        $sql = "SELECT
                    COUNT(p.id_prestito) as totale_prestiti,
                    SUM(CASE WHEN p.data_restituzione IS NOT NULL THEN 1 ELSE 0 END) as prestiti_completati,
                    AVG(DATEDIFF(p.data_restituzione, p.data_prestito)) as durata_media_prestito,
                    u.livello_xp
                FROM Prestiti p
                JOIN Utenti u ON p.id_utente = u.id_utente
                WHERE p.id_utente = :userId
                GROUP BY u.livello_xp";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return $stats ?: [
                'totale_prestiti' => 0,
                'prestiti_completati' => 0,
                'durata_media_prestito' => 0,
                'livello_xp' => 0
            ];

        } catch (PDOException $e) {
            throw new \Exception("Errore nel recupero delle statistiche: " . $e->getMessage());
        }
    }

    /**
     * Recupera i badge ottenuti dall'utente
     *
     * @param int $userId ID dell'utente
     * @return array Lista badge
     */
    public function getUserBadges(int $userId): array
    {
        $sql = "SELECT
                    b.id_badge,
                    b.nome,
                    b.descrizione,
                    b.icona_url,
                    ub.data_conseguimento
                FROM Utenti_Badge ub
                JOIN Badge b ON ub.id_badge = b.id_badge
                WHERE ub.id_utente = :userId
                ORDER BY ub.data_conseguimento DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new \Exception("Errore nel recupero dei badge: " . $e->getMessage());
        }
    }

    /**
     * Recupera le multe attive dell'utente
     *
     * @param int $userId ID dell'utente
     * @return array Lista multe
     */
    public function getUserFines(int $userId): array
    {
        $sql = "SELECT
                    id_multa,
                    giorni,
                    importo,
                    causa,
                    commento,
                    data_creazione,
                    data_pagamento
                FROM Multe
                WHERE id_utente = :userId
                AND data_pagamento IS NULL
                ORDER BY data_creazione DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new \Exception("Errore nel recupero delle multe: " . $e->getMessage());
        }
    }

    /**
     * Verifica le credenziali di login
     *
     * @param string $email Email dell'utente
     * @param string $password Password in chiaro
     * @return array|false Dati utente se login riuscito, false altrimenti
     */
    public function verifyLogin(string $email, string $password): array|false
    {
        $sql = "SELECT
                    id_utente,
                    username,
                    nome,
                    cognome,
                    email,
                    password
                FROM Utenti
                WHERE email = :email";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Verifica la password
            if (password_verify($password, $user['password'])) {
                // Rimuove la password dalla risposta
                unset($user['password']);
                return $user;
            }

            return false;

        } catch (PDOException $e) {
            throw new \Exception("Errore durante il login: " . $e->getMessage());
        }
    }
}
