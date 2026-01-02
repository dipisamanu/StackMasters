<?php
/**
 * Footer Comune - Chiude la pagina e carica gli script
 * File: src/Views/layout/footer.php
 */
?>

</main>
<footer class="bg-dark text-white text-center py-4 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-12">
                <p class="mb-0 small">
                    &copy; <?= date('Y') ?> <strong>Biblioteca ITIS Rossi</strong>.
                    Progetto StackMasters. Tutti i diritti riservati.
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if (defined('BASE_URL')): ?>
    <script src="<?= BASE_URL ?>/assets/js/scanner.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                let alerts = document.querySelectorAll('.alert-success, .alert-danger');
                alerts.forEach(function(alert) {
                    // Bootstrap 5 dismiss
                    let bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000); // 5000ms = 5 secondi
        });
    </script>
<?php endif; ?>

</body>
</html>