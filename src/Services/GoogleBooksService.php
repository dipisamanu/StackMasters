<?php
/**
 * Servizio per integrare Google Books API
 * File: src/Services/GoogleBooksService.php
 */

require_once __DIR__ . '/../Helpers/IsbnValidator.php';

class GoogleBooksService
{
    // Aggiungo &country=IT per tentare di ottenere prezzi italiani
    private const API_URL = 'https://www.googleapis.com/books/v1/volumes?country=IT&q=isbn:';

    public function fetchByIsbn(string $isbn): ?array
    {
        $cleanIsbn = IsbnValidator::clean($isbn);
        if (empty($cleanIsbn)) return null;

        $url = self::API_URL . $cleanIsbn;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BiblioSystem/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) return null;

        $data = json_decode($response, true);

        if (!isset($data['totalItems']) || $data['totalItems'] === 0 || !isset($data['items'][0]['volumeInfo'])) {
            return null;
        }

        $item = $data['items'][0];
        $info = $item['volumeInfo'];
        $sale = $item['saleInfo'] ?? []; // Qui ci sono i prezzi

        $imgUrl = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? '';
        $imgUrl = str_replace('http://', 'https://', $imgUrl);

        $categories = $info['categories'] ?? [];

        // Estrazione prezzo copertina
        // Cerchiamo prima il prezzo di listino, poi quello al dettaglio
        $prezzo = 0.00;
        if (isset($sale['listPrice']['amount'])) {
            $prezzo = (float)$sale['listPrice']['amount'];
        } elseif (isset($sale['retailPrice']['amount'])) {
            $prezzo = (float)$sale['retailPrice']['amount'];
        }

        return [
            'titolo' => $info['title'] ?? '',
            'autore' => isset($info['authors']) ? implode(', ', $info['authors']) : '',
            'editore' => $info['publisher'] ?? '',
            'anno' => isset($info['publishedDate']) ? substr($info['publishedDate'], 0, 4) : '',
            'descrizione' => $info['description'] ?? '',
            'pagine' => $info['pageCount'] ?? 0,
            'isbn' => $cleanIsbn,
            'copertina' => $imgUrl,
            'categorie' => $categories,
            'prezzo' => $prezzo // NUOVO CAMPO
        ];
    }
}