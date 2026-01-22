<?php
namespace Ottaviodipisa\StackMasters\Controllers;

require_once __DIR__ . '/../Services/LoanService.php';

use Exception;
use Ottaviodipisa\StackMasters\Services\LoanService;

class LoanController
{
    private LoanService $service;
    public PDO $db;

    // Configurazione multe
    private const GIORNI_TOLLERANZA = 3;
    private const IMPORTO_MULTA_GIORNALIERA = 0.50;

    // Configurazione prenotazioni
    private const ORE_RISERVA_PRENOTAZIONE = 48;

    public function __construct()
    {
        $this->service = new LoanService();
    }

    /**
     * @throws Exception
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        return $this->service->registraPrestito($utenteId, $inventarioId);
    }

    /**
     * @throws Exception
     */
    public function registraRestituzione(int $inventarioId, string $condizione = 'BUONO', ?string $commentoDanno = null): array
    {
        return $this->service->registraRestituzione($inventarioId, $condizione, $commentoDanno);
    }
}
