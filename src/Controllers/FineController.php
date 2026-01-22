<?php

namespace Ottaviodipisa\StackMasters\Controllers;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Ottaviodipisa\StackMasters\Models\Fine;
use Ottaviodipisa\StackMasters\Helpers\RicevutaPagamentoPDF;

/**
 * Controller per la gestione delle sanzioni, pagamenti e reportistica.
 * Gestisce le richieste provenienti dal pannello amministrativo.
 */
class FineController
{
    private Fine $fineModel;
    private RicevutaPagamentoPDF $pdfHelper;

    public function __construct()
    {
        $this->fineModel = new Fine();
        $this->pdfHelper = new RicevutaPagamentoPDF();
    }

    /**
     * Visualizza il pannello principale di gestione finanziaria.
     * Caricato da: dashboard/admin/finance.php
     */
    public function index(): void
    {
        $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

        $data = [
            'user' => $userId ? $this->fineModel->getUserBalance($userId) : null,
            'fines' => $userId ? $this->fineModel->getPendingDetails($userId) : [],
            'discount' => $userId ? $this->fineModel->getLoyaltyDiscount($userId) : 0,
            'debtors' => $this->fineModel->getTopDebtors()
        ];

        require_once __DIR__ . '/../../dashboard/librarian/finance.php';
    }

    /**
     * Gestisce la registrazione di un pagamento.
     * Questa azione salda TUTTE le multe pendenti per un utente.
     */
    public function pay(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

            if ($userId) {
                try {
                    // Recupera i dati dell'utente e il totale da saldare PRIMA del pagamento
                    $userData = $this->fineModel->getUserData($userId); // Metodo per avere solo i dati anagrafici
                    $totalToPay = $this->fineModel->getTotalPendingAmount($userId);

                    if ($totalToPay <= 0) {
                        throw new Exception("Nessun debito da saldare.");
                    }

                    // Processa il pagamento (salda tutto)
                    $this->fineModel->settleAllFines($userId);

                    // Genera la quietanza PDF con i dati recuperati PRIMA
                    $pdfFile = $this->pdfHelper->generateQuietanza($userData, $totalToPay);

                    // Reindirizza con messaggio di successo e link al PDF
                    header("Location: /StackMasters/dashboard/librarian/finance.php?user_id=$userId&status=success&msg=Pagamento_registrato_con_successo&pdf=$pdfFile");
                    exit();
                } catch (Exception $e) {
                    header("Location: /StackMasters/dashboard/librarian/finance.php?user_id=$userId&status=error&msg=" . urlencode($e->getMessage()));
                    exit();
                }
            }
        }
         // Se non è una richiesta POST, reindirizza alla pagina principale di finanza
        header("Location: /StackMasters/dashboard/librarian/finance.php");
        exit();
    }


    /**
     * Registra un addebito manuale (danni, smarrimento).
     */
    #[NoReturn]
    public function charge(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $causa = filter_input(INPUT_POST, 'causa', FILTER_SANITIZE_SPECIAL_CHARS);
            $commento = filter_input(INPUT_POST, 'commento', FILTER_SANITIZE_SPECIAL_CHARS);


            if ($userId && $amount > 0) {
                $this->fineModel->addManualCharge($userId, $amount, $causa, $commento);
                header("Location: /StackMasters/dashboard/librarian/finance.php?user_id=$userId&status=success&msg=Addebito_manuale_inserito");
                exit();
            }
        }
         // Se non è una richiesta POST, reindirizza
        header("Location: /StackMasters/dashboard/librarian/finance.php");
        exit();
    }

    /**
     * Entry point per le azioni del controller.
     * Determina quale metodo eseguire in base al parametro 'action'.
     */
    public static function handleRequest(): void
    {
        $action = $_GET['action'] ?? 'index';
        $controller = new self();

        switch ($action) {
            case 'pay':
                $controller->pay();
                break;
            case 'charge':
                $controller->charge();
            case 'index':
            default:
                $controller->index();
                break;
        }
    }
}

// Esecuzione del gestore delle richieste
FineController::handleRequest();
