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

    <!-- IMPORT FONT INTER -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb;
        }
    </style>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="p-4 sm:p-8 bg-slate-50">

<div class="max-w-2xl mx-auto bg-white p-6 md:p-10 rounded-xl shadow-2xl border border-gray-100">

    <!-- HEADER -->
    <h1 class="text-3xl font-bold text-gray-800 mb-2 flex items-center">
        <i class="fas fa-exchange-alt text-blue-600 mr-3"></i>
        Nuovo Prestito
    </h1>
    <p class="text-gray-500 mb-8 border-b pb-4">
        Registrazione rapida tramite scanner barcode
    </p>

    <!-- MESSAGGI SERVER -->
    <?php if ($message): ?>
        <div class="p-4 mb-6 rounded-lg font-medium text-sm border
            <?php echo $message_type === 'success'
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-red-50 text-red-700 border-red-200'; ?>">
            <i class="fas <?php echo $message_type === 'success'
                    ? 'fa-check-circle'
                    : 'fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <form id="loan-form"
          method="POST"
          action="/dashboard/librarian/registra-prestito.php"
          class="space-y-6">

        <!-- CODICE FISCALE -->
        <div class="space-y-2">
            <label for="user_barcode" class="block text-sm font-semibold text-gray-700">
                <i class="fas fa-id-card mr-2"></i>Codice Fiscale Utente
            </label>

            <div class="relative">
                <i class="fas fa-barcode barcode-icon absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                <input
                        type="text"
                        id="user_barcode"
                        name="user_barcode"
                        autocomplete="off"
                        required
                        onchange="checkFieldLogic(this, 'user')"
                        placeholder="Scansiona la tessera sanitaria..."
                        value="<?php echo htmlspecialchars($scanned_user); ?>"
                        class="mt-1 block w-full px-4 py-4 pl-12
                           border border-gray-300 rounded-lg shadow-sm
                           focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                           transition-all uppercase font-mono text-lg
                           placeholder:font-sans placeholder:italic placeholder:text-gray-400"
                >
            </div>
        </div>

        <!-- CODICE LIBRO -->
        <div class="space-y-2">
            <label for="book_barcode" class="block text-sm font-semibold text-gray-700">
                <i class="fas fa-book mr-2"></i>Codice Libro (EAN13 / Inventario)
            </label>

            <div class="relative">
                <i class="fas fa-barcode barcode-icon absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                <input
                        type="text"
                        id="book_barcode"
                        name="book_barcode"
                        autocomplete="off"
                        required
                        onchange="checkFieldLogic(this, 'book')"
                        placeholder="Scansiona il codice del libro..."
                        class="mt-1 block w-full px-4 py-4 pl-12
                           border border-gray-300 rounded-lg shadow-sm
                           focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                           transition-all uppercase font-mono text-lg
                           placeholder:font-sans placeholder:italic placeholder:text-gray-400"
                >
            </div>
        </div>

        <!-- SUBMIT -->
        <button type="submit"
                class="w-full flex justify-center items-center py-4 px-4
                       border border-transparent rounded-lg shadow-lg
                       text-lg font-bold text-white bg-blue-600
                       hover:bg-blue-700 focus:outline-none
                       focus:ring-4 focus:ring-blue-200 transition-all">
            <i class="fas fa-check mr-2"></i>
            REGISTRA PRESTITO
        </button>
    </form>
</div>

<!-- JS SCANNER -->
<script src="/public/assets/js/scanner.js"></script>

<!-- JS UI -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const userInp = document.getElementById('user_barcode');
        const bookInp = document.getElementById('book_barcode');

        // Focus iniziale
        if (!userInp.value) {
            userInp.focus();
        } else {
            bookInp.focus();
        }

        // Rimuove evidenziazione se si modifica
        [userInp, bookInp].forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('border-green-500', 'bg-green-50');
                const icon = input.parentElement.querySelector('.barcode-icon');
                icon?.classList.remove('text-green-500', 'text-red-500');
                icon?.classList.add('text-gray-400');
            });
        });
    });
</script>

</body>
</html>
