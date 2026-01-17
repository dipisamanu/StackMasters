<?php
/**
 * Elenco di tutte le copie nell'inventario
 * File: dashboard/librarian/all-copies.php
 */

require_once '../../src/config/session.php';
require_once '../../src/Models/InventoryModel.php';

Session::requireRole('Bibliotecario');

$invModel = new InventoryModel();
$copies = $invModel->getAllCopies();

require_once '../../src/Views/layout/header.php';
?>

<div class="container-fluid py-4">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h6 class="text-uppercase text-muted small mb-1">Inventario</h6>
            <h2 class="fw-bold text-danger mb-0">Tutte le Copie</h2>
        </div>
    </div>

    <div class="card shadow border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                <tr>
                    <th class="ps-4">ID Copia</th>
                    <th>Titolo del Libro</th>
                    <th>Stato</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($copies)): ?>
                    <tr>
                        <td colspan="3" class="text-center p-5 text-muted">Nessuna copia trovata.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($copies as $copy): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($copy['id_inventario']) ?></td>
                            <td><?= htmlspecialchars($copy['titolo']) ?></td>
                            <td>
                                <?php
                                $bg = 'secondary';
                                if ($copy['stato'] == 'DISPONIBILE') $bg = 'success';
                                if ($copy['stato'] == 'IN_PRESTITO') $bg = 'warning';
                                if ($copy['stato'] == 'SMARRITO') $bg = 'danger';
                                if ($copy['stato'] == 'FUORI_CATALOGO') $bg = 'dark';
                                ?>
                                <span class="badge bg-<?= $bg ?>"><?= str_replace('_', ' ', htmlspecialchars($copy['stato'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../src/Views/layout/footer.php'; ?>
