<?php
/**
 * Interfaccia Prestito Rapido (RFID/Barcode Multiplo)
 * File: dashboard/librarian/new_loan.php
 * Invia i dati a: registra-prestito.php per la generazione del PDF e salvataggio.
 */
session_start();

/* Verifica login (opzionale, basato sulla tua sessione)
if (!isset($_SESSION['logged_in'])) {
    header('Location: /StackMasters/public/login.php');
    exit;
}
*/


$nomeCompleto = ($_SESSION['nome'] ?? 'Bibliotecario') . ' ' . ($_SESSION['cognome'] ?? '');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Prestito Rapido - ITIS Rossi</title>

    <!-- Tailwind & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .itis-red { color: #bf2121; }
        .itis-bg-red { background-color: #bf2121; }

        /* Animazione per l'aggiunta dei libri alla lista */
        .book-entry { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>
</head>
<body class="p-4 md:p-8">

<div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border-l-8 border-red-600 p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="bg-red-50 p-4 rounded-xl">
                <i class="fas fa-barcode itis-red text-3xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Registrazione Prestito</h1>
                <p class="text-slate-500 text-sm">Modalità Scansione RFID e Lettura Multipla attiva</p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-xs font-bold text-slate-400 uppercase">Operatore</p>
            <p class="font-bold text-slate-700"><?= htmlspecialchars($nomeCompleto) ?></p>
            <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Form Principale -->
    <form id="loan-form" action="registra-prestito.php" method="POST">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Colonna Sinistra: Identificazione -->
            <div class="lg:col-span-1 space-y-6">

                <!-- 1. UTENTE -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-3">1. Identifica Utente (RFID / CF)</label>
                    <div class="relative">
                        <i class="fas fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" id="user_barcode" name="user_barcode"
                               class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-lg font-mono uppercase focus:border-red-500 focus:ring-0 outline-none transition-all"
                               placeholder="Scansiona..." required autofocus>
                    </div>

                    <!-- Feedback Utente (AJAX) -->
                    <div id="user-info-display" class="mt-4 empty:hidden">
                        <!-- Popolato da scanner.js -->
                    </div>
                </div>

                <!-- 2. SCANSIONE LIBRI -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-3">2. Scansiona Libri (Uno alla volta)</label>
                    <div class="relative">
                        <i class="fas fa-book absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" id="book_barcode"
                               class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-lg font-mono focus:border-red-500 outline-none transition-all"
                               placeholder="Scansiona copia...">
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2 italic text-center">Puoi scansionare più libri consecutivamente</p>
                </div>
            </div>

            <!-- Colonna Destra: Riepilogo Gruppo -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col h-full overflow-hidden">
                    <div class="p-5 border-b bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-black text-slate-700 uppercase text-xs tracking-widest">
                            <i class="fas fa-list-ul mr-2 text-red-600"></i>Libri pronti per l'uscita
                        </h3>
                        <span id="books-count" class="bg-slate-800 text-white text-[10px] px-2.5 py-1 rounded-full font-black">0</span>
                    </div>

                    <!-- Lista dinamica dei libri -->
                    <div id="scanned-books-list" class="flex-1 min-h-[350px] max-h-[500px] overflow-y-auto p-2">
                        <!-- I libri scansionati appariranno qui tramite scanner.js -->
                        <div id="empty-list-msg" class="h-full flex flex-col items-center justify-center text-slate-300 py-20">
                            <i class="fas fa-barcode text-5xl mb-4 opacity-20"></i>
                            <p class="font-bold uppercase text-xs tracking-widest">Nessun libro scansionato</p>
                        </div>
                    </div>

                    <!-- Bottone di Conferma Finale -->
                    <div class="p-6 bg-slate-50 border-t">
                        <button type="submit" id="submit-btn" disabled
                                class="w-full itis-bg-red hover:bg-red-700 disabled:bg-slate-300 text-white p-5 rounded-xl font-black text-lg shadow-xl shadow-red-200 transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-check-double"></i> CONFERMA PRESTITO DI GRUPPO
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Messaggi di Stato (Toasts) -->
<div id="scan-status" class="fixed bottom-8 right-8 z-50 pointer-events-none opacity-0 transition-opacity duration-300"></div>

<!-- Caricamento logica scanner -->
<script src="/public/assets/js/scanner.js"></script>

<script>
    // Logica aggiuntiva per la UI
    const list = document.getElementById('scanned-books-list');
    const emptyMsg = document.getElementById('empty-list-msg');
    const submitBtn = document.getElementById('submit-btn');
    const countBadge = document.getElementById('books-count');

    // Monitora la lista per attivare/disattivare il bottone e contatore
    const observer = new MutationObserver(() => {
        const count = list.querySelectorAll('.book-entry').length;
        countBadge.innerText = count;

        if (count > 0) {
            emptyMsg.style.display = 'none';
            submitBtn.disabled = false;
        } else {
            emptyMsg.style.display = 'flex';
            submitBtn.disabled = true;
        }
    });

    observer.observe(list, { childList: true });
</script>

<?php
require_once __DIR__ . '/../../src/Views/layout/footer.php';
?>
</body>
</html>

