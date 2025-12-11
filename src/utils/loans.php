<!-- FILE: views/bibliotecario/nuovo_prestito.php -->
<?php
$message = $data['message'] ?? '';
$message_type = $data['message_type'] ?? '';
$scanned_user = $data['scanned_user'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Prestito - Biblioteca</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="p-4 sm:p-8 bg-gray-100">

<div class="max-w-2xl mx-auto bg-white p-6 md:p-10 rounded-xl shadow-2xl">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-barcode text-blue-600 mr-3"></i>
        Registra Nuovo Prestito
    </h1>

    <p class="text-gray-600 mb-8">
        Scansiona i codici a barre della tessera utente e della copia del libro.
    </p>

    <?php if ($message): ?>
        <div id="alert-message"
             class="p-4 mb-8 rounded-lg font-medium text-sm
             <?= $message_type === 'success'
                     ? 'bg-green-100 text-green-700 border border-green-300'
                     : 'bg-red-100 text-red-700 border border-red-300' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/bibliotecario/registra-prestito" class="space-y-6">

        <!-- Campo Utente -->
        <div>
            <label for="user_barcode" class="block text-sm font-medium text-gray-700">
                <i class="fas fa-user-tag mr-2"></i> Scansiona Tessera Utente (Codice Fiscale)
            </label>
            <input type="text" id="user_barcode" name="user_barcode" required
                   placeholder="Codice Fiscale (16 caratteri)"
                   value="<?= htmlspecialchars($scanned_user) ?>"
                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Campo Libro -->
        <div>
            <label for="book_barcode" class="block text-sm font-medium text-gray-700">
                <i class="fas fa-book mr-2"></i> Scansiona Codice Copia Libro (EAN-13)
            </label>
            <input type="text" id="book_barcode" name="book_barcode" required
                   placeholder="Codice a barre EAN-13 (13 cifre)"
                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <button type="submit"
                class="w-full py-3 px-4 rounded-lg shadow text-white bg-blue-600 hover:bg-blue-700">
            <i class="fas fa-arrow-right mr-2"></i> Conferma Prestito
        </button>
    </form>
</div>

<script>
    // ============================
    // VALIDAZIONE BARCODE
    // ============================

    // CF = 16 caratteri alfanumerici
    function isUserBarcode(code) {
        return /^[A-Za-z0-9]{16}$/.test(code);
    }

    // VERIFICA CORRETTEZZA BAR CODE --> CI SRAA DA INDIVIDUARE TIPI DIVERSI TASK 7.2
    function isBookBarcode(code) {
        return /^\d{13}$/.test(code);
    }

    document.addEventListener('DOMContentLoaded', () => {

        const userInput = document.getElementById('user_barcode');
        const bookInput = document.getElementById('book_barcode');

        // Focus iniziale
        if (!userInput.value.trim()) {
            userInput.focus();
        } else {
            bookInput.focus();
        }

        // Controllo dopo scansione UTENTE
        userInput.addEventListener('change', function () {
            const code = this.value.trim();

            if (!isUserBarcode(code)) {
                alert("Errore: Il codice scansionato NON è un Codice Fiscale valido (16 caratteri alfanumerici).");
                this.value = "";
                this.focus();
                return;
            }

            bookInput.focus();
        });

        // Controllo dopo scansione LIBRO
        bookInput.addEventListener('change', function () {
            const userCode = userInput.value.trim();
            const bookCode = this.value.trim();

            // Codici identici
            if (bookCode === userCode) {
                alert("Errore: Il codice del libro NON può essere uguale al codice utente.");
                this.value = "";
                this.focus();
                return;
            }

            // Barcode libro valido?
            if (!isBookBarcode(bookCode)) {
                alert("Errore: Il codice scansionato NON è un EAN-13 valido (13 cifre).");
                this.value = "";
                this.focus();
                return;
            }

            // CF nel campo libro → errore
            if (isUserBarcode(bookCode)) {
                alert("Errore: Sembra che tu abbia scansionato un CODICE FISCALE nel campo del libro.");
                this.value = "";
                this.focus();
                return;
            }
        });

    });
</script>

</body>
</html>
