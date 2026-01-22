<?php
/**
 * Componente Paginazione Riutilizzabile (Avanzato)
 * 
 * Variabili richieste:
 * @var int $page Pagina corrente
 * @var int $totalPages Numero totale di pagine
 * @var string $search (Opzionale) Termine di ricerca da mantenere nei link
 */

// Funzione helper per costruire l'URL mantenendo i parametri GET
if (!function_exists('buildPageUrl')) {
    function buildPageUrl($pageNum, $searchStr = null): string
    {
        $params = $_GET;
        $params['page'] = $pageNum;
        
        // Gestione parametro ricerca (se passato esplicitamente o presente in GET)
        if ($searchStr !== null) {
            if ($searchStr === '') {
                unset($params['q']);
            } else {
                $params['q'] = $searchStr;
            }
        }
        
        return '?' . http_build_query($params);
    }
}

// Configurazione visualizzazione
$maxVisible = 5; // Numero massimo di pulsanti pagina visibili
$start = max(1, $page - floor($maxVisible / 2));
$end = min($totalPages, $start + $maxVisible - 1);

if ($end - $start + 1 < $maxVisible) {
    $start = max(1, $end - $maxVisible + 1);
}

if ($totalPages > 1): ?>
    <div class="py-4 mt-2">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                
                <!-- Pulsante Precedente -->
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link border-0 bg-transparent text-dark"
                       href="<?= buildPageUrl($page - 1, $search ?? null) ?>"
                       aria-label="Precedente">
                        <i class="fas fa-chevron-left me-1"></i> Precedente
                    </a>
                </li>

                <!-- Prima Pagina -->
                <?php if ($start > 1): ?>
                    <li class="page-item">
                        <a class="page-link border-0 bg-transparent text-dark" href="<?= buildPageUrl(1, $search ?? null) ?>">1</a>
                    </li>
                    <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link border-0 bg-transparent text-muted">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Pagine Centrali -->
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <?php if ($page == $i): ?>
                            <span class="page-link border-0 fw-bold bg-primary text-white rounded-3 mx-1 shadow-sm"><?= $i ?></span>
                        <?php else: ?>
                            <a class="page-link border-0 bg-transparent text-dark mx-1" href="<?= buildPageUrl($i, $search ?? null) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>

                <!-- Ultima Pagina -->
                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link border-0 bg-transparent text-muted">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link border-0 bg-transparent text-dark" href="<?= buildPageUrl($totalPages, $search ?? null) ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>

                <!-- Pulsante Successivo -->
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link border-0 bg-transparent text-dark"
                       href="<?= buildPageUrl($page + 1, $search ?? null) ?>"
                       aria-label="Successivo">
                        Successivo <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>
