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

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        input::placeholder {
            color: #cbd5e1;
            font-weight: 400;
        }

        #scanned-books-list::-webkit-scrollbar {
            width: 6px;
        }

        #scanned-books-list::-webkit-scrollbar-track {
            background: transparent;
        }

        #scanned-books-list::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }
    </style>
</head>
<body class="p-4 md:p-8">

<div class="max-w-7xl mx-auto">
    <!-- Header Istituzionale -->
    <div class="bg-white rounded-2xl shadow-sm border-l-8 border-indigo-600 p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="bg-indigo-50 p-4 rounded-xl text-indigo-600 text-3xl">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight leading-none mb-1">Registrazione
                    Prestito</h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Acquisizione rapida volumi in
                    uscita</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden md:block border-r pr-4 border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">
                    Operatore</p>
                <p class="font-bold text-slate-700 leading-none"><?= htmlspecialchars($nomeCompleto) ?></p>
            </div>
            <a href="index.php"
               class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Form Principale -->
    <form id="loan-form" action="registra-prestito.php" method="POST" class="space-y-8">

        <!-- SEZIONE SUPERIORE: INPUT E CODA -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- COLONNA SINISTRA: MODULI INPUT (5/12) -->
            <div class="lg:col-span-5 space-y-6">
                <!-- IDENTIFICA UTENTE -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-full -mr-16 -mt-16 opacity-50"></div>

                    <label class="relative z-10 block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">
                        <i class="fas fa-user-tag mr-2 text-indigo-500"></i>1. Identificazione Utente (CF)
                    </label>

                    <div class="relative z-10">
                        <i class="fas fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" id="user_barcode" name="user_barcode"
                               class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-2xl text-lg font-mono uppercase focus:border-indigo-500 outline-none transition-all shadow-inner"
                               placeholder="Inserisci o scansiona CF..." required autofocus autocomplete="off">
                    </div>

                    <!-- Pannello del ruolo -->
                    <div id="user-info-display" class="mt-4 empty:hidden"></div>
                </div>

                <!-- SCANSIONE LIBRI -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">
                        <i class="fas fa-barcode mr-2 text-indigo-500"></i>2. Acquisizione Volumi
                    </label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" id="book_barcode"
                               class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-2xl text-lg font-mono focus:border-indigo-600 outline-none transition-all shadow-inner"
                               placeholder="ID Inventario..." autocomplete="off">
                    </div>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <span class="animate-pulse w-2 h-2 bg-green-500 rounded-full"></span>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">Lettore Ottico
                            Pronto</p>
                    </div>
                </div>
            </div>

            <!-- COLONNA DESTRA: RIEPILOGO SESSIONE (7/12) -->
            <div class="lg:col-span-7">
                <div class="bg-white rounded-3xl shadow-md border border-slate-100 flex flex-col h-full overflow-hidden min-h-[480px]">
                    <div class="p-6 border-b bg-white flex justify-between items-center">
                        <div>
                            <h3 class="font-black text-slate-800 uppercase text-sm tracking-tight">
                                <i class="fas fa-shopping-cart mr-2 text-indigo-600"></i>Coda di Uscita
                            </h3>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">Elementi pronti per la
                                registrazione</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span id="books-count"
                                  class="bg-indigo-600 text-white text-xs px-4 py-1.5 rounded-full font-black shadow-lg shadow-indigo-200">0</span>
                        </div>
                    </div>

                    <!-- Lista dinamica dei libri -->
                    <div id="scanned-books-list" class="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50/30">
                        <div id="empty-list-msg"
                             class="h-full flex flex-col items-center justify-center text-slate-300 py-20">
                            <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center mb-4 shadow-sm border border-slate-100">
                                <i class="fas fa-book-open text-3xl opacity-10"></i>
                            </div>
                            <p class="font-black uppercase text-[11px] tracking-widest text-slate-400 text-center">
                                Nessun volume scansionato</p>
                        </div>
                    </div>

                    <!-- Bottone di Conferma Finale -->
                    <div class="p-6 bg-white border-t">
                        <button type="submit" id="submit-btn" disabled
                                class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 text-white p-5 rounded-2xl font-black text-lg shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-3 uppercase tracking-tighter">
                            Finalizza Prestito Complessivo <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEZIONE INFERIORE: POLICY E INFO SCADENZE -->
        <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
            <div class="flex flex-col md:flex-row items-center gap-8">
                <div class="md:w-1/4">
                    <h4 class="text-lg font-black text-slate-800 uppercase tracking-tighter leading-none mb-2">Policy
                        <span class="text-indigo-600">Circolazione</span></h4>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-widest">Termini di restituzione
                        basati sul ruolo utente</p>
                </div>
                <div class="md:w-3/4 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-100">
                        <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2">Studenti</p>
                        <p class="text-sm font-bold text-slate-700">Max 3 volumi</p>
                        <p class="text-xs font-medium text-indigo-600">Durata: 14 giorni</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100">
                        <p class="text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-2">Docenti</p>
                        <p class="text-sm font-bold text-slate-700">Max 5 volumi</p>
                        <p class="text-xs font-medium text-emerald-600">Durata: 30 giorni</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Staff</p>
                        <p class="text-sm font-bold text-slate-700">Max 10 volumi</p>
                        <p class="text-xs font-medium text-slate-500">Durata: 45 giorni</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Messaggi di Stato (Toasts) -->
<div id="scan-status"
     class="fixed bottom-8 right-8 z-50 pointer-events-none opacity-0 transition-all duration-300 transform translate-y-2"></div>

<!-- Script Scanner -->
<script src="../../public/assets/js/scanner.js"></script>

<script>
    // Monitoraggio UI per attivazione bottone e conteggio
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

    observer.observe(list, {childList: true});
</script>

<?php require_once __DIR__ . '/../../src/Views/layout/footer.php'; ?>
</body>
</html>