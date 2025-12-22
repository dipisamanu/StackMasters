<?php
require_once __DIR__ . '/tcpdf/tcpdf.php';

class RicevutaPrestitoPDF
{
    public static function genera(array $dati)
    {
        $pdf = new TCPDF();
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Ricevuta Prestito', 0, 1, 'C');

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 6, 'Utente: ' . $dati['details']['utente']['nome'], 0, 1);
        $pdf->Cell(0, 6, 'Libro: ' . $dati['details']['copia']['titolo'], 0, 1);
        $pdf->Cell(0, 6, 'Scadenza: ' . $dati['details']['data_scadenza'], 0, 1);

        $pdf->Output('ricevuta_prestito.pdf', 'I'); // apre PDF nel browser
        exit;
    }
}
