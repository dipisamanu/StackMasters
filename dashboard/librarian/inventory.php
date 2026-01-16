<?php
/**
 * Gestione Inventario (Con Stati Smarrito/Fuori Catalogo)
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
                <div class="btn-group">
                    <a href="print-labels.php?id_libro=<?= $idLibro ?>" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print me-2"></i>Stampa Etichette</a>
                    <button class="btn btn-success shadow-sm" onclick="openCopyModal('add')"><i class="fas fa-plus me-2"></i>Aggiungi Copia</button>
                </div>
            </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card shadow border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                    <tr><th class="ps-4">RFID</th><th>Collocazione</th><th>Condizione</th><th>Stato</th><th class="text-end pe-4">Azioni</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($copies)): ?><tr><td colspan="5" class="text-center p-5 text-muted">Nessuna copia fisica.</td></tr><?php else: ?>
                        <?php foreach ($copies as $c): ?>
                            <tr class="<?= ($c['stato'] == 'SMARRITO' || $c['stato'] == 'FUORI_CATALOGO') ? 'table-danger text-muted' : '' ?>">
                                <td class="ps-4 font-monospace fw-bold text-primary"><?= htmlspecialchars($c['codice_rfid']??'N/D') ?></td>
                                <td><span class="badge bg-white text-dark border"><?= htmlspecialchars($c['collocazione']) ?></span></td>
                                <td><?= htmlspecialchars($c['condizione']) ?></td>
                                <td>
                                    <?php
                                    $bg = 'secondary';
                                    if($c['stato']=='DISPONIBILE') $bg='success';
                                    if($c['stato']=='SMARRITO') $bg='danger';
                                    if($c['stato']=='FUORI_CATALOGO') $bg='dark';
                                    ?>
                                    <span class="badge bg-<?= $bg ?>"><?= $c['stato'] ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light" onclick='openCopyModal("edit", <?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
                                    <form action="process-inventory.php" method="POST" class="d-inline" onsubmit="return confirm('Eliminare fisicamente? Usa lo stato Fuori Catalogo se vuoi tenere lo storico.');">
                                        <input type="hidden" name="action" value="delete_copy">
                                        <input type="hidden" name="id_libro" value="<?= $idLibro ?>">
                                        <input type="hidden" name="id_inventario" value="<?= $c['id_inventario'] ?>">
                                        <button class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
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
                        <?php if ($modalError): ?><div class="alert alert-danger"><?= htmlspecialchars($modalError) ?></div><?php endif; ?>
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
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label>Condizione</label>
                                <select name="condizione" id="condizione" class="form-select">
                                    <option value="BUONO">Buono</option>
                                    <option value="DANNEGGIATO">Danneggiato</option>
                                    <option value="SMARRITO">Smarrito</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>Stato</label>
                                <select name="stato" id="stato" class="form-select">
                                    <option value="DISPONIBILE">Disponibile</option>
                                    <option value="NON_IN_PRESTITO">Non Prestabile</option>
                                    <option value="SMARRITO">Smarrito</option>
                                    <option value="FUORI_CATALOGO">Fuori Catalogo</option>
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
                <?php if (!empty($oldData)): ?> openCopyModal(<?= isset($oldData['id_inventario']) ? "'edit'" : "'add'" ?>, <?= json_encode($oldData) ?>, true); <?php endif; ?>
            }
        });

        function generateRFID() {
            document.getElementById('rfid').value = 'LIB-' + Date.now().toString().slice(-6) + '-' + Math.floor(Math.random()*100);
        }

        function suggestLocation() {
            const btn = document.getElementById('btnSuggest');
            const input = document.getElementById('collocazione');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btn.disabled=true;

            fetch('ajax-get-location.php')
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        input.value = data.location;
                    } else alert("Errore: " + data.error);
                })
                .catch(e => alert("Errore AJAX."))
                .finally(() => { btn.innerHTML = orig; btn.disabled=false; });
        }

        function openCopyModal(mode, data = null, isOldData = false) {
            document.getElementById('copyForm').reset();
            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Modifica Copia';
                document.getElementById('formAction').value = 'update_copy';
                document.getElementById('copyId').value = data.id_inventario;
                document.getElementById('rfid').value = isOldData ? data.rfid : data.codice_rfid;
                document.getElementById('rfid').disabled = true;
                document.getElementById('collocazione').value = data.collocazione;
                document.getElementById('condizione').value = data.condizione;
                document.getElementById('stato').value = data.stato;
                // Se è in prestito, non puoi cambiare lo stato manualmente per evitare incongruenze
                document.getElementById('stato').disabled = (data.stato === 'IN_PRESTITO');
            } else {
                document.getElementById('modalTitle').innerText = 'Nuova Copia';
                document.getElementById('formAction').value = 'add_copy';
                document.getElementById('rfid').disabled = false;
                document.getElementById('stato').value = 'DISPONIBILE';
                document.getElementById('stato').disabled = false;
            }
            copyModal.show();
        }
    </script>
<?php require_once '../../src/Views/layout/footer.php'; ?>