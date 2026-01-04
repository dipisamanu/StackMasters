<?php
/**
 * Gestione Inventario (Con Validazione e RFID Generator)
 * File: dashboard/librarian/inventory.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';
require_once '../../src/Models/InventoryModel.php';

Session::requireRole('Bibliotecario');

$idLibro = $_GET['id_libro'] ?? 0;
if (!$idLibro) {
    header("Location: books.php");
    exit;
}

// Dati Libro
$bookModel = new BookModel();
$book = $bookModel->getById($idLibro);

if (!$book) {
    $_SESSION['flash_error'] = "Libro non trovato.";
    header("Location: books.php");
    exit;
}

// Dati Copie
$invModel = new InventoryModel();
$copies = $invModel->getCopiesByBookId($idLibro);

// Gestione Messaggi e Errori Form
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
$oldData = $_SESSION['form_data'] ?? [];

unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['form_data']);

// Se c'è un oldData, significa che l'errore appartiene al modale
$modalError = '';
if (!empty($oldData) && !empty($error)) {
    $modalError = $error;
    $error = ''; // Rimuovi l'errore dalla pagina principale per non duplicarlo
}

require_once '../../src/Views/layout/header.php';
?>

    <div class="container-fluid py-4">

        <div class="mb-3">
            <a href="books.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Torna al Catalogo
            </a>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase text-muted small mb-1">Inventario</h6>
                    <h2 class="fw-bold text-danger mb-0"><?= htmlspecialchars($book['titolo']) ?></h2>
                    <div class="text-muted mt-1">
                        <span class="me-3"><i class="fas fa-pen-nib"></i> <?= htmlspecialchars($book['autori_nomi'] ?? 'N/D') ?></span>
                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($book['isbn']) ?></span>
                    </div>
                </div>
                <button class="btn btn-success shadow-sm" onclick="openCopyModal('add')">
                    <i class="fas fa-plus me-2"></i>Aggiungi Copia
                </button>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase small text-muted">
                    <tr>
                        <th class="ps-4">RFID</th>
                        <th>Collocazione</th>
                        <th>Condizione</th>
                        <th>Stato</th>
                        <th class="text-end pe-4">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($copies)): ?>
                        <tr>
                            <td colspan="5" class="text-center p-5 text-muted">
                                <i class="fas fa-box-open fa-2x mb-3 d-block text-secondary"></i>
                                Nessuna copia fisica registrata. Aggiungine una per renderla disponibile.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($copies as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="font-monospace fw-bold text-primary">
                                        <i class="fas fa-barcode me-2 text-muted"></i><?= htmlspecialchars($c['codice_rfid'] ?? 'N/D') ?>
                                    </div>
                                    <small class="text-muted" style="font-size:0.75rem;">ID Interno: <?= $c['id_inventario'] ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($c['collocazione']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $condMap = [
                                            'BUONO' => ['text-success', 'Buono'],
                                            'DANNEGGIATO' => ['text-warning', 'Danneggiato'],
                                            'PERSO' => ['text-danger', 'Perso']
                                    ];
                                    $cond = $condMap[$c['condizione']] ?? ['text-secondary', $c['condizione']];
                                    ?>
                                    <span class="<?= $cond[0] ?> fw-bold small"><i class="fas fa-circle me-1" style="font-size:8px;"></i><?= $cond[1] ?></span>
                                </td>
                                <td>
                                    <?php
                                    $stClass = match($c['stato']) {
                                        'DISPONIBILE' => 'bg-success',
                                        'IN_PRESTITO' => 'bg-warning text-dark',
                                        'PRENOTATO' => 'bg-info text-dark',
                                        'NON_IN_PRESTITO' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $stClass ?>"><?= $c['stato'] ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <button class="btn btn-light btn-sm text-primary" onclick='openCopyModal("edit", <?= json_encode($c) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="process-inventory.php" method="POST" class="d-inline" onsubmit="return confirm('Sicuro di voler eliminare questa copia?');">
                                            <input type="hidden" name="action" value="delete_copy">
                                            <input type="hidden" name="id_libro" value="<?= $idLibro ?>">
                                            <input type="hidden" name="id_inventario" value="<?= $c['id_inventario'] ?>">
                                            <button class="btn btn-light btn-sm text-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="copyModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalTitle">Gestione Copia</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form action="process-inventory.php" method="POST" id="copyForm">
                    <div class="modal-body">

                        <?php if (!empty($modalError)): ?>
                            <div class="alert alert-danger border-start border-danger border-4 fade show">
                                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($modalError) ?>
                            </div>
                        <?php endif; ?>

                        <input type="hidden" name="action" id="formAction" value="add_copy">
                        <input type="hidden" name="id_libro" value="<?= $idLibro ?>">
                        <input type="hidden" name="id_inventario" id="copyId">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Codice RFID *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                                <input type="text" name="rfid" id="rfid" class="form-control font-monospace" required placeholder="Es. SCAN-123456" minlength="3">
                                <button type="button" class="btn btn-outline-secondary" id="btnGenerate" onclick="generateRFID()" title="Genera un codice casuale">
                                    <i class="fas fa-random"></i> Genera
                                </button>
                            </div>
                            <div class="form-text">Usa il lettore o genera un codice se non hai l'etichetta.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Collocazione (Scaffale) *</label>
                            <input type="text" name="collocazione" id="collocazione" class="form-control text-uppercase" required placeholder="Es. A1-05" minlength="2">
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Condizione</label>
                                <select name="condizione" id="condizione" class="form-select">
                                    <option value="BUONO">Buono</option>
                                    <option value="DANNEGGIATO">Danneggiato</option>
                                    <option value="PERSO">Perso</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Stato</label>
                                <select name="stato" id="stato" class="form-select">
                                    <option value="DISPONIBILE">Disponibile</option>
                                    <option value="NON_IN_PRESTITO">Non Prestabile</option>
                                    <option value="PERSO">Perso</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                        <button type="submit" class="btn btn-success">Salva Copia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let copyModal;
        document.addEventListener('DOMContentLoaded', function() {
            copyModal = new bootstrap.Modal(document.getElementById('copyModal'));

            // Se c'è un errore di validazione (dati vecchi presenti), riapri il modale
            <?php if (!empty($oldData)): ?>
            const old = <?= json_encode($oldData) ?>;
            const mode = (old.action === 'update_copy') ? 'edit' : 'add';
            openCopyModal(mode, old, true);
            <?php endif; ?>
        });

        // Funzione per generare RFID casuale (Simulazione)
        function generateRFID() {
            // Formato: LIB-{TIMESTAMP}-{RANDOM 3 CIFRE}
            const timestamp = Date.now().toString().slice(-6);
            const random = Math.floor(Math.random() * 900) + 100;
            const code = 'LIB-' + timestamp + '-' + random;
            document.getElementById('rfid').value = code;
        }

        function openCopyModal(mode, data = null, isOldData = false) {
            const form = document.getElementById('copyForm');
            const btnGen = document.getElementById('btnGenerate');

            if (!isOldData) form.reset();

            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Modifica Copia';
                document.getElementById('formAction').value = 'update_copy';
                document.getElementById('copyId').value = data.id_inventario;

                // In modifica RFID non si tocca (integrità db)
                const rfidField = document.getElementById('rfid');
                rfidField.value = isOldData ? (data.rfid || '') : (data.codice_rfid || '');
                rfidField.disabled = true;
                btnGen.disabled = true;

                document.getElementById('collocazione').value = data.collocazione;
                document.getElementById('condizione').value = data.condizione;

                const statoSelect = document.getElementById('stato');
                statoSelect.value = data.stato;
                statoSelect.disabled = (data.stato === 'IN_PRESTITO');
            } else {
                // Modalità Aggiungi (Add)
                document.getElementById('modalTitle').innerText = 'Nuova Copia';
                document.getElementById('formAction').value = 'add_copy';

                const rfidField = document.getElementById('rfid');
                rfidField.disabled = false;
                btnGen.disabled = false;

                // Valori di default
                if(!isOldData) {
                    document.getElementById('stato').value = 'DISPONIBILE';
                    document.getElementById('condizione').value = 'BUONO';
                }

                document.getElementById('stato').disabled = false;

                // Se stiamo ripopolando dopo un errore
                if (isOldData) {
                    rfidField.value = data.rfid;
                    document.getElementById('collocazione').value = data.collocazione;
                    document.getElementById('condizione').value = data.condizione;
                    document.getElementById('stato').value = data.stato;
                }
            }

            copyModal.show();
        }
    </script>

<?php require_once '../../src/Views/layout/footer.php'; ?>