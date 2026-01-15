<?php
/**
 * Interfaccia Prestito Rapido (Solo Codice Fiscale)
 * File: dashboard/librarian/new-loan.php
 * Invia i dati a: registra-prestito.php per la generazione del PDF e salvataggio.
 */
require_once __DIR__ . '/../../src/config/session.php';

// Verifica autorizzazione (Librarian/Admin)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit;
}

$nomeCompleto = ($_SESSION['nome_completo'] ?? 'Operatore');

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione Prestito - StackMasters</title>

    <!-- Tailwind & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }

        /* Tema Indigo per Prestito */
        .theme-indigo { color: #4f46e5; }
        .bg-theme-indigo { background-color: #4f46e5; }

        .book-entry { animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        input::placeholder { color: #cbd5e1; font-weight: 400; }
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
<body class="p-4 md:p-8">

<div class="max-w-6xl mx-auto">
    <!-- Header Istituzionale -->
    <div class="bg-white rounded-2xl shadow-sm border-l-8 border-indigo-600 p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="bg-indigo-50 p-4 rounded-xl text-indigo-600 text-3xl">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight leading-none mb-1">Registrazione Prestito</h1>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden md:block">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Operatore</p>
                <p class="font-bold text-slate-700 leading-none"><?= htmlspecialchars($nomeCompleto) ?></p>
            </div>
            <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Form Principale -->
    <form id="loan-form" action="registra-prestito.php" method="POST">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- COLONNA SINISTRA: INPUT -->
            <div class="lg:col-span-1 space-y-6">

                <!-- 1. IDENTIFICA UTENTE -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 text-center">1. Identificazione Utente (CF)</label>
                    <div class="relative">
                        <i class="fas fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" id="user_barcode" name="user_barcode"
                               class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-lg font-mono uppercase focus:border-indigo-500 outline-none transition-all"
                               placeholder="Codice Fiscale..." required autofocus autocomplete="off">
                    </div>

                    <!-- Il pannello del ruolo appare qui (popolato da scanner.js) -->
                    <div id="user-info-display" class="mt-4 empty:hidden"></div>
                </div>

                <!-- 2. SCANSIONE LIBRI -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 text-center">2. Acquisizione Volumi</label>
                    <div class="relative">
                        <i class="fas fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" id="book_barcode"
                               class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-lg font-mono focus:border-indigo-600 outline-none transition-all"
                               placeholder="ID Inventario..." autocomplete="off">
                    </div>
                    <p class="text-[9px] text-slate-400 mt-4 text-center font-bold uppercase tracking-tighter">Supporto lettura sequenziale attiva</p>
                </div>

                <!-- Info Policy -->
                <div class="bg-slate-800 p-6 rounded-2xl text-white shadow-xl">
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 opacity-80">Policy di Circolazione</h4>
                    <ul class="text-xs space-y-3 font-medium text-slate-300">
                        <li class="flex items-start gap-3"><i class="fas fa-info-circle mt-0.5 text-indigo-400"></i> Studenti: Max 3 volumi / 14 gg</li>
                        <li class="flex items-start gap-3"><i class="fas fa-info-circle mt-0.5 text-indigo-400"></i> Docenti: Max 5 volumi / 30 gg</li>
                        <li class="flex items-start gap-3"><i class="fas fa-info-circle mt-0.5 text-indigo-400"></i> Staff: Max 12 volumi / 45 gg</li>
                        <li class="flex items-start gap-3"><i class="fas fa-exclamation-triangle mt-0.5 text-amber-400"></i> Blocco automatico per sanzioni</li>
                    </ul>
                </div>
            </div>

            <!-- COLONNA DESTRA: RIEPILOGO SESSIONE -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col h-full overflow-hidden min-h-[500px]">
                    <div class="p-5 border-b bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-black text-slate-700 uppercase text-xs tracking-widest">
                            <i class="fas fa-list-ul mr-2 text-indigo-600"></i>Coda di Uscita
                        </h3>
                        <span id="books-count" class="bg-indigo-600 text-white text-[10px] px-3 py-1 rounded-full font-black shadow-sm">0</span>
                    </div>

                    <!-- Lista dinamica dei libri -->
                    <div id="scanned-books-list" class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div id="empty-list-msg" class="h-full flex flex-col items-center justify-center text-slate-300 py-20">
                            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
                                <i class="fas fa-shopping-basket text-2xl opacity-20"></i>
                            </div>
                            <p class="font-black uppercase text-[10px] tracking-widest text-slate-400 text-center">Nessun volume nel carrello di uscita</p>
                        </div>
                    </div>

                    <!-- Bottone di Conferma Finale -->
                    <div class="p-6 bg-slate-50 border-t">
                        <button type="submit" id="submit-btn" disabled
                                class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 text-white p-5 rounded-xl font-black text-lg shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-3 uppercase tracking-tighter">
                            Finalizza Prestito Complessivo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Messaggi di Stato (Toasts) -->
<div id="scan-status" class="fixed bottom-8 right-8 z-50 pointer-events-none opacity-0 transition-all duration-300 transform translate-y-2"></div>

<!-- Script Scanner -->
<script src="../../public/assets/js/scanner.js"></script>

<script>
    // Monitoraggio UI
    const list = document.getElementById('scanned-books-list');
    const emptyMsg = document.getElementById('empty-list-msg');
    const submitBtn = document.getElementById('submit-btn');
    const countBadge = document.getElementById('books-count');

    const observer = new MutationObserver(() => {
        const count = list.querySelectorAll('.book-entry').length;
        countBadge.innerText = count;

        if (count > 0) {
            if (emptyMsg) emptyMsg.style.display = 'none';
            submitBtn.disabled = false;
        } else {
            if (emptyMsg) emptyMsg.style.display = 'flex';
            submitBtn.disabled = true;
        }
    });

    observer.observe(list, { childList: true });
</script>

<?php require_once __DIR__ . '/../../src/Views/layout/footer.php'; ?>
</body>
</html>