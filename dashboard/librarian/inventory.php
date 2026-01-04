<?php
/**
 * Dettaglio Inventario per Libro
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

// FIX: Usa il BookModel per recuperare i dati del libro (Autori inclusi)
$bookModel = new BookModel();
$book = $bookModel->getById($idLibro);

if (!$book) {
    $_SESSION['flash_error'] = "Libro non trovato.";
    header("Location: books.php");
    exit;
}

// Recupera le copie
$invModel = new InventoryModel();
$copies = $invModel->getCopiesByBookId($idLibro);

// Messaggi sessione
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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
                    <h6 class="text-uppercase text-muted small mb-1">Gestione Inventario</h6>
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
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
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
                                <i class="fas fa-box-open fa-2x mb-3 d-block"></i>
                                Nessuna copia fisica presente per questo titolo.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($copies as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="font-monospace fw-bold text-primary">
                                        <i class="fas fa-barcode me-2 text-muted"></i><?= htmlspecialchars($c['codice_rfid'] ?? 'N/D') ?>
                                    </div>
                                    <small class="text-muted" style="font-size:0.75rem;">ID Copia: <?= $c['id_inventario'] ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-white text-dark border">
                                        <?= htmlspecialchars($c['collocazione']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $condClass = match($c['condizione']) {
                                        'BUONO' => 'text-success',
                                        'DANNEGGIATO' => 'text-warning',
                                        'PERSO' => 'text-danger',
                                        default => 'text-secondary'
                                    };
                                    ?>
                                    <span class="<?= $condClass ?> fw-semibold small">
                                        <?= ucfirst(strtolower($c['condizione'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = match($c['stato']) {
                                        'DISPONIBILE' => 'bg-success',
                                        'IN_PRESTITO' => 'bg-warning text-dark',
                                        'PRENOTATO' => 'bg-info text-dark',
                                        'NON_IN_PRESTITO' => 'bg-secondary',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $c['stato'] ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <button class="btn btn-light btn-sm text-primary" onclick='openCopyModal("edit", <?= json_encode($c) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="process-inventory.php" method="POST" class="d-inline" onsubmit="return confirm('Sei sicuro di voler eliminare questa copia fisica?');">
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalTitle">Gestione Copia</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="process-inventory.php" method="POST" id="copyForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add_copy">
                        <input type="hidden" name="id_libro" value="<?= $idLibro ?>">
                        <input type="hidden" name="id_inventario" id="copyId">

                        <div class="mb-3">
                            <label class="form-label">Codice RFID *</label>
                            <input type="text" name="rfid" id="rfid" class="form-control font-monospace" required placeholder="Scansiona o digita RFID">
                            <small class="text-muted">Se l'RFID non esiste, verrà creato automaticamente.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Collocazione (Scaffale) *</label>
                            <input type="text" name="collocazione" id="collocazione" class="form-control" required placeholder="Es. A1-05">
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-success">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let copyModal;
        document.addEventListener('DOMContentLoaded', function() {
            copyModal = new bootstrap.Modal(document.getElementById('copyModal'));
        });

        function openCopyModal(mode, data = null) {
            const form = document.getElementById('copyForm');

            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Modifica Copia';
                document.getElementById('formAction').value = 'update_copy';
                document.getElementById('copyId').value = data.id_inventario;

                document.getElementById('rfid').value = data.codice_rfid;
                document.getElementById('rfid').disabled = true; // In modifica l'RFID è bloccato

                document.getElementById('collocazione').value = data.collocazione;
                document.getElementById('condizione').value = data.condizione;

                const statoSelect = document.getElementById('stato');
                statoSelect.value = data.stato;
                // Se è in prestito, disabilita il cambio stato manuale
                statoSelect.disabled = (data.stato === 'IN_PRESTITO');
            } else {
                document.getElementById('modalTitle').innerText = 'Nuova Copia';
                document.getElementById('formAction').value = 'add_copy';
                form.reset();

                document.getElementById('rfid').disabled = false;
                document.getElementById('stato').value = 'DISPONIBILE';
                document.getElementById('stato').disabled = false;
            }

            copyModal.show();
        }
    </script>

<?php require_once '../../src/Views/layout/footer.php'; ?>