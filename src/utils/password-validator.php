<?php
/**
 * Password Validator - Validazione Robustezza Password
 * File: src/utils/password-validator.php
 *
 * Requisiti:
 * - Minimo 8 caratteri
 * - Almeno una lettera maiuscola
 * - Almeno una lettera minuscola
 * - Almeno un numero
 * - Almeno un carattere speciale
 */

class PasswordValidator {

    // Configurazione requisiti password
    private const MIN_LENGTH = 8;
    private const MAX_LENGTH = 128;
    private const REQUIRE_UPPERCASE = true;
    private const REQUIRE_LOWERCASE = true;
    private const REQUIRE_NUMBER = true;
    private const REQUIRE_SPECIAL = true;

    // Caratteri speciali ammessi
    private const SPECIAL_CHARS = '!@#$%^&*()_+-=[]{}|;:,.<>?';

    public static function validate($password) {
        $errors = [];
        $strength = 0;

        // Controllo lunghezza minima
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "La password deve contenere almeno " . self::MIN_LENGTH . " caratteri";
        } else {
            $strength += 20;
        }

        // Controllo lunghezza massima
        if (strlen($password) > self::MAX_LENGTH) {
            $errors[] = "La password non può superare " . self::MAX_LENGTH . " caratteri";
        }

        // Controllo maiuscole
        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "La password deve contenere almeno una lettera maiuscola";
        } else if (preg_match('/[A-Z]/', $password)) {
            $strength += 20;
        }

        // Controllo minuscole
        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "La password deve contenere almeno una lettera minuscola";
        } else if (preg_match('/[a-z]/', $password)) {
            $strength += 20;
        }

        // Controllo numeri
        if (self::REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = "La password deve contenere almeno un numero";
        } else if (preg_match('/[0-9]/', $password)) {
            $strength += 20;
        }

        // Controllo caratteri speciali
        if (self::REQUIRE_SPECIAL && !preg_match('/[' . preg_quote(self::SPECIAL_CHARS, '/') . ']/', $password)) {
            $errors[] = "La password deve contenere almeno un carattere speciale (!@#$%^&*()_+-=[]{}|;:,.<>?)";
        } else if (preg_match('/[' . preg_quote(self::SPECIAL_CHARS, '/') . ']/', $password)) {
            $strength += 20;
        }

        // Bonus per lunghezza extra
        if (strlen($password) >= 12) {
            $strength += 10;
        }
        if (strlen($password) >= 16) {
            $strength += 10;
        }

        // Controllo password comuni
        if (self::isCommonPassword($password)) {
            $errors[] = "Questa password è troppo comune. Scegline una più sicura";
            $strength = max(0, $strength - 30);
        }

        // Controllo sequenze
        if (self::hasSequences($password)) {
            $errors[] = "La password contiene sequenze troppo semplici (es. 123, abc)";
            $strength = max(0, $strength - 20);
        }

        // Cap strength a 100
        $strength = min(100, $strength);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $strength,
            'strength_label' => self::getStrengthLabel($strength)
        ];
    }

    /**
     * Controlla se la password è tra quelle comuni
     */
    private static function isCommonPassword($password) {
        $commonPasswords = [
            'password', 'password123', '12345678', 'qwerty', 'abc123',
            'monkey', '1234567890', 'letmein', 'trustno1', 'dragon',
            'baseball', 'iloveyou', 'master', 'sunshine', 'ashley',
            'bailey', 'shadow', '123456', 'admin', 'root'
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Controlla sequenze semplici
     */
    private static function hasSequences($password) {
        $sequences = ['123', '234', '345', '456', '567', '678', '789',
            'abc', 'bcd', 'cde', 'def', 'efg', 'fgh', 'ghi',
            '111', '222', '333', '444', '555', '666', '777', '888', '999'];

        foreach ($sequences as $seq) {
            if (stripos($password, $seq) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ottiene l'etichetta testuale della forza
     */
    private static function getStrengthLabel($strength) {
        if ($strength >= 80) return 'Molto Forte';
        if ($strength >= 60) return 'Forte';
        if ($strength >= 40) return 'Media';
        if ($strength >= 20) return 'Debole';
        return 'Molto Debole';
    }

    /**
     * Genera una password casuale sicura
     */
    public static function generateSecure($length = 16) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = self::SPECIAL_CHARS;

        $password = '';

        // Assicura almeno un carattere di ogni tipo
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Riempi il resto
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Mischia
        return str_shuffle($password);
    }

    /**
     * Verifica se due password corrispondono
     */
    public static function passwordsMatch($password, $confirm) {
        return $password === $confirm;
    }

    /**
     * HTML per mostrare i requisiti
     */
    public static function getRequirementsHTML() {
        return '
        <div class="password-requirements" style="font-size: 13px; color: #666; margin-top: 10px;">
            <p style="margin-bottom: 8px; font-weight: 600;">La password deve contenere:</p>
            <ul style="padding-left: 20px; margin: 0;">
                <li>Almeno ' . self::MIN_LENGTH . ' caratteri</li>
                <li>Almeno una lettera maiuscola (A-Z)</li>
                <li>Almeno una lettera minuscola (a-z)</li>
                <li>Almeno un numero (0-9)</li>
                <li>Almeno un carattere speciale (!@#$%^&*()_+-=[]{}|;:,.<>?)</li>
            </ul>
        </div>';
    }
}

// ===== FUNZIONI HELPER =====

/**
 * Wrapper semplificato per validazione veloce
 */
function validatePassword($password) {
    return PasswordValidator::validate($password);
}

/**
 * Controlla se una password è valida (ritorna solo bool)
 */
function isPasswordValid($password) {
    $result = PasswordValidator::validate($password);
    return $result['valid'];
}