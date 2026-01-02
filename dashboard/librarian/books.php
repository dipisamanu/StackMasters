<?php
/**
 * Catalogo Libri - View
 * File: dashboard/librarian/books.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/BookModel.php';

Session::requireRole('Bibliotecario');

$bookModel = new BookModel();
$search = $_GET['q'] ?? '';
$books = $bookModel->getAll($search);

// Messaggi Flash
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once '../../src/Views/layout/header.php';
?>

    <div class="container-fluid py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>📚 Catalogo Libri (Titoli)</h2>
            <button class="btn btn-primary" onclick="document.getElementById('bookModal').style.display='block'">
                + Nuovo Titolo
            </button>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form class="d-flex gap-2 mb-4">
            <input type="text" name="q" class="form-control" placeholder="Cerca titolo, autore o ISBN..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-secondary">Cerca</button>
        </form>

        <div class="card shadow">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                    <tr>
                        <th class="p-3">Titolo</th>
                        <th>Autore(i)</th>
                        <th>Editore</th>
                        <th>Anno</th>
                        <th>ISBN</th>
                        <th class="text-end p-3">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($books)): ?>
                        <tr><td colspan="6" class="text-center p-4">Nessun libro trovato.</td></tr>
                    <?php else: ?>
                        <?php foreach ($books as $b): ?>
                            <tr>
                                <td class="p-3 fw-bold"><?= htmlspecialchars($b['titolo']) ?></td>
                                <td><?= htmlspecialchars($b['autori_nomi'] ?? 'N/D') ?></td>
                                <td><?= htmlspecialchars($b['editore']) ?></td>
                                <td><?= $b['anno_uscita'] ? date('Y', strtotime($b['anno_uscita'])) : '-' ?></td>
                                <td style="font-family:monospace"><?= htmlspecialchars($b['isbn']) ?></td>
                                <td class="text-end p-3">
                                    <form action="process-book.php" method="POST" onsubmit="return confirm('Eliminare <?= addslashes($b['titolo']) ?>?');">
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

    <div id="bookModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1050;">
        <div style="background:white; max-width:600px; margin:30px auto; padding:25px; border-radius:10px; max-height:90vh; overflow-y:auto;">
            <div class="d-flex justify-content-between mb-3">
                <h3>Nuovo Titolo</h3>
                <button onclick="document.getElementById('bookModal').style.display='none'" class="btn-close" style="border:none; background:none; font-size:1.5rem;">&times;</button>
            </div>

            <form action="process-book.php" method="POST">
                <input type="hidden" name="action" value="create">

                <div class="mb-3">
                    <label>Titolo *</label>
                    <input type="text" name="titolo" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Autore Principale (Nome Cognome) *</label>
                    <input type="text" name="autore" class="form-control" placeholder="Es. George Orwell" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label>Editore</label>
                        <input type="text" name="editore" class="form-control">
                    </div>
                    <div class="col-6">
                        <label>Anno Uscita</label>
                        <input type="number" name="anno" class="form-control" placeholder="YYYY">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label>ISBN</label>
                        <input type="text" name="isbn" class="form-control">
                    </div>
                    <div class="col-6">
                        <label>Numero Pagine</label>
                        <input type="number" name="pagine" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Descrizione</label>
                    <textarea name="descrizione" class="form-control" rows="3"></textarea>
                </div>

                <div class="d-grid">
                    <button class="btn btn-primary">Salva nel Catalogo</button>
                </div>
            </form>
        </div>
    </div>

<?php require_once '../../src/Views/layout/footer.php'; ?>