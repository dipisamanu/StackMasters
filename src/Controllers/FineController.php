<?php

namespace Ottaviodipisa\StackMasters\Controllers;

use Ottaviodipisa\StackMasters\Models\Fine;
use Ottaviodipisa\StackMasters\Helpers\RicevutaPrestitoPDF;

/**
 * Controller per la gestione delle sanzioni, pagamenti e reportistica.
 * Gestisce le richieste provenienti dal pannello amministrativo.
 */
class FineController
{
    private Fine $fineModel;
    private RicevutaPrestitoPDF $pdfHelper;

    public function __construct()
    {
        $this->fineModel = new Fine();
        $this->pdfHelper = new RicevutaPrestitoPDF();
    }

    /**
     * Visualizza il pannello principale di gestione finanziaria.
     * Caricato da: dashboard/admin/finance.php
     */
    public function index()
    {
        $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

        $data = [
            'user' => $userId ? $this->fineModel->getUserBalance($userId) : null,
            'pending_details' => $userId ? $this->fineModel->getPendingDetails($userId) : [],
            'discount' => $userId ? $this->fineModel->getLoyaltyDiscount($userId) : 0,
            'top_debtors' => $this->fineModel->getTopDebtors()
        ];

        // Inclusione della vista specifica
        require_once __DIR__ . '/../../dashboard/admin/finance.php';
    }

    /**
     * Gestisce la registrazione di un pagamento (totale o parziale).
     */
    public function storePayment()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $amount = filter_input(INPUT_POST, 'pay_amount', FILTER_VALIDATE_FLOAT);

            if ($userId && $amount > 0) {
                try {
                    $result = $this->fineModel->processPayment($userId, $amount);

                    // Recupero dati per la quietanza PDF
                    $userData = $this->fineModel->getUserBalance($userId);
                    $pdfFile = $this->pdfHelper->generateFineReceipt($userData, $result);

                    header("Location: /StackMasters/dashboard/admin/finance.php?user_id=$userId&status=success&msg=Pagamento_registrato&pdf=$pdfFile");
                    exit();
                } catch (\Exception $e) {
                    header("Location: /StackMasters/dashboard/admin/finance.php?user_id=$userId&status=error&msg=Errore_elaborazione_pagamento");
                    exit();
                }
            }
        }
    }

    /**
     * Registra un addebito manuale (danni, smarrimento).
     */
    public function storeManualCharge()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_SPECIAL_CHARS);

            if ($userId && $amount > 0) {
                $this->fineModel->addManualCharge($userId, $amount, $reason);
                header("Location: /StackMasters/dashboard/admin/finance.php?user_id=$userId&status=success&msg=Addebito_inserito");
                exit();
            }
        }
    }

    /**
     * Visualizza il report contabile per la segreteria.
     */
    public function showAccountingReport()
    {
        $start = $_GET['start_date'] ?? date('Y-m-01');
        $end = $_GET['end_date'] ?? date('Y-m-d');

        $reportData = $this->fineModel->getAccountingReport($start, $end);

        // Carica la vista del report
        require_once __DIR__ . '/../../dashboard/admin/finance_report.php';
    }
}