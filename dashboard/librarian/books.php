<?php
/**
 * Gestione Catalogo Libri (Versione Pulita)
 * File: dashboard/librarian/books.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';

Session::requireRole('Bibliotecario');

$bookModel = new BookModel();

$search = $_GET['q'] ?? '';
$filterAvailable = isset($_GET['available']) && $_GET['available'] === 'on';
$filters = $filterAvailable ? ['solo_disponibili' => true] : [];

$books = [];
$sysError = '';
try {
    $books = $bookModel->getAll($search, $filters);
} catch (Exception $e) {
    $sysError = "Errore Sistema: " . $e->getMessage();
}

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
$oldData = $_SESSION['form_data'] ?? [];
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['form_data']);

$modalError = '';
if (!empty($oldData) && !empty($error)) {
    $modalError = $error;
    $error = '';
}

require_once '../../src/Views/layout/header.php';
?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-danger"><i class="fas fa-book me-2"></i>Gestione Catalogo</h2>
                <p class="text-muted small mb-0">Catalogo dei titoli disponibili in biblioteca.</p>
            </div>
            <button class="btn btn-danger shadow-sm" onclick="openModal('create')">
                <i class="fas fa-plus me-2"></i>Nuovo Titolo
            </button>
        </div>

        <?php if ($error || $sysError): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error . $sysError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body bg-light rounded">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-start-0 ps-0"
                                   placeholder="Cerca titolo, autore, ISBN..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" name="available" id="avail"
                                    <?= $filterAvailable ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label class="form-check-label" for="avail">Solo disponibili</label>
                        </div>
                    </div>
                    <div class="col-md-1 text-end">
                        <?php if($search || $filterAvailable): ?>
                            <a href="books.php" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-times"></i></a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-dark btn-sm w-100">Vai</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase small text-muted">
                    <tr>
                        <th class="ps-4">Libro</th>
                        <th>Dettagli</th>
                        <th>ISBN</th>
                        <th class="text-center">Copie</th>
                        <th class="text-end pe-4">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($books)): ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted">Nessun libro trovato.</td></tr>
                    <?php else: ?>
                        <?php foreach ($books as $b): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($b['titolo']) ?></div>
                                    <div class="small text-muted"><i class="fas fa-user-edit me-1"></i><?= htmlspecialchars($b['autori_nomi'] ?? 'N/D') ?></div>
                                </td>
                                <td>
                                    <small class="d-block">Editore: <strong><?= htmlspecialchars($b['editore']) ?></strong></small>
                                    <small class="d-block">Anno: <strong><?= $b['anno_uscita'] ? date('Y', strtotime($b['anno_uscita'])) : '-' ?></strong></small>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['isbn']) ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $b['copie_disponibili'] > 0 ? 'success' : 'secondary' ?>">
                                        <?= $b['copie_disponibili'] ?> / <?= $b['copie_totali'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="inventory.php?id_libro=<?= $b['id_libro'] ?>" class="btn btn-light btn-sm text-success" title="Inventario"><i class="fas fa-boxes"></i></a>
                                        <button class="btn btn-light btn-sm text-primary" onclick='openModal("edit", <?= json_encode($b) ?>)'><i class="fas fa-edit"></i></button>
                                        <form action="process-book.php" method="POST" class="d-inline" onsubmit="return confirm('Eliminare questo libro?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_libro" value="<?= $b['id_libro'] ?>">
                                            <button class="btn btn-light btn-sm text-danger"><i class="fas fa-trash"></i></button>
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

    <div class="modal fade" id="bookModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalTitle">Gestione Libro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form action="process-book.php" method="POST" id="bookForm">
                    <div class="modal-body">
                        <?php if (!empty($modalError)): ?>
                            <div class="alert alert-danger fade show border-start border-danger border-4">
                                <?= htmlspecialchars($modalError) ?>
                            </div>
                        <?php endif; ?>

                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id_libro" id="bookId">

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Titolo *</label>
                                <input type="text" name="titolo" id="titolo" class="form-control" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Autore (Nome Cognome) *</label>
                                <input type="text" name="autore" id="autore" class="form-control" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Editore</label>
                                <input type="text" name="editore" id="editore" class="form-control" maxlength="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Anno</label>
                                <input type="number" name="anno" id="anno" class="form-control" min="1400" max="<?= date('Y')+2 ?>" placeholder="YYYY">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ISBN</label>
                                <input type="text" name="isbn" id="isbn" class="form-control" maxlength="17" placeholder="10 o 13 cifre">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pagine</label>
                                <input type="number" name="pagine" id="pagine" class="form-control" min="1">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrizione</label>
                                <textarea name="descrizione" id="descrizione" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-danger">Salva Libro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let bookModal;
        document.addEventListener('DOMContentLoaded', function() {
            bookModal = new bootstrap.Modal(document.getElementById('bookModal'));

            <?php if (!empty($oldData)): ?>
            const old = <?= json_encode($oldData) ?>;
            const mode = old.id_libro ? 'edit' : 'create';
            openModal(mode, old, true);
            <?php endif; ?>
        });

        function openModal(mode, data = null, isOldData = false) {
            const form = document.getElementById('bookForm');

            if(!isOldData) form.reset();

            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Modifica Libro';
                document.getElementById('formAction').value = 'update';
                document.getElementById('bookId').value = data.id_libro;

                document.getElementById('titolo').value = data.titolo;
                document.getElementById('autore').value = isOldData ? (data.autore || '') : (data.autori_nomi || '');
                document.getElementById('editore').value = data.editore;

                let anno = data.anno_uscita || data.anno || '';
                if (anno.length > 4) anno = anno.substring(0, 4);
                document.getElementById('anno').value = anno;

                document.getElementById('isbn').value = data.isbn;
                document.getElementById('pagine').value = data.numero_pagine || data.pagine || '';
                document.getElementById('descrizione').value = data.descrizione;
            } else {
                document.getElementById('modalTitle').innerText = 'Nuovo Libro';
                document.getElementById('formAction').value = 'create';

                if(isOldData) {
                    document.getElementById('titolo').value = data.titolo;
                    document.getElementById('autore').value = data.autore;
                    document.getElementById('editore').value = data.editore;
                    document.getElementById('anno').value = data.anno;
                    document.getElementById('isbn').value = data.isbn;
                    document.getElementById('pagine').value = data.pagine;
                    document.getElementById('descrizione').value = data.descrizione;
                }
            }

            bookModal.show();
        }
    </script>

<?php require_once '../../src/Views/layout/footer.php'; ?>