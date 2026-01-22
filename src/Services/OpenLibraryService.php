<?php
/**
 * Servizio per integrare Open Library API
 * File: src/Services/OpenLibraryService.php
 */

require_once __DIR__ . '/../Helpers/IsbnValidator.php';

class OpenLibraryService
{
    private const API_URL = 'https://openlibrary.org/api/books';

    public function fetchByIsbn(string $isbn): ?array
    {
        $cleanIsbn = IsbnValidator::clean($isbn);
        if (empty($cleanIsbn)) return null;

        $queryKey = "ISBN:" . $cleanIsbn;
        $url = self::API_URL . "?bibkeys=" . $queryKey . "&format=json&jscmd=data";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BiblioSystem/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) return null;

        $data = json_decode($response, true);

        if (empty($data) || !isset($data[$queryKey])) return null;

        $info = $data[$queryKey];

        $autoriStr = '';
        if (isset($info['authors']) && is_array($info['authors'])) {
            $names = array_column($info['authors'], 'name');
            $autoriStr = implode(', ', $names);
        }

        $editoreStr = '';
        if (isset($info['publishers']) && is_array($info['publishers'])) {
            $names = array_column($info['publishers'], 'name');
            $editoreStr = $names[0] ?? '';
        }

        $anno = '';
        if (isset($info['publish_date']) && preg_match('/\d{4}/', $info['publish_date'], $matches)) {
            $anno = $matches[0];
        }

        $descrizione = '';
        if (!empty($info['excerpts'])) {
            $descrizione = $info['excerpts'][0]['text'] ?? '';
        }

        if (isset($info['cover']['medium'])) {
            $imgUrl = $info['cover']['medium'];
        } elseif (isset($data[$queryKey]['cover']['large'])) {
            $imgUrl = $data[$queryKey]['cover']['large'];
        } else {
            $imgUrl = "https://covers.openlibrary.org/b/isbn/$cleanIsbn-M.jpg";
        }

        // Estrazione Soggetti
        $categorie = [];
        if (isset($info['subjects']) && is_array($info['subjects'])) {
            $slice = array_slice($info['subjects'], 0, 3);
            $categorie = array_column($slice, 'name');
        }

        return [
            'titolo' => $info['title'] ?? '',
            'autore' => $autoriStr,
            'editore' => $editoreStr,
            'anno' => $anno,
            'descrizione' => $descrizione,
            'pagine' => $info['number_of_pages'] ?? 0,
            'isbn' => $cleanIsbn,
            'copertina' => $imgUrl,
            'categorie' => $categorie // Array fondamentale!
        ];
    }
}