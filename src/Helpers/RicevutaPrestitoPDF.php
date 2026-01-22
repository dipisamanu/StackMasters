<?php

namespace Ottaviodipisa\StackMasters\Helpers;

use TCPDF;

class RicevutaPrestitoPDF
{
    private static function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }

    public static function genera(array $dati): string
    {
        $utente = $dati['utente'] ?? null;
        $libri  = $dati['libri'] ?? [];
        $dataOp = $dati['data_operazione'] ?? date('d/m/Y H:i');

        if (!$utente) {
            return "";
        }

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('StackMasters LMS');
        $pdf->SetAuthor('Biblioteca ITIS Rossi');
        $pdf->SetTitle('Ricevuta di Prestito - ' . $utente['cognome']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Biblioteca Scolastica ITIS "A. Rossi"', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Via Legione Gallieno, 52 - 36100 Vicenza (VI)', 0, 1, 'L');
        $pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(191, 33, 33);
        $pdf->Cell(0, 15, 'RICEVUTA DI PRESTITO', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('helvetica', '', 11);
        $html = '
        <style>
            .section-title { font-weight: bold; font-size: 12pt; color: #333; border-bottom: 1px solid #ccc; margin-bottom: 5px; }
            .info-table td { padding: 5px; font-size: 10pt; }
        </style>
        <h4 class="section-title">Dati del Lettore</h4>
        <table class="info-table">
            <tr><td style="width: 25%;"><b>Nominativo:</b></td><td style="width: 75%;">' . htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) . '</td></tr>
            <tr><td><b>Codice Fiscale:</b></td><td>' . htmlspecialchars($utente['cf'] ?? 'N/D') . '</td></tr>
            <tr><td><b>Data Operazione:</b></td><td>' . $dataOp . '</td></tr>
        </table>
        <br><br>
        <h4 class="section-title">Riepilogo Volumi Consegnati</h4>';
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->SetFont('helvetica', 'B', 9);
        // CORREZIONE: Aggiunta colonna ISBN e larghezze aggiornate
        $header = ['ID', 'Titolo', 'ISBN', 'Condizione', 'Scadenza'];
        $w = [15, 70, 35, 30, 30];
        
        $pdf->SetFillColor(191, 33, 33);
        $pdf->SetTextColor(255, 255, 255);
        for($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '');

        foreach ($libri as $libro) {
            $titoloTroncato = self::truncate(htmlspecialchars($libro['titolo']), 40); // Tronca di più
            $condizione = strtoupper($libro['condizione'] ?? 'BUONO');
            $pdf->Cell($w[0], 6, $libro['id_inventario'], 'LR', 0, 'C');
            $pdf->Cell($w[1], 6, $titoloTroncato, 'LR', 0, 'L');
            $pdf->Cell($w[2], 6, $libro['isbn'], 'LR', 0, 'C');
            $pdf->Cell($w[3], 6, $condizione, 'LR', 0, 'C');
            $pdf->Cell($w[4], 6, date('d/m/Y', strtotime($libro['scadenza'])), 'LR', 0, 'C');
            $pdf->Ln();
        }
        $pdf->Cell(array_sum($w), 0, '', 'T');
        
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->MultiCell(0, 10, "L'utente dichiara di aver ricevuto i volumi sopra elencati nelle condizioni specificate. È tenuto a restituirli entro la data di scadenza indicata. In caso di ritardo, smarrimento o di un peggioramento delle condizioni del volume, verranno applicate le sanzioni previste dal regolamento della biblioteca.", 0, 'L');

        $pdf->SetY(-40);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(90, 10, 'Firma del Bibliotecario', 'T', 0, 'C');
        $pdf->Cell(10, 10, '', 0, 0, 'C');
        $pdf->Cell(90, 10, 'Firma del Lettore', 'T', 1, 'C');

        $fileName = "ricevuta_prestito_" . ($utente['id_utente'] ?? '0') . "_" . time() . ".pdf";
        $path = __DIR__ . "/../../public/assets/docs/" . $fileName;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $pdf->Output($path, 'F');
        return $fileName;
    }
}