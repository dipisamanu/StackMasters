<?php
/**
 * Helper per la generazione di etichette Barcode
 * File: src/Helpers/LabelPrinter.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class LabelPrinter
{
    public function generatePdf(array $copies, string $bookTitle)
    {
        // Layout Etichette (A4 con etichette adesive standard 3x8 o simili)
        // Usiamo misure standard generiche
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('BiblioSystem');
        $pdf->SetTitle('Etichette: ' . $bookTitle);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 15, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        // Configurazione Barcode
        $style = [
            'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true,
            'cellfitalign' => '', 'border' => false, 'hpadding' => 'auto', 'vpadding' => 'auto',
            'fgcolor' => [0,0,0], 'bgcolor' => false, 'text' => true, 'font' => 'helvetica', 'fontsize' => 8
        ];

        // Griglia: 3 colonne, N righe
        $xStart = 10;
        $yStart = 15;
        $w = 63; // Larghezza etichetta
        $h = 38; // Altezza etichetta
        $gap = 2; // Spazio tra etichette

        $col = 0;
        $row = 0;

        foreach ($copies as $copy) {
            // Calcolo coordinate
            $x = $xStart + ($col * ($w + $gap));
            $y = $yStart + ($row * ($h + $gap));

            // Disegna bordo etichetta (utile per ritagliare)
            $pdf->SetLineStyle(['width' => 0.1, 'cap' => 'butt', 'join' => 'miter', 'dash' => 2, 'color' => [200, 200, 200]]);
            $pdf->Rect($x, $y, $w, $h);

            // Intestazione (Titolo troncato)
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($x + 2, $y + 2);
            $titleShort = (strlen($bookTitle) > 30) ? substr($bookTitle, 0, 30) . '...' : $bookTitle;
            $pdf->Cell($w - 4, 5, $titleShort, 0, 1, 'C');

            // Collocazione (Grande)
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetXY($x + 2, $y + 8);
            $pdf->Cell($w - 4, 8, $copy['collocazione'], 0, 1, 'C');

            // Barcode (CODE 128) - Usa RFID se c'è, altrimenti ID
            $code = !empty($copy['codice_rfid']) ? $copy['codice_rfid'] : 'COPY-' . $copy['id_inventario'];
            $pdf->write1DBarcode($code, 'C128', $x + 4, $y + 18, $w - 8, 16, 0.4, $style, 'N');

            // Gestione Griglia (3 colonne per riga)
            $col++;
            if ($col >= 3) {
                $col = 0;
                $row++;
                // Se fine pagina (circa 7 righe)
                if ($row >= 7) {
                    $pdf->AddPage();
                    $row = 0;
                }
            }
        }

        $pdf->Output('etichette.pdf', 'I');
    }
}