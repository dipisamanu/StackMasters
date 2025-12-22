<?php
namespace Ottaviodipisa\StackMasters\Controllers;

require_once __DIR__ . '/../Services/LoanService.php';

use Ottaviodipisa\StackMasters\Services\LoanService;

class LoanController
{
    private LoanService $service;

    public function __construct()
    {
        $this->service = new LoanService();
    }

    public function registraPrestito(int $utenteId, int $inventarioId): array
    {
        return $this->service->registraPrestito($utenteId, $inventarioId);
    }

    public function registraRestituzione(int $inventarioId, string $condizione = 'BUONO', ?string $commentoDanno = null): array
    {
        return $this->service->registraRestituzione($inventarioId, $condizione, $commentoDanno);
    }
}
