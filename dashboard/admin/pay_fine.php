<?php
/**
 * Pagamento Multe - Admin
 * File: dashboard/admin/pay_fine.php
 */

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';

Session::requireRole('Admin');

$db = Database::getInstance()->getConnection();
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId <= 0) {
    header('Location: fines.php');
    exit;
}

// Recupera info utente
try {
    $stmt = $db->prepare("SELECT nome, cognome, email FROM utenti WHERE id_utente = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: fines.php');
        exit;
    }
} catch (Exception $e) {
    die("Errore database: " . $e->getMessage());
}

// Recupera multe pendenti dell'utente
try {
    $stmt = $db->prepare("
        SELECT m.id_multa, m.importo, m.causa, m.commento, m.data_creazione
        FROM multe m
        WHERE m.id_utente = :id AND m.data_pagamento IS NULL
        ORDER BY m.data_creazione DESC
    ");
    $stmt->execute([':id' => $userId]);
    $fines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Errore database: " . $e->getMessage());
}

// Gestione POST per il pagamento
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fineId = isset($_POST['fine_id']) ? (int)$_POST['fine_id'] : 0;
    $amountPaid = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $action = $_POST['action'] ?? '';

    if ($fineId > 0 && $amountPaid > 0) {
        try {
            $db->beginTransaction();

            // Recupera importo attuale della multa
            $stmt = $db->prepare("SELECT importo FROM multe WHERE id_multa = :id AND data_pagamento IS NULL FOR UPDATE");
            $stmt->execute([':id' => $fineId]);
            $currentFineAmount = $stmt->fetchColumn();

            if ($currentFineAmount !== false) {
                if ($action === 'pay_full' || $amountPaid >= $currentFineAmount) {
                    // Pagamento totale o superiore -> Segna come pagata
                    $stmt = $db->prepare("UPDATE multe SET data_pagamento = NOW() WHERE id_multa = :id");
                    $stmt->execute([':id' => $fineId]);
                    $message = "Multa saldata con successo.";
                } elseif ($action === 'pay_partial') {
                    // Pagamento parziale -> Riduci importo
                    $newAmount = $currentFineAmount - $amountPaid;
                    $stmt = $db->prepare("UPDATE multe SET importo = :new_amount WHERE id_multa = :id");
                    $stmt->execute([':new_amount' => $newAmount, ':id' => $fineId]);
                    $message = "Pagamento parziale registrato. Nuovo importo: € " . number_format($newAmount, 2);
                }
                $db->commit();
                
                // Ricarica la pagina per aggiornare i dati
                header("Location: pay_fine.php?user_id=$userId&msg=" . urlencode($message));
                exit;
            } else {
                $error = "Multa non trovata o già pagata.";
                $db->rollBack();
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Errore durante la registrazione del pagamento: " . $e->getMessage();
        }
    } else {
        $error = "Importo non valido.";
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

require_once '../../src/Views/layout/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark">Gestione Pagamenti</h1>
            <p class="text-muted mb-0">Utente: <strong><?= htmlspecialchars($user['cognome'] . ' ' . $user['nome']) ?></strong> (<?= htmlspecialchars($user['email']) ?>)</p>
        </div>
        <a href="fines.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Torna alla Lista</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark">Multe Pendenti</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Data</th>
                            <th class="py-3">Causa / Dettagli</th>
                            <th class="py-3 text-end">Importo</th>
                            <th class="py-3 text-end pe-4" style="width: 350px;">Registra Pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fines)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">Nessuna multa pendente per questo utente.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fines as $fine): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= date('d/m/Y', strtotime($fine['data_creazione'])) ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($fine['causa'] ?? 'Multa generica') ?></div>
                                        <?php if (!empty($fine['commento'])): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($fine['commento']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-danger">€ <?= number_format($fine['importo'], 2) ?></td>
                                    <td class="pe-4">
                                        <form method="POST" class="d-flex gap-2 justify-content-end">
                                            <input type="hidden" name="fine_id" value="<?= $fine['id_multa'] ?>">
                                            <div class="input-group input-group-sm" style="max-width: 150px;">
                                                <span class="input-group-text">€</span>
                                                <input type="number" step="0.01" min="0.01" max="<?= $fine['importo'] ?>" class="form-control" name="amount" placeholder="Importo" required>
                                            </div>
                                            <button type="submit" name="action" value="pay_partial" class="btn btn-sm btn-warning text-white" title="Pagamento Parziale">
                                                <i class="fas fa-coins"></i>
                                            </button>
                                            <button type="submit" name="action" value="pay_full" class="btn btn-sm btn-success" title="Saldo Totale" onclick="this.form.amount.value='<?= $fine['importo'] ?>'">
                                                <i class="fas fa-check"></i>
                                            </button>
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
</div>

<?php require_once '../../src/Views/layout/footer.php'; ?>