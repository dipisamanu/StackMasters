<?php

namespace Ottaviodipisa\StackMasters\Helpers;

use TCPDF;

/**
 * Helper per la generazione delle ricevute di restituzione (Epic 5.9 - 5.11).
 * Utilizza la libreria TCPDF per produrre un documento PDF salvato sul server.
 */
class RicevutaRestituzionePDF
{
    /**
     * Genera la ricevuta PDF per un blocco di restituzioni.
     * * @param array $dati Deve contenere le chiavi 'utente', 'libri' (array di dettagli) e 'data_operazione'.
     * @return string Nome del file generato.
     */
    public static function genera(array $dati): string
    {
        // 1. Validazione e estrazione dati
        $utente = $dati['utente'] ?? null;
        $libri  = $dati['libri'] ?? [];
        $dataOp = $dati['data_operazione'] ?? date('d/m/Y H:i');
        $totaleMulte = 0;

        if (!$utente) {
            return "";
        }

        // 2. Inizializzazione TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('StackMasters LMS');
        $pdf->SetAuthor('Biblioteca ITIS Rossi');
        $pdf->SetTitle('Ricevuta Restituzione #' . ($utente['id_utente'] ?? '0'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 45, 15);
        $pdf->AddPage();

        /* ================= HEADER ESTETICO (ROSSO ITIS) ================= */
        $pdf->SetFillColor(191, 33, 33); // Rosso istituzionale #bf2121
        $pdf->Rect(0, 0, 210, 35, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetY(12);
        $pdf->Cell(0, 10, 'RICEVUTA DI RESTITUZIONE', 0, 1, 'C');

        /* ================= CONTENUTO HTML ================= */
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 11);

        // Generazione dinamica delle righe della tabella
        $rowsHtml = '';
        foreach ($libri as $libro) {
            $titolo = htmlspecialchars($libro['titolo']);
            $condizione = $libro['condizione'] ?? 'BUONO';
            $multa = (float)($libro['multa'] ?? 0);
            $totaleMulte += $multa;

            // Stile condizionale in base allo stato del libro
            $condStyle = ($condizione !== 'BUONO' && $condizione !== 'NUOVO') ? 'color: #bf2121; font-weight: bold;' : 'color: #28a745;';
            $multaText = ($multa > 0) ? number_format($multa, 2) . ' €' : '-';

            $rowsHtml .= '
            <tr>
                <td style="border: 1px solid #ddd; width: 15%; text-align: center;">' . $libro['id_inventario'] . '</td>
                <td style="border: 1px solid #ddd; width: 45%;">' . $titolo . '</td>
                <td style="border: 1px solid #ddd; width: 25%; text-align: center; ' . $condStyle . '">' . $condizione . '</td>
                <td style="border: 1px solid #ddd; width: 15%; text-align: right;">' . $multaText . '</td>
            </tr>';
        }

        $html = '
        <style>
            .section-title { color: #bf2121; font-weight: bold; font-size: 14pt; border-bottom: 1px solid #eee; }
            .info-table td { padding: 4px; }
            .items-table th { background-color: #f8f9fa; font-weight: bold; text-align: center; border: 1px solid #ddd; }
            .total-row { background-color: #fff5f5; font-weight: bold; }
        </style>

        <br><br>
        <h4 class="section-title">DATI UTENTE</h4>
        <table class="info-table">
            <tr><td><b>Utente:</b></td><td>' . htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) . '</td></tr>
            <tr><td><b>C.F.:</b></td><td>' . htmlspecialchars($utente['cf'] ?? 'N/D') . '</td></tr>
            <tr><td><b>Data Rientro:</b></td><td>' . $dataOp . '</td></tr>
        </table>

        <br><br>
        <h4 class="section-title">RIEPILOGO VOLUMI RESTITUITI</h4>
        <table cellpadding="5" class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">ID</th>
                    <th style="width: 45%;">Titolo Libro</th>
                    <th style="width: 25%;">Stato</th>
                    <th style="width: 15%;">Sanzione</th>
                </tr>
            </thead>
            <tbody>
                ' . $rowsHtml . '
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="border: 1px solid #ddd; text-align: right;">TOTALE ADDEBITI GENERATI:</td>
                    <td style="border: 1px solid #ddd; text-align: right; color: #bf2121;">' . number_format($totaleMulte, 2) . ' €</td>
                </tr>
            </tfoot>
        </table>

        <br><br>
        <p style="font-size: 9pt; color: #666; font-style: italic;">
            La presente ricevuta attesta la riconsegna fisica dei volumi sopra elencati. 
            In caso di penali indicate, l\'importo è stato addebitato sul portale utente e dovrà essere saldato secondo il regolamento della biblioteca.
        </p>
        ';

        $pdf->writeHTML($html, true, false, true, false, '');

        /* ================= FOOTER ================= */
        $pdf->SetY(-40);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 5, 'StackMasters LMS - Generato automaticamente dal sistema', 0, 1, 'C');
        $pdf->Ln(5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 10, 'Timbro e Firma Bibliotecario: _______________________________', 0, 1, 'R');

        // 3. Salvataggio fisico
        $fileName = "restituzione_" . ($utente['id_utente'] ?? '0') . "_" . time() . ".pdf";
        $dir = __DIR__ . "/../../public/assets/docs/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdf->Output($dir . $fileName, 'F');

        return $fileName;
    }
}