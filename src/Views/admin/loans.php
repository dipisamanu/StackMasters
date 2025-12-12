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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fb; }
    </style>
</head>
<body class="p-4 sm:p-8">

<div class="max-w-2xl mx-auto bg-white p-6 md:p-10 rounded-xl shadow-2xl">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-barcode text-blue-600 mr-3"></i>
        Registra Nuovo Prestito
    </h1>
    <p class="text-gray-600 mb-8">
        Scansiona i codici a barre della tessera utente e della copia del libro.
    </p>

    <!-- AREA MESSAGGI -->
    <?php if ($message): ?>
        <div id="alert-message" class="p-4 mb-8 rounded-lg font-medium text-sm
                <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>"
             role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- FORM PRINCIPALE -->
    <form method="POST" action="/bibliotecario/registra-prestito" class="space-y-6">

        <!-- Campo Scansione Utente -->
        <div class="space-y-2">
            <label for="user_barcode" class="block text-sm font-medium text-gray-700">
                <i class="fas fa-user-tag mr-2"></i> Scansiona Tessera Utente (ID)
            </label>
            <input type="text" id="user_barcode" name="user_barcode"
                   placeholder="Scansiona qui il codice Utente..." required
                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                   value="<?php echo htmlspecialchars($scanned_user); ?>">
        </div>

        <!-- Campo Scansione Libro -->
        <div class="space-y-2">
            <label for="book_barcode" class="block text-sm font-medium text-gray-700">
                <i class="fas fa-book-open-reader mr-2"></i> Scansiona Codice Copia Libro (ID Inventario)
            </label>
            <input type="text" id="book_barcode" name="book_barcode"
                   placeholder="Scansiona qui il codice Copia Libro..." required
                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out">
        </div>

        <!-- Pulsante di Registrazione -->
        <button type="submit"
                class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-md text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
            <i class="fas fa-arrow-right mr-2"></i> Conferma Prestito
        </button>
    </form>
</div>

<!-- Script Validazioni + Focus -->
<script>
    // Regole: modifica se servono
    function isUserBarcode(code) {
        return /^\d{6,12}$/.test(code);  // Tessera utente = numerico 6-12 cifre
    }

    function isBookBarcode(code) {
        return /^[A-Za-z0-9]{4,30}$/.test(code); // Libro = più generico alfanumerico
    }

    document.addEventListener('DOMContentLoaded', () => {
        const userBarcodeInput = document.getElementById('user_barcode');
        const bookBarcodeInput = document.getElementById('book_barcode');

        // Focus iniziale
        if (!userBarcodeInput.value) {
            userBarcodeInput.focus();
        } else {
            bookBarcodeInput.focus();
        }

        // Quando scansiono UTENTE
        userBarcodeInput.addEventListener('change', function() {
            const code = this.value.trim();

            if (!isUserBarcode(code)) {
                alert("Errore: nel campo UTENTE è stato scansionato un codice libro.");
                this.value = "";
                this.focus();
                return;
            }

            bookBarcodeInput.focus();
        });

        // Quando scansiono LIBRO
        bookBarcodeInput.addEventListener('change', function() {
            const bookCode = this.value.trim();
            const userCode = userBarcodeInput.value.trim();

            // Non possono essere uguali
            if (bookCode === userCode) {
                alert("Errore: il codice del libro non può essere uguale al codice dell'utente.");
                this.value = "";
                this.focus();
                return;
            }

            // Non può essere un codice utente
            if (isUserBarcode(bookCode)) {
                alert("Errore: nel campo LIBRO è stato scansionato un codice utente.");
                this.value = "";
                this.focus();
                return;
            }
        });
    });
</script>

</body>
</html>
