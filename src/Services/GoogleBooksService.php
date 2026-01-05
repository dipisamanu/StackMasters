<?php
/**
 * Servizio per integrare Google Books API (Versione cURL)
 * File: src/Services/GoogleBooksService.php
 */

require_once __DIR__ . '/../Helpers/IsbnValidator.php';

class GoogleBooksService
{
    private const API_URL = 'https://www.googleapis.com/books/v1/volumes?q=isbn:';

    public function fetchByIsbn(string $isbn): ?array
    {
        // 1. Pulizia ISBN
        $cleanIsbn = IsbnValidator::clean($isbn);
        if (empty($cleanIsbn)) {
            return null;
        }

        $url = self::API_URL . $cleanIsbn;

        // 2. Inizializza cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Fondamentale per XAMPP/Localhost: ignora verifica SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Simula un browser reale
        curl_setopt($ch, CURLOPT_USERAGENT, 'BiblioSystem/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 3. Controllo Errori
        if ($response === false || $httpCode !== 200) {
            // Puoi scommentare per debug: error_log("Google API Error: $curlError");
            return null;
        }

        $data = json_decode($response, true);

        // 4. Verifica Risultati
        if (!isset($data['totalItems']) || $data['totalItems'] === 0 || !isset($data['items'][0]['volumeInfo'])) {
            return null;
        }

        $info = $data['items'][0]['volumeInfo'];

        // RECUPERO IMMAGINE (Preferiamo thumbnail o smallThumbnail)
        $imgUrl = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? '';
        // Fix: Google manda http, forziamo https per evitare warning
        $imgUrl = str_replace('http://', 'https://', $imgUrl);

        return [
            'titolo' => $info['title'] ?? '',
            'autore' => isset($info['authors']) ? implode(', ', $info['authors']) : '',
            'editore' => $info['publisher'] ?? '',
            'anno' => isset($info['publishedDate']) ? substr($info['publishedDate'], 0, 4) : '',
            'descrizione' => $info['description'] ?? '',
            'pagine' => $info['pageCount'] ?? 0,
            'isbn' => $cleanIsbn,
            'copertina' => $imgUrl // NUOVO CAMPO
        ];
    }
}