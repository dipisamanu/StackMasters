<?php
/**
 * Servizio per integrare Open Library API (Fallback)
 * File: src/Services/OpenLibraryService.php
 */

require_once __DIR__ . '/../Helpers/IsbnValidator.php';

class OpenLibraryService
{
    private const API_URL = 'https://openlibrary.org/api/books';

    public function fetchByIsbn(string $isbn): ?array
    {
        $cleanIsbn = IsbnValidator::clean($isbn);
        if (empty($cleanIsbn)) {
            return null;
        }

        // Open Library usa il formato "ISBN:xxxxxxxx" come chiave
        $queryKey = "ISBN:" . $cleanIsbn;

        // Costruiamo l'URL: richiediamo formato JSON e dati completi (jscmd=data)
        $url = self::API_URL . "?bibkeys=" . $queryKey . "&format=json&jscmd=data";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BiblioSystem/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);

        // Se l'array è vuoto o non contiene la chiave richiesta, il libro non esiste
        if (empty($data) || !isset($data[$queryKey])) {
            return null;
        }

        $info = $data[$queryKey];

        // Normalizzazione Dati (Mapping dei campi Open Library ai nostri)

        // 1. Autori
        $autoriStr = '';
        if (isset($info['authors']) && is_array($info['authors'])) {
            $names = array_column($info['authors'], 'name');
            $autoriStr = implode(', ', $names);
        }

        // 2. Editore
        $editoreStr = '';
        if (isset($info['publishers']) && is_array($info['publishers'])) {
            $names = array_column($info['publishers'], 'name');
            $editoreStr = $names[0] ?? ''; // Prendiamo il primo
        }

        // 3. Anno (Spesso è una stringa tipo "Nov 2005" o "2005")
        $anno = '';
        if (isset($info['publish_date'])) {
            if (preg_match('/\d{4}/', $info['publish_date'], $matches)) {
                $anno = $matches[0];
            }
        }

        // 4. Descrizione (A volte è un oggetto, a volte stringa mancante)
        // Open Library non sempre restituisce la descrizione con jscmd=data, ma ci proviamo
        $descrizione = '';
        if (isset($info['excerpts']) && !empty($info['excerpts'])) {
            $descrizione = $info['excerpts'][0]['text'] ?? '';
        }

        $imgUrl = '';
        if (isset($info['cover']['medium'])) {
            $imgUrl = $info['cover']['medium'];
        } elseif (isset($data[$queryKey]['cover']['large'])) { // A volte struttura diversa
            $imgUrl = $data[$queryKey]['cover']['large'];
        } else {
            // Tentativo generico basato su ISBN
            $imgUrl = "https://covers.openlibrary.org/b/isbn/$cleanIsbn-M.jpg";
        }

        return [
            'titolo' => $info['title'] ?? '',
            'autore' => $autoriStr,
            'editore' => $editoreStr,
            'anno' => $anno,
            'descrizione' => $descrizione,
            'pagine' => $info['number_of_pages'] ?? 0,
            'isbn' => $cleanIsbn,
            'copertina' => $imgUrl
        ];
    }
}