<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class User extends Model
{
    // Verifica se email o CF esistono già
    public function exists($email, $cf)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email OR cf = :cf");
        $stmt->execute(['email' => $email, 'cf' => $cf]);
        return $stmt->fetchColumn();
    }

    // Crea nuovo utente con stato "non attivo" (0)
    public function create($data)
    {
        $sql = "INSERT INTO users (nome, cognome, email, password, cf, activation_token, is_active, role, created_at) 
                VALUES (:nome, :cognome, :email, :password, :cf, :token, 0, 'student', NOW())";

        $stmt = $this->db->prepare($sql);

        $result = $stmt->execute([
            'nome' => $data['nome'],
            'cognome' => $data['cognome'],
            'email' => $data['email'],
            'password' => $data['password'],
            'cf' => $data['cf'],
            'token' => $data['token'] // Il token generato nel controller
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Attiva l'account basandosi sul token
     * @param string $token
     * @return bool true se attivato, false se token invalido
     */
    public function activateAccount($token)
    {
        // 1. Cerca l'utente con quel token
        $stmt = $this->db->prepare("SELECT id FROM users WHERE activation_token = :token AND is_active = 0");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false; // Token non trovato o utente già attivo
        }

        // 2. Aggiorna lo stato e rimuove il token per sicurezza
        $update = $this->db->prepare("UPDATE users SET is_active = 1, activation_token = NULL, email_verified_at = NOW() WHERE id = :id");
        return $update->execute(['id' => $user['id']]);
    }
}