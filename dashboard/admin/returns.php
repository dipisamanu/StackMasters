<!-- FILE: views/bibliotecario/registra_restituzione.php -->
<?php
$message = $data['message'] ?? '';
$message_type = $data['message_type'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registra Restituzione</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fb; }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
        .modal.hidden { opacity: 0; visibility: hidden; }
        .modal.flex { opacity: 1; visibility: visible; }
    </style>
</head>
<body class="p-4 sm:p-8">

<div class="max-w-2xl mx-auto bg-white p-10 rounded-xl shadow-2xl">

    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-undo-alt text-red-600 mr-3"></i>
        Registra Restituzione
    </h1>

    <?php if ($message): ?>
        <div class="p-4 mb-8 rounded-lg font-medium text-sm
            <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300'
                : 'bg-red-100 text-red-700 border border-red-300'; ?>">
            <?= $message; ?>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <form method="POST" action="/bibliotecario/registra-restituzione" class="space-y-6">

        <!-- Campo libro -->
        <div class="space-y-2">
            <label for="book_barcode_display" class="block text-sm font-medium text-gray-700">
                <i class="fas fa-book mr-2"></i> Codice copia libro
            </label>
            <input type="text" id="book_barcode_display" name="book_barcode_display"
                   class="w-full px-4 py-3 border rounded-lg shadow-sm"
                   placeholder="Scansiona il libro..." required>
        </div>

        <!-- hidden -->
        <input type="hidden" id="book_barcode_hidden" name="book_barcode">
        <input type="hidden" id="condizione_hidden" name="condizione" value="BUONO">
        <input type="hidden" id="commento_danno_hidden" name="commento_danno">

        <!-- Bottone -->
        <button type="button" id="trigger_evaluation_button"
                class="w-full py-3 px-4 rounded-lg text-white bg-red-600 hover:bg-red-700">
            <i class="fas fa-check mr-2"></i> Completa Restituzione
        </button>
    </form>
</div>


<!-- MODALE -->
<div id="evaluation-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">

        <h2 class="text-xl font-bold mb-4">Valutazione Libro</h2>

        <div class="space-y-3 mb-4">
            <label class="flex items-center space-x-3 bg-green-50 p-3 rounded-lg border border-green-200 cursor-pointer">
                <input type="radio" name="cond_radio" value="BUONO" checked>
                <span>BUONO / OTTIMO</span>
            </label>
            <label class="flex items-center space-x-3 bg-yellow-50 p-3 rounded-lg border border-yellow-200 cursor-pointer">
                <input type="radio" name="cond_radio" value="DANNEGGIATO">
                <span>DANNEGGIATO</span>
            </label>
            <label class="flex items-center space-x-3 bg-red-50 p-3 rounded-lg border border-red-200 cursor-pointer">
                <input type="radio" name="cond_radio" value="PERSO">
                <span>PERSO</span>
            </label>
        </div>

        <textarea id="commento-danno" class="w-full p-3 border rounded-lg" rows="3"
                  placeholder="Commento (facoltativo)..."></textarea>

        <button type="button" id="confirm-evaluation"
                class="w-full mt-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Conferma</button>

    </div>
</div>

<!-- SCRIPT LOGICA -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const bookDisp = document.getElementById("book_barcode_display");
        const hiddenBarcode = document.getElementById("book_barcode_hidden");
        const trigger = document.getElementById("trigger_evaluation_button");
        const modal = document.getElementById("evaluation-modal");
        const confirm = document.getElementById("confirm-evaluation");
        const form = bookDisp.closest("form");

        bookDisp.focus();

        function openModal() {
            if (!bookDisp.value.trim()) return;
            hiddenBarcode.value = bookDisp.value.trim();
            modal.classList.remove("hidden");
            modal.classList.add("flex");
        }

        trigger.addEventListener("click", openModal);

        bookDisp.addEventListener("keydown", e => {
            if (e.key === "Enter") {
                e.preventDefault();
                openModal();
            }
        });

        confirm.addEventListener("click", () => {
            document.getElementById("condizione_hidden").value =
                document.querySelector('input[name="cond_radio"]:checked').value;

            document.getElementById("commento_danno_hidden").value =
                document.getElementById("commento-danno").value;

            modal.classList.add("hidden");
            modal.classList.remove("flex");

            form.submit();
        });
    });
</script>

<!-- SCANNER -->
<script src="/assets/js/scanner.js"></script>

</body>
</html>
