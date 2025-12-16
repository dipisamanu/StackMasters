<!-- FILE: views/bibliotecario/nuovo_prestito.php -->
<?php
// I dati sono passati dal Controller tramite l'array $data
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

<!-- Script per Focus Automatico -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const userBarcodeInput = document.getElementById('user_barcode');
        const bookBarcodeInput = document.getElementById('book_barcode');

        // Focus automatico sul campo utente al caricamento se vuoto
        if (userBarcodeInput && !userBarcodeInput.value) {
            userBarcodeInput.focus();
        } else if (bookBarcodeInput) {
            // Se l'utente è già scansionato (dopo un errore, ad esempio), passa al libro
            bookBarcodeInput.focus();
        }

        // Sposta il focus da Utente a Libro dopo il primo input (simula la scansione)
        userBarcodeInput.addEventListener('change', function() {
            if (this.value.length > 0) {
                bookBarcodeInput.focus();
            }
        });
    });
</script>
</body>
</html>