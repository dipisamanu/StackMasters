<?php
/**
 * Helper per la validazione e pulizia degli ISBN
 * File: src/Helpers/IsbnValidator.php
 */

class IsbnValidator
{
    /**
     * Pulisce l'ISBN rimuovendo trattini, spazi e rendendolo maiuscolo.
     * Restituisce solo numeri e 'X' finale.
     */
    public static function clean(string $isbn): string
    {
        // Rimuove tutto tranne numeri e X
        return strtoupper(preg_replace('/[^0-9X]/i', '', $isbn));
    }

    /**
     * Valida un ISBN (10 o 13 cifre) usando il checksum.
     */
    public static function validate(string $isbn): bool
    {
        $isbn = self::clean($isbn);

        if (strlen($isbn) === 10) {
            return self::validate10($isbn);
        } elseif (strlen($isbn) === 13) {
            return self::validate13($isbn);
        }

        return false;
    }

    private static function validate10(string $isbn): bool
    {
        if (!preg_match('/^\d{9}[\d|X]$/', $isbn)) return false;

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int)$isbn[$i]) * (10 - $i);
        }

        $checksum = $isbn[9];
        $sum += ($checksum === 'X') ? 10 : (int)$checksum;

        return ($sum % 11 === 0);
    }

    private static function validate13(string $isbn): bool
    {
        if (!preg_match('/^\d{13}$/', $isbn)) return false;

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$isbn[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $calcCheck = (10 - ($sum % 10)) % 10;
        return $calcCheck === (int)$isbn[12];
    }
}