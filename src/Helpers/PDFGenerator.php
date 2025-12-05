<?php
// FILE: DocumentGenerator.php

namespace Ottaviodipisa\StackMasters\Helpers;

/**
 * Classe Utility per gestire la generazione di documenti (PDF, Ricevute, ecc.).
 * NOTA: L'integrazione completa con una libreria PDF è qui simulata per brevità.
 */
class DocumentGenerator
{
    /**
     * Simula la generazione di una ricevuta PDF per una transazione di prestito.
     * @param array $prestitoDetails Dettagli completi della transazione di prestito.
     * @return string Il percorso del file PDF generato (simulato).
     * @throws \Exception Se la generazione fallisce.
     */
    public function generateLoanReceipt(array $prestitoDetails): string
    {
        // --- IMPLEMENTAZIONE NEL MONDO REALE: ---
        // 1. Inizializzare la libreria PDF (es. TCPDF, Dompdf).
        // 2. Caricare i dati del template (prestitoDetails).
        // 3. Renderizzare il template HTML in formato PDF.
        // 4. Salvare il PDF sul server (es. public/uploads/receipts/...).

        $prestitoId = $prestitoDetails['prestito_id'] ?? 'N/A';
        $dataScadenza = $prestitoDetails['data_scadenza'] ?? 'N/A';

        if (empty($prestitoDetails)) {
            throw new \Exception("Impossibile generare la ricevuta: Dettagli del prestito mancanti.");
        }

        $filename = "ricevuta_{$prestitoId}_" . time() . ".pdf";

        // Simulazione della creazione del file e del suo percorso
        $simulatedPath = "uploads/ricevute/{$filename}";

        // Restituire il percorso da usare/inviare dal Controller
        return $simulatedPath;
    }

    // Qui è possibile aggiungere in seguito il metodo generateFineReceipt()
}