<?php
require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';

Session::requireRole('Bibliotecario');

$bookModel = new BookModel();
$search = $_GET['q'] ?? '';
$books = [];
$error = '';

// Tenta di recuperare i libri, se fallisce mostra l'errore SQL esatto
try {
    $books = $bookModel->getAll($search);
} catch (Exception $e) {
    $error = "ERRORE CRITICO DATABASE: " . $e->getMessage();
}

// Recupera messaggi flash normali
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

require_once '../../src/Views/layout/header.php';
?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>📚 Catalogo Libri</h2>
            <button class="btn btn-primary" onclick="openModal('create')">
                <i class="fas fa-plus"></i> Nuovo Titolo
            </button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Attenzione:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cerca titolo o autore..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-secondary">Cerca</button>
                    <?php if($search): ?><a href="books.php" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Titolo</th>
                        <th>Autore</th>
                        <th>Editore</th>
                        <th>Anno</th>
                        <th>ISBN</th>
                        <th class="text-center">Copie</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($books)): ?>
                        <tr><td colspan="7" class="text-center p-4">Nessun libro trovato.</td></tr>
                    <?php else: ?>
                        <?php foreach ($books as $b): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($b['titolo']) ?></td>
                                <td><?= htmlspecialchars($b['autori_nomi'] ?? 'N/D') ?></td>
                                <td><?= htmlspecialchars($b['editore']) ?></td>
                                <td><?= $b['anno_uscita'] ? date('Y', strtotime($b['anno_uscita'])) : '-' ?></td>
                                <td class="small font-monospace"><?= htmlspecialchars($b['isbn']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $b['copie_totali'] ?></span>
                                    <span class="badge bg-success"><?= $b['copie_disponibili'] ?></span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-warning" onclick='openModal("edit", <?= json_encode($b) ?>)'><i class="fas fa-edit"></i></button>
                                    <form action="process-book.php" method="POST" class="d-inline" onsubmit="return confirm('Eliminare?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_libro" value="<?= $b['id_libro'] ?>">
                                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
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

    <div class="modal fade" id="bookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuovo Libro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process-book.php" method="POST" id="bookForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id_libro" id="bookId">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Titolo *</label>
                                <input type="text" name="titolo" id="titolo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Autore (Nome Cognome) *</label>
                                <input type="text" name="autore" id="autore" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Editore</label>
                                <input type="text" name="editore" id="editore" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Anno</label>
                                <input type="number" name="anno" id="anno" class="form-control" placeholder="YYYY">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Pagine</label>
                                <input type="number" name="pagine" id="pagine" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ISBN</label>
                                <input type="text" name="isbn" id="isbn" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrizione</label>
                                <textarea name="descrizione" id="descrizione" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let bookModal;
        document.addEventListener('DOMContentLoaded', function() {
            bookModal = new bootstrap.Modal(document.getElementById('bookModal'));
        });

        function openModal(mode, data = null) {
            const form = document.getElementById('bookForm');

            if (mode === 'edit' && data) {
                document.getElementById('modalTitle').innerText = 'Modifica Libro';
                document.getElementById('formAction').value = 'update';
                document.getElementById('bookId').value = data.id_libro;

                document.getElementById('titolo').value = data.titolo;
                document.getElementById('autore').value = data.autori_nomi || '';
                document.getElementById('editore').value = data.editore;
                document.getElementById('anno').value = data.anno_uscita ? data.anno_uscita.substring(0, 4) : '';
                document.getElementById('isbn').value = data.isbn;
                document.getElementById('pagine').value = data.numero_pagine;
                document.getElementById('descrizione').value = data.descrizione;
            } else {
                document.getElementById('modalTitle').innerText = 'Nuovo Libro';
                document.getElementById('formAction').value = 'create';
                form.reset();
            }
            bookModal.show();
        }
    </script>

<?php require_once '../../src/Views/layout/footer.php'; ?>