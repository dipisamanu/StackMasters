<!-- FILE: views/bibliotecario/registra_restituzione.php -->
<?php
// I dati sono passati dal Controller tramite l'array $data
$message = $data['message'] ?? '';
$message_type = $data['message_type'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registra Restituzione - Biblioteca</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fb; }
        /* Modal Style */
        .modal {
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal.hidden {
            opacity: 0;
            visibility: hidden;
        }
        .modal.flex {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body class="p-4 sm:p-8">

<div class="max-w-2xl mx-auto bg-white p-10 rounded-xl shadow-2xl">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-undo-alt text-red-600 mr-3"></i>
        Registra Restituzione
    </h1>
    <p class="text-gray-600 mb-8">
        Scansiona la copia del libro per registrarne il rientro e valutare lo stato.
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
    <form method="POST" action="/bibliotecario/registra-restituzione" class="space-y-6">

        <!-- Campo Scansione Libro (Input Display) -->
        <div class="space-y-2">
            <label for="book_barcode_display" class="block text-sm font-medium text-gray-700">
                <i class="fas fa-book-return mr-2"></i> Scansiona Codice Copia Libro (ID Inventario)
            </label>
            <input type="text" id="book_barcode_display" name="book_barcode_display"
                   placeholder="Scansiona qui il codice Copia Libro..." required
                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm text-gray-900 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out">
        </div>

        <!-- Campi Nascosti (Inviati dal Modale) -->
        <input type="hidden" id="book_barcode_hidden" name="book_barcode" value="">
        <input type="hidden" id="condizione_hidden" name="condizione" value="BUONO">
        <input type="hidden" id="commento_danno_hidden" name="commento_danno" value="">

        <!-- Pulsante che scatena la scansione e il pop-up di valutazione -->
        <button type="button" id="trigger_evaluation_button"
                class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-md text-base font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150 ease-in-out">
            <i class="fas fa-check-double mr-2"></i> Completa Restituzione
        </button>

    </form>
</div>

<!-- MODALE DI VALUTAZIONE STATO LIBRO (Sub-issue 5.11) -->
<div id="evaluation-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4 text-gray-800">Valutazione Stato Libro</h2>
        <p class="mb-4 text-gray-600">Seleziona la condizione del volume restituito per il calcolo di eventuali penali:</p>

        <div class="space-y-3 mb-4">
            <label class="flex items-center space-x-3 bg-green-50 p-3 rounded-lg border border-green-200 cursor-pointer">
                <input type="radio" name="cond_radio" value="BUONO" checked class="form-radio text-green-600">
                <span class="font-medium text-green-800">BUONO / OTTIMO (Nessuna Penale)</span>
            </label>
            <label class="flex items-center space-x-3 bg-yellow-50 p-3 rounded-lg border border-yellow-200 cursor-pointer">
                <input type="radio" name="cond_radio" value="DANNEGGIATO" class="form-radio text-yellow-600">
                <span class="font-medium text-yellow-800">DANNEGGIATO (Potenziale Penale per Riparazione)</span>
            </label>
            <label class="flex items-center space-x-3 bg-red-50 p-3 rounded-lg border border-red-200 cursor-pointer">
                <input type="radio" name="cond_radio" value="PERSO" class="form-radio text-red-600">
                <span class="font-medium text-red-800">PERSO (Costo Sostituzione)</span>
            </label>
        </div>

        <textarea id="commento-danno" placeholder="Aggiungi commenti in caso di danno o perdita..."
                  class="w-full mt-2 p-3 border border-gray-300 rounded-lg focus:ring-red-500" rows="3"></textarea>

        <button type="button" id="confirm-evaluation"
                class="w-full mt-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
            Conferma Valutazione e Registra
        </button>
    </div>
</div>

<!-- Script per Focus e Modale -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const bookInputDisplay = document.getElementById('book_barcode_display');
        const bookInputHidden = document.getElementById('book_barcode_hidden');
        const triggerButton = document.getElementById('trigger_evaluation_button');
        const modal = document.getElementById('evaluation-modal');
        const confirmBtn = document.getElementById('confirm-evaluation');
        const hiddenCondizione = document.getElementById('condizione_hidden');
        const hiddenCommento = document.getElementById('commento_danno_hidden');
        const form = bookInputDisplay.closest('form');

        // Focus automatico sul campo di scansione
        bookInputDisplay.focus();

        // Funzione per mostrare la modale se l'input è valido
        function showModalIfValid() {
            if (bookInputDisplay.value.trim() === '') {
                bookInputDisplay.focus();
                return;
            }
            // Trasferisce il valore scansionato al campo hidden che verrà inviato
            bookInputHidden.value = bookInputDisplay.value.trim();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // 1. Scatena Modale al click del pulsante
        triggerButton.addEventListener('click', showModalIfValid);

        // 2. Scatena Modale dopo la scansione (invio con tasto Enter)
        bookInputDisplay.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                showModalIfValid();
            }
        });

        // 3. Logica di conferma Modale
        confirmBtn.addEventListener('click', () => {
            const selectedCond = document.querySelector('input[name="cond_radio"]:checked').value;
            const commento = document.getElementById('commento-danno').value;

            hiddenCondizione.value = selectedCond;
            hiddenCommento.value = commento;

            // Chiude modale e invia il form
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            form.submit();
        });
    });
</script>
</body>
</html>