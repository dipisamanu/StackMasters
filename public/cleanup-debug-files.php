<?php

$filesToDelete = [
    'process-forgot-password-debug.php',
    'check-database.php',
    'test-reset-flow.php',
    'fix-token-column.sql',
    'cleanup-debug-files.php' // Cancella anche se stesso!
];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Debug Files</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #bf2121; }
        .file-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .file-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-left: 4px solid #ffc107;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-item.deleted {
            border-left-color: #28a745;
            opacity: 0.6;
        }
        .file-item.error {
            border-left-color: #dc3545;
        }
        .status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .status.not-found { background: #e7f3ff; color: #004085; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #bf2121;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 5px;
        }
        .btn:hover { background: #931b1b; }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover { background: #545b62; }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            color: #856404;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #28a745;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            color: #155724;
        }
        form { margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧹 Cleanup Debug Files</h1>

    <?php if (!isset($_POST['confirm'])): ?>

        <div class="warning">
            <strong>⚠️ ATTENZIONE!</strong><br>
            Questo script eliminerà i seguenti file di debug/test dal server:
        </div>

        <div class="file-list">
            <?php foreach ($filesToDelete as $file): ?>
                <div class="file-item">
                    <span>📄 <?= htmlspecialchars($file) ?></span>
                    <?php if (file_exists($file)): ?>
                        <span class="status" style="background: #fff3cd; color: #856404;">Da eliminare</span>
                    <?php else: ?>
                        <span class="status not-found">Non trovato</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="warning">
            <strong>📋 Prima di procedere:</strong><br>
            ✅ Hai testato il sistema di reset password?<br>
            ✅ Tutto funziona correttamente?<br>
            ✅ Hai fatto un backup dei file (opzionale)?<br>
        </div>

        <form method="POST">
            <button type="submit" name="confirm" value="yes" class="btn">
                🗑️ Sì, Elimina i File di Debug
            </button>
            <a href="login.php" class="btn btn-secondary">❌ Annulla</a>
        </form>

    <?php else: ?>

        <h2>📊 Risultato Eliminazione</h2>

        <div class="file-list">
            <?php
            $deletedCount = 0;
            $errorCount = 0;
            $notFoundCount = 0;

            foreach ($filesToDelete as $file):
                if (file_exists($file)):
                    if (@unlink($file)):
                        $deletedCount++;
                        ?>
                        <div class="file-item deleted">
                            <span>✅ <?= htmlspecialchars($file) ?></span>
                            <span class="status success">Eliminato</span>
                        </div>
                    <?php else:
                        $errorCount++;
                        ?>
                        <div class="file-item error">
                            <span>❌ <?= htmlspecialchars($file) ?></span>
                            <span class="status error">Errore</span>
                        </div>
                    <?php endif;
                else:
                    $notFoundCount++;
                    ?>
                    <div class="file-item">
                        <span>ℹ️ <?= htmlspecialchars($file) ?></span>
                        <span class="status not-found">Non trovato</span>
                    </div>
                <?php endif;
            endforeach;
            ?>
        </div>

        <?php if ($errorCount === 0): ?>
            <div class="success-box">
                <h3 style="margin-top: 0;">✅ Pulizia Completata!</h3>
                <p><strong>File eliminati:</strong> <?= $deletedCount ?></p>
                <p><strong>File non trovati:</strong> <?= $notFoundCount ?></p>
                <p style="margin-top: 15px;">
                    Il sistema è ora pronto per la produzione!<br>
                    Tutti i file di debug sono stati rimossi.
                </p>
            </div>
        <?php else: ?>
            <div class="warning">
                <strong>⚠️ Alcuni file non sono stati eliminati</strong><br>
                Verifica i permessi di scrittura sul server.<br>
                Puoi eliminarli manualmente via FTP/SSH.
            </div>
        <?php endif; ?>

        <a href="login.php" class="btn">← Vai al Login</a>

        <?php if ($deletedCount > 0): ?>
            <div class="warning" style="margin-top: 30px;">
                <strong>🗑️ NOTA FINALE:</strong><br>
                Questo script (cleanup-debug-files.php) <?= in_array('cleanup-debug-files.php', array_filter($filesToDelete, fn($f) => file_exists($f))) ? 'sarà eliminato' : 'è stato eliminato' ?> automaticamente.<br>
                Se ancora esiste, eliminalo manualmente.
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>