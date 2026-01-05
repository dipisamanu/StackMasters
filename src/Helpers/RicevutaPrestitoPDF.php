<?php
namespace Ottaviodipisa\StackMasters\Helpers;

use TCPDF;

class RicevutaPrestitoPDF
{
    public static function genera(array $dati): void
    {
        $utente = $dati['details']['utente'];
        $copia  = $dati['details']['copia'];

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('StackMasters LMS');
        $pdf->SetAuthor('Biblioteca');
        $pdf->SetTitle('Ricevuta Prestito #' . $utente['id_utente']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        /* ================= HEADER ROSSO ================= */
        $pdf->SetFillColor(220, 53, 69); // #dc3545
        $pdf->Rect(0, 0, 210, 35, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetY(12);
        $pdf->Cell(0, 10, 'RICEVUTA PRESTITO', 0, 1, 'C');

        $pdf->Ln(15);

        /* ================= CONTENUTO ================= */
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 12);

        $html = '
        <style>
            h3 {
                color: #dc3545;
                border-bottom: 2px solid #dc3545;
                padding-bottom: 4px;
            }
            table {
                width: 100%;
            }
            td {
                padding: 6px;
            }
            .label {
                font-weight: bold;
                width: 35%;
            }
            .box {
                border: 1px solid #e5e7eb;
                background-color: #f9fafb;
            }
        </style>

        <h3>DATI UTENTE</h3>
        <table class="box" cellpadding="4">
            <tr>
                <td class="label">Nome</td>
                <td>' . htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) . '</td>
            </tr>
            <tr>
                <td class="label">ID Utente</td>
                <td>' . $utente['id_utente'] . '</td>
            </tr>
        </table>

        <br>

        <h3>DATI LIBRO</h3>
        <table class="box" cellpadding="4">
            <tr>
                <td class="label">Titolo</td>
                <td>' . htmlspecialchars($copia['titolo']) . '</td>
            </tr>
            <tr>
                <td class="label">Autori</td>
                <td>' . htmlspecialchars($copia['autori'] ?? '-') . '</td>
            </tr>
            <tr>
                <td class="label">ID Copia</td>
                <td>' . $copia['id_inventario'] . '</td>
            </tr>
        </table>

        <br>

        <h3>DETTAGLI PRESTITO</h3>
        <table class="box" cellpadding="4">
            <tr>
                <td class="label">Data Scadenza</td>
                <td><b style="color:#dc3545;">' . $dati['details']['data_scadenza'] . '</b></td>
            </tr>
        </table>
        ';

        if (!empty($dati['details']['messaggio_prenotazione'])) {
            $html .= '
            <br>
            <p style="color:#6b7280; font-style:italic;">
                ' . htmlspecialchars($dati['details']['messaggio_prenotazione']) . '
            </p>';
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        /* ================= FOOTER ================= */
        $pdf->SetY(-35);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(75, 85, 99);
        $pdf->Cell(0, 6, 'Biblioteca - StackMasters Library Management System', 0, 1, 'C');
        $pdf->Cell(0, 6, 'Firma Bibliotecario: _______________________________', 0, 1, 'R');

        $pdf->Output('ricevuta_prestito.pdf', 'I');
        exit;
    }
}
