<?php
/**
 * Loan.php - Modello Deprecato (Wrapper per retrocompatibilità)
 * Percorso: src/Models/Loan.php
 *
 * @deprecated Utilizzare Services\LoanService per tutte le operazioni.
 */

namespace Ottaviodipisa\StackMasters\Models;

use Ottaviodipisa\StackMasters\Services\LoanService;
use Exception;

class Loan
{
    private LoanService $service;

    public function __construct()
    {
        $this->service = new LoanService();
    }

    /**
     * @deprecated Usa LoanService::registraPrestito
     * @throws Exception
     */
    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        return $this->service->registraPrestito($utenteId, $inventarioId);
    }

    /**
     * @deprecated Usa LoanService::registraRestituzione
     * @throws Exception
     */
    public function registraRestituzione(int $inventarioId, string $condizioneRientro, ?string $dannoCommento = null): array
    {
        // Adattatore per il formato di ritorno leggermente diverso se necessario,
        // ma LoanService è stato allineato per restituire array compatibili.
        return $this->service->registraRestituzione($inventarioId, $condizioneRientro, $dannoCommento);
    }

    /**
     * @deprecated Usa LoanService::gestisciPrenotazioniScadute
     * @throws Exception
     */
    public function gestisciPrenotazioniScadute(): array
    {
        return $this->service->gestisciPrenotazioniScadute();
    }
}
