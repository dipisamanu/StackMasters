<?php

namespace Ottaviodipisa\StackMasters\Helpers;

class RicevutaPagamentoPDF
{
    /**
     * Genera un PDF di quietanza per il saldo delle sanzioni.
     * @param array $user Dati dell'utente
     * @param float $total Amount saldato
     * @return string Nome del file generato
     */
    public function generateQuietanza(array $user, float $total): string
    {
        // Inizializzazione TCPDF (Assicurati che composer abbia scaricato tecnickcom/tcpdf)
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Impostazioni Documento
        $pdf->SetCreator('StackMasters LMS');
        $pdf->SetAuthor('Biblioteca Scolastica');
        $pdf->SetTitle('Quietanza di Pagamento #' . $user['id_utente']);
        $pdf->setPrintHeader(false);

        $pdf->AddPage();

        // Stile e Contenuto
        $html = "
        <h1 style='color: #4f46e5; text-align: center;'>QUIETANZA DI PAGAMENTO</h1>
        <p style='text-align: center; font-size: 10pt;'>StackMasters Library Management System</p>
        <hr>
        <br><br>
        <table style='padding: 5px;'>
            <tr>
                <td><b>Utente:</b></td>
                <td>{$user['nome']} {$user['cognome']} (ID: {$user['id_utente']})</td>
            </tr>
            <tr>
                <td><b>Email:</b></td>
                <td>{$user['email']}</td>
            </tr>
            <tr>
                <td><b>Data Versamento:</b></td>
                <td>" . date('d/m/Y H:i') . "</td>
            </tr>
            <tr>
                <td style='font-size: 14pt;'><b>TOTALE SALDATO:</b></td>
                <td style='font-size: 14pt; color: #059669;'><b>€ " . number_format($total, 2) . "</b></td>
            </tr>
        </table>
        <br><br>
        <p>Si rilascia la presente quietanza a titolo liberatorio per tutte le pendenze (multe/danni) 
        maturate dall'utente fino alla data odierna.</p>
        <br><br><br>
        <p style='text-align: right;'>Firma del Bibliotecario: _______________________</p>
        ";

        $pdf->writeHTML($html, true, false, true, false, '');

        // Salvataggio fisico nella cartella assets
        $fileName = "quietanza_" . $user['id_utente'] . "_" . time() . ".pdf";
        $dir = __DIR__ . "/../../public/assets/docs/";

        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $pdf->Output($dir . $fileName, 'F');

        return $fileName;
    }
}