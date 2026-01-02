<?php
/**
 * Gestione Libri - Interfaccia Avanzata
 * File: dashboard/librarian/books.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';

Session::requireRole('Bibliotecario');

$bookModel = new BookModel();

// Gestione Input
$search = $_GET['q'] ?? '';
$filterAvailable = isset($_GET['available']) && $_GET['available'] === 'on';

// Preparazione Filtri per il Model
$filters = [];
if ($filterAvailable) {
    $filters['solo_disponibili'] = true;
}

// Recupero Dati
$books = [];
$error = '';
try {
    $books = $bookModel->getAll($search, $filters);
} catch (Exception $e) {
    $error = "Errore Database: " . $e->getMessage();
}

// Messaggi Flash
$success = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../src/Views/layout/header.php';
?>

    <div class="container-fluid py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-danger"><i class="fas fa-book-open me-2"></i>Catalogo Libri</h2>
                <p class="text-muted mb-0">Gestisci i titoli, gli autori e verifica la disponibilità.</p>
            </div>
            <button class="btn btn-danger shadow-sm" onclick="openModal('create')">
                <i class="fas fa-plus me-2"></i>Nuovo Titolo
            </button>
        </div>

        <?php if ($error || $flashError): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error . $flashError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3 bg-light rounded">
                <form method="GET" action="books.php" class="row g-3 align-items-center">

                    <div class="col-md-7 col-lg-8">
                        <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                            <input type="text" name="q" class="form-control border-start-0 ps-0"
                                   placeholder="Cerca per titolo, autore, ISBN, editore..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="col-md-3 col-lg-2">
                        <div class="form-check form-switch custom-switch">
                            <input class="form-check-input" type="checkbox" id="availableCheck" name="available"
                                    <?= $filterAvailable ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label class="form-check-label fw-semibold" for="availableCheck">Solo Disponibili</label>
                        </div>
                    </div>

                    <div class="col-md-2 col-lg-2 text-end">
                        <?php if(!empty($search) || $filterAvailable): ?>
                            <a href="books.php" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-dark btn-sm w-100">Cerca</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow border-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-secondary text-white small text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">Titolo & Autore</th>
                        <th>Dettagli</th>
                        <th>ISBN</th>
                        <th class="text-center">Stato</th>
                        <th class="text-end pe-4">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($books)): ?>
                        <tr>
                            <td colspan="5" class="text-center p-5 text-muted">
                                <div class="mb-3"><i class="fas fa-folder-open fa-3x"></i></div>
                                <h5>Nessun libro trovato</h5>
                                <small>Prova a modificare i filtri di ricerca.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($books as $b): ?>
                            <tr class="border-bottom">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded p-2 me-3 d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
                                            <i class="fas fa-book text-danger fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($b['titolo']) ?></div>
                                            <small class="text-muted"><i class="fas fa-pen-nib me-1"></i><?= htmlspecialchars($b['autori_nomi'] ?? 'Sconosciuto') ?></small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <small class="d-block text-muted">Editore: <span class="text-dark fw-semibold"><?= htmlspecialchars($b['editore'] ?? '-') ?></span></small>
                                    <small class="d-block text-muted">Anno: <span class="text-dark fw-semibold"><?= $b['anno_uscita'] ? date('Y', strtotime($b['anno_uscita'])) : '-' ?></span></small>
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?= htmlspecialchars($b['isbn'] ?? '-') ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <?php
                                    $tot = $b['copie_totali'];
                                    $disp = $b['copie_disponibili'];
                                    $percent = $tot > 0 ? ($disp / $tot) * 100 : 0;
                                    $color = $percent > 0 ? 'success' : 'secondary';
                                    if ($disp == 0 && $tot > 0) $color = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $color ?> rounded-pill px-3">
                                        <?= $disp ?> / <?= $tot ?> Disp.
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <button class="btn btn-light btn-sm text-primary" title="Modifica"
                                                onclick='openModal("edit", <?= json_encode($b) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="process-book.php" method="POST" class="d-inline" onsubmit="return confirm('Sei sicuro di voler eliminare questo libro?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_libro" value="<?= $b['id_libro'] ?>">
                                            <button class="btn btn-light btn-sm text-danger" title="Elimina">
                                                <i class="fas fa-trash-alt"></i>
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

    <div class="modal fade" id="bookModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle">Gestione Libro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="process-book.php" method="POST" id="bookForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id_libro" id="bookId">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Titolo *</label>
                                <input type="text" name="titolo" id="titolo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Autore (Nome Cognome) *</label>
                                <input type="text" name="autore" id="autore" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Editore</label>
                                <input type="text" name="editore" id="editore" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Anno</label>
                                <input type="number" name="anno" id="anno" class="form-control" placeholder="YYYY">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Pagine</label>
                                <input type="number" name="pagine" id="pagine" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ISBN</label>
                                <input type="text" name="isbn" id="isbn" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Descrizione</label>
                                <textarea name="descrizione" id="descrizione" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-danger px-4">Salva Libro</button>
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