<?php

namespace Ottaviodipisa\StackMasters\Helpers;
/**
 * Helper per la validazione matematica degli ISBN
 * File: src/Helpers/IsbnValidator.php
 */
class IsbnValidator
{
    /**
     * Valida un ISBN (10 o 13 cifre)
     */
    public static function validate(string $isbn): bool
    {
        // Rimuove trattini e spazi
        $isbn = preg_replace('/[^0-9X]/i', '', $isbn);
        $isbn = strtoupper($isbn);

        if (strlen($isbn) === 10) {
            return self::validate10($isbn);
        } elseif (strlen($isbn) === 13) {
            return self::validate13($isbn);
        }

        return false; // Lunghezza errata
    }

    /**
     * Algoritmo ISBN-10 (Modulo 11)
     */
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

    /**
     * Algoritmo ISBN-13 (Modulo 10, pesi 1-3)
     */
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