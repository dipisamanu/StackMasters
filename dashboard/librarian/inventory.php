<?php
/**
 * Gestione Inventario (Frontend - Formato A1-01)
 * File: dashboard/librarian/inventory.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';
require_once '../../src/Models/InventoryModel.php';

Session::requireRole('Bibliotecario');

$idLibro = $_GET['id_libro'] ?? 0;
if (!$idLibro) { header("Location: books.php"); exit; }

$bookModel = new BookModel();
$book = $bookModel->getById($idLibro);
if (!$book) { header("Location: books.php"); exit; }

$invModel = new InventoryModel();
$copies = $invModel->getCopiesByBookId($idLibro);

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
$oldData = $_SESSION['form_data'] ?? [];
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['form_data']);

$modalError = (!empty($oldData) && !empty($error)) ? $error : '';
if ($modalError) $error = '';

require_once '../../src/Views/layout/header.php';
?>

    <div class="container-fluid py-4">
        <div class="mb-3">
            <a href="books.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Torna al Catalogo</a>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase text-muted small mb-1">Inventario</h6>
                    <h2 class="fw-bold text-danger mb-0"><?= htmlspecialchars($book['titolo']) ?></h2>
                    <div class="text-muted mt-1">
                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($book['isbn']) ?></span>
                    </div>
                </div>
                <button class="btn btn-success shadow-sm" onclick="openCopyModal('add')"><i class="fas fa-plus me-2"></i>Aggiungi Copia</button>
            </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

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
                        <tr><td colspan="5" class="text-center p-5 text-muted">Nessuna copia fisica.</td></tr>
                    <?php else: ?>
                        <?php foreach ($copies as $c): ?>
                            <tr>
                                <td class="ps-4 font-monospace fw-bold text-primary"><?= htmlspecialchars($c['codice_rfid']) ?></td>
                                <td><span class="badge bg-white text-dark border"><?= htmlspecialchars($c['collocazione']) ?></span></td>
                                <td><?= htmlspecialchars($c['condizione']) ?></td>
                                <td><span class="badge bg-<?= $c['stato']=='DISPONIBILE'?'success':'secondary' ?>"><?= $c['stato'] ?></span></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light" onclick='openCopyModal("edit", <?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
                                    <form action="process-inventory.php" method="POST" class="d-inline" onsubmit="return confirm('Eliminare?');">
                                        <input type="hidden" name="action" value="delete_copy">
                                        <input type="hidden" name="id_libro" value="<?= $idLibro ?>">
                                        <input type="hidden" name="id_inventario" value="<?= $c['id_inventario'] ?>">
                                        <button class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="copyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalTitle">Gestione Copia</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="process-inventory.php" method="POST" id="copyForm">
                    <div class="modal-body">
                        <?php if ($modalError): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($modalError) ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="action" id="formAction" value="add_copy">
                        <input type="hidden" name="id_libro" value="<?= $idLibro ?>">
                        <input type="hidden" name="id_inventario" id="copyId">

                        <div class="mb-3">
                            <label class="form-label">RFID *</label>
                            <div class="input-group">
                                <input type="text" name="rfid" id="rfid" class="form-control font-monospace" required placeholder="SCAN-123">
                                <button type="button" class="btn btn-outline-secondary" onclick="generateRFID()"><i class="fas fa-random"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Collocazione *</label>
                            <div class="input-group">
                                <input type="text" name="collocazione" id="collocazione" class="form-control text-uppercase" required placeholder="Es. A1-01">
                                <button type="button" class="btn btn-outline-primary" onclick="suggestLocation()" id="btnSuggest"><i class="fas fa-magic"></i> Auto</button>
                            </div>
                            <div class="form-text text-muted">Formato: LetteraNumero-Numero (Es. A1-01)</div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label>Condizione</label>
                                <select name="condizione" id="condizione" class="form-select">
                                    <option value="BUONO">Buono</option>
                                    <option value="DANNEGGIATO">Danneggiato</option>
                                    <option value="PERSO">Perso</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>Stato</label>
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
                        <button type="submit" class="btn btn-success">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let copyModal;
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('copyModal');
            if (modalEl) {
                copyModal = new bootstrap.Modal(modalEl);
                <?php if (!empty($oldData)): ?>
                openCopyModal(<?= isset($oldData['id_inventario']) ? "'edit'" : "'add'" ?>, <?= json_encode($oldData) ?>, true);
                <?php endif; ?>
            }
        });

        function generateRFID() {
            const rfidInput = document.getElementById('rfid');
            if(rfidInput) {
                rfidInput.value = 'LIB-' + Date.now().toString().slice(-6) + '-' + Math.floor(Math.random()*100);
            }
        }

        function suggestLocation() {
            const btn = document.getElementById('btnSuggest');
            const input = document.getElementById('collocazione');

            if (!btn || !input) return;

            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            fetch('ajax-get-location.php')
                .then(async response => {
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error("Risposta non JSON:", text);
                        throw new Error("Il server ha risposto con dati non validi.");
                    }

                    if (!data.success) {
                        throw new Error(data.error || 'Errore sconosciuto');
                    }
                    return data;
                })
                .then(data => {
                    input.value = data.location;
                    input.classList.add('bg-warning', 'bg-opacity-25');
                    setTimeout(() => input.classList.remove('bg-warning', 'bg-opacity-25'), 500);
                })
                .catch(err => {
                    alert("Errore: " + err.message);
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }

        function openCopyModal(mode, data = null, isOldData = false) {
            const form = document.getElementById('copyForm');
            if(!form) return;

            if(!isOldData) form.reset();

            const title = document.getElementById('modalTitle');
            const action = document.getElementById('formAction');
            const rfid = document.getElementById('rfid');
            const coll = document.getElementById('collocazione');
            const cond = document.getElementById('condizione');
            const stato = document.getElementById('stato');
            const copyId = document.getElementById('copyId');
            const btnGen = document.getElementById('btnGenerate');

            if (mode === 'edit') {
                title.innerText = 'Modifica Copia';
                action.value = 'update_copy';
                copyId.value = data.id_inventario;

                rfid.value = isOldData ? data.rfid : data.codice_rfid;
                rfid.disabled = true;
                if(btnGen) btnGen.disabled = true;

                coll.value = data.collocazione;
                cond.value = data.condizione;
                stato.value = data.stato;
                stato.disabled = (data.stato === 'IN_PRESTITO');
            } else {
                title.innerText = 'Nuova Copia';
                action.value = 'add_copy';

                rfid.disabled = false;
                if(btnGen) btnGen.disabled = false;
                stato.value = 'DISPONIBILE';
                stato.disabled = false;

                if(isOldData) {
                    rfid.value = data.rfid;
                    coll.value = data.collocazione;
                }
            }
            copyModal.show();
        }
    </script>
<?php require_once '../../src/Views/layout/footer.php'; ?>