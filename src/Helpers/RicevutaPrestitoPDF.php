<?php

namespace Ottaviodipisa\StackMasters\Helpers;

use TCPDF;

class RicevutaPrestitoPDF
{
    /**
     * Genera la ricevuta di prestito e la salva sul server.
     * @param array $dati Contiene 'utente', 'libri', 'data_operazione'
     * @return string Nome del file PDF generato
     */
    public static function genera(array $dati): string
    {
        // Estrazione dati sicura
        $utente = $dati['utente'] ?? null;
        $libri  = $dati['libri'] ?? [];
        $dataOp = $dati['data_operazione'] ?? date('d/m/Y H:i');

        if (!$utente) {
            return "";
        }

        //var_dump($utente['cf']);

        // Inizializzazione TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('StackMasters LMS');
        $pdf->SetAuthor('Biblioteca');
        $pdf->SetTitle('Ricevuta Prestito #' . ($utente['id_utente'] ?? '0'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 45, 15); // Margini per non sovrapporsi all'header rosso
        $pdf->AddPage();

        /* ================= HEADER ESTETICO ================= */
        $pdf->SetFillColor(191, 33, 33); // Rosso ITIS Rossi (#bf2121)
        $pdf->Rect(0, 0, 210, 35, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetY(12);
        $pdf->Cell(0, 10, 'RICEVUTA DI PRESTITO', 0, 1, 'C');

        /* ================= CONTENUTO HTML ================= */
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 11);

        // Generazione righe tabella libri
        $rowsHtml = '';
        foreach ($libri as $libro) {
            $titolo = htmlspecialchars($libro['titolo']);
            $scadenza = date('d/m/Y', strtotime($libro['scadenza']));
            $rowsHtml .= '
            <tr>
                <td style="border: 1px solid #ddd; width: 15%; text-align: center;">' . $libro['id_inventario'] . '</td>
                <td style="border: 1px solid #ddd; width: 60%;">' . $titolo . '</td>
                <td style="border: 1px solid #ddd; width: 25%; text-align: center; font-weight: bold; color: #bf2121;">' . $scadenza . '</td>
            </tr>';
        }

        $html = '
        <style>
            .section-title { color: #bf2121; font-weight: bold; font-size: 14pt; border-bottom: 1px solid #eee; }
            .info-table td { padding: 4px; }
            .items-table th { background-color: #f8f9fa; font-weight: bold; text-align: center; border: 1px solid #ddd; }
        </style>

        <br><br>
        <h4 class="section-title">DETTAGLI UTENTE</h4>
        <table class="info-table">
            <tr><td><b>Nominativo:</b></td><td>' . htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) . '</td></tr>
            <tr><td><b>Codice Fiscale:</b></td><td>' . htmlspecialchars($utente['cf'] ?? 'N/D') . '</td></tr>
            <tr><td><b>Data Operazione:</b></td><td>' . $dataOp . '</td></tr>
        </table>

        <br><br>
        <h4 class="section-title">VOLUMI IN PRESTITO</h4>
        <table cellpadding="5" class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">ID</th>
                    <th style="width: 60%;">Titolo Libro</th>
                    <th style="width: 25%;">Scadenza</th>
                </tr>
            </thead>
            <tbody>
                ' . $rowsHtml . '
            </tbody>
        </table>

        <br><br>
        <p style="font-size: 9pt; color: #666; font-style: italic;">
            Si ricorda di restituire i volumi entro la data indicata. 
            In caso di smarrimento o danneggiamento verranno applicate le sanzioni previste dal regolamento.
        </p>
        ';

        $pdf->writeHTML($html, true, false, true, false, '');

        /* ================= FOOTER ================= */
        $pdf->SetY(-40);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 5, 'StackMasters Library Management System - Ricevuta generata automaticamente', 0, 1, 'C');
        $pdf->Ln(5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 10, 'Firma Bibliotecario: _______________________________', 0, 1, 'R');

        // Salvataggio fisico del file
        $fileName = "ricevuta_" . ($utente['id_utente'] ?? '0') . "_" . time() . ".pdf";
        $path = __DIR__ . "/../../public/assets/docs/" . $fileName;

        // Assicuriamoci che la cartella esista
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        // 'F' salva il file sul disco invece di inviarlo al buffer di uscita
        $pdf->Output($path, 'F');

        return $fileName;
    }
}