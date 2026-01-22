<?php
/**
 * Interfaccia Restituzione Rapida (Manuale e Multi-book)
 * File: dashboard/librarian/returns.php
 */

require_once __DIR__ . '/../../src/config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit;
}

$nomeCompleto = ($_SESSION['nome_completo'] ?? 'Bibliotecario');
$message = $_SESSION['loan_success'] ?? $_SESSION['loan_error'] ?? '';
$message_type = isset($_SESSION['loan_success']) ? 'success' : (isset($_SESSION['loan_error']) ? 'error' : '');

// Pulizia messaggi sessione
unset($_SESSION['loan_success'], $_SESSION['loan_error']);
?>
<!DOCTYPE html>
<html lang="it" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restituzione Libri - StackMasters</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

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

        button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }

        button:disabled .group-hover\:text-green-500 {
            color: #a3a3a3 !important;
        }
    </style>
</head>
<body class="flex flex-col h-full">

<main class="flex-grow p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border-l-8 border-emerald-500 p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-emerald-50 p-4 rounded-xl text-3xl text-emerald-600">
                    <i class="fas fa-undo-alt"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Registrazione Rientri</h1>
                    <p class="text-slate-500 text-sm">Operatore: <strong><?= htmlspecialchars($nomeCompleto) ?></strong>
                    </p>
                </div>
            </div>
            <a href="index.php"
               class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>


        <!-- Feedback Messaggi Sessione -->
        <?php if ($message): ?>
            <div class="p-4 mb-6 rounded-xl font-bold text-sm border <?= $message_type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'; ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Form Restituzione -->
        <form id="return-form" action="registra-restituzione.php" method="POST">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <!-- COLONNA SINISTRA: INPUT MANUALE -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 text-center">Identifica
                            Copia Fisica</label>
                        <div class="flex flex-col gap-3">
                            <div class="relative">
                                <i class="fas fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                                <input type="text" id="book_barcode_input"
                                       class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-lg font-mono focus:border-emerald-500 outline-none transition-all"
                                       placeholder="Digita ID..." autofocus autocomplete="off">
                            </div>
                            <button type="button" onclick="handleManualSearch()"
                                    class="w-full bg-slate-800 hover:bg-slate-900 text-white font-black py-3 rounded-xl text-xs uppercase tracking-widest transition-all shadow-lg">
                                <i class="fas fa-search mr-2"></i> Verifica Volume
                            </button>
                        </div>
                    </div>

                    <div class="bg-emerald-600 p-6 rounded-2xl text-white shadow-xl">
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-emerald-100 mb-4 opacity-80">
                            Protocollo Rientro</h4>
                        <ul class="text-xs space-y-3 font-medium">
                            <li class="flex items-start gap-3"><i class="fas fa-clock mt-0.5"></i> Tolleranza: 3 giorni
                                solari
                            </li>
                            <li class="flex items-start gap-3"><i class="fas fa-coins mt-0.5"></i> Penale Ritardo: 0.50€
                                / giorno
                            </li>
                            <li class="flex items-start gap-3"><i class="fas fa-tools mt-0.5"></i> Valutazione danni
                                obbligatoria
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- COLONNA DESTRA: ELENCO RIENTRI -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col h-full overflow-hidden min-h-[500px]">
                        <div class="p-5 border-b bg-slate-50/50 flex justify-between items-center">
                            <h3 class="font-black text-slate-700 uppercase text-xs tracking-widest">Lista Volumi
                                Rientrati</h3>
                            <span id="returns-count"
                                  class="bg-emerald-500 text-white text-[10px] px-2.5 py-1 rounded-full font-black">0</span>
                        </div>

                        <!-- Lista scrollabile -->
                        <div id="scanned-returns-list" class="flex-1 overflow-y-auto p-4 space-y-3">
                            <div id="empty-return-msg"
                                 class="h-full flex flex-col items-center justify-center text-slate-300 py-20">
                                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-undo-alt text-4xl opacity-20"></i>
                                </div>
                                <p class="font-black uppercase text-[10px] tracking-widest text-slate-400">Scansiona o
                                    digita i volumi</p>
                            </div>
                        </div>

                        <div class="p-6 bg-slate-50 border-t">
                            <button type="submit" id="submit-return-btn" disabled
                                    class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white p-5 rounded-xl font-black text-lg shadow-xl shadow-emerald-100 transition-all flex items-center justify-center gap-3 uppercase tracking-tighter">
                                <i class="fas fa-check-double"></i> Finalizza Rientro Gruppo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<!-- MODALE VALUTAZIONE STATO -->
<div id="evaluation-modal"
     class="modal fixed inset-0 bg-slate-900/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-3xl shadow-2xl p-8 w-full max-w-md border-t-8 border-emerald-500">
        <h2 class="text-2xl font-black mb-2 text-slate-800 uppercase tracking-tight">Condizione Copia</h2>
        <p class="text-slate-500 text-sm mb-6 font-medium" id="modal-book-title">Valuta l'integrità del volume.</p>

        <div class="space-y-3 mb-6">
            <button type="button" data-condition="BUONO" onclick="confirmCondition('BUONO')"
                    class="w-full flex items-center justify-between p-4 bg-green-50 border-2 border-green-200 rounded-2xl hover:bg-green-100 transition-all group focus:ring-4 focus:ring-green-200 outline-none">
                <div class="text-left">
                    <span class="block font-black text-green-800 text-sm uppercase">BUONO / OTTIMO</span>
                    <span class="text-[10px] text-green-600 font-bold uppercase">Integrità mantenuta</span>
                </div>
                <i class="fas fa-check-circle text-green-300 group-hover:text-green-500 text-xl"></i>
            </button>

            <button type="button" data-condition="USURATO" onclick="confirmCondition('USURATO')"
                    class="w-full flex items-center justify-between p-4 bg-blue-50 border-2 border-blue-200 rounded-2xl hover:bg-blue-100 transition-all group focus:ring-4 focus:ring-blue-200 outline-none">
                <div class="text-left">
                    <span class="block font-black text-blue-800 text-sm uppercase">USURATO</span>
                    <span class="text-[10px] text-blue-600 font-bold uppercase">Lievie segni (10% penale)</span>
                </div>
                <i class="fas fa-info-circle text-blue-300 group-hover:text-blue-500 text-xl"></i>
            </button>

            <button type="button" data-condition="DANNEGGIATO" onclick="confirmCondition('DANNEGGIATO')"
                    class="w-full flex items-center justify-between p-4 bg-yellow-50 border-2 border-yellow-200 rounded-2xl hover:bg-yellow-100 transition-all group focus:ring-4 focus:ring-yellow-200 outline-none">
                <div class="text-left">
                    <span class="block font-black text-yellow-800 text-sm uppercase">DANNEGGIATO</span>
                    <span class="text-[10px] text-yellow-600 font-bold uppercase">Riparazione (50% penale)</span>
                </div>
                <i class="fas fa-exclamation-triangle text-yellow-300 group-hover:text-yellow-500 text-xl"></i>
            </button>

            <button type="button" data-condition="SMARRITO" onclick="confirmCondition('SMARRITO')"
                    class="w-full flex items-center justify-between p-4 bg-red-50 border-2 border-red-200 rounded-2xl hover:bg-red-100 transition-all group focus:ring-4 focus:ring-red-200 outline-none">
                <div class="text-left">
                    <span class="block font-black text-red-800 text-sm uppercase">SMARRITO</span>
                    <span class="text-[10px] text-red-600 font-bold uppercase">Sostituzione (100% penale)</span>
                </div>
                <i class="fas fa-times-circle text-red-300 group-hover:text-red-500 text-xl"></i>
            </button>
        </div>

        <textarea id="modal-comment"
                  class="w-full p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm focus:border-emerald-500 outline-none transition-all"
                  rows="2" placeholder="Note per l'utente (opzionale)..."></textarea>

        <button type="button" onclick="closeModal()"
                class="w-full mt-4 text-slate-400 font-bold text-[10px] uppercase hover:text-slate-600 transition-colors">
            Annulla operazione
        </button>
    </div>
</div>

<script>
    const returnInp = document.getElementById('book_barcode_input');
    const returnList = document.getElementById('scanned-returns-list');
    const modal = document.getElementById('evaluation-modal');
    const modalTitle = document.getElementById('modal-book-title');
    let currentScannedCode = "";
    let currentBookData = null;

    function handleManualSearch() {
        const code = returnInp.value.trim();
        if (code) {
            if (document.querySelector(`.item-${code}`)) {
                showStatus("Volume già presente in lista", "error");
                returnInp.value = "";
                return;
            }
            currentScannedCode = code;
            fetchBookInfo(code);
        } else {
            returnInp.focus();
        }
    }

    returnInp.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleManualSearch();
        }
    });

    async function fetchBookInfo(code) {
        try {
            returnInp.disabled = true;
            const res = await fetch(`ajax-fetch-book.php?id=${code}`);
            const responseData = await res.json();
            returnInp.disabled = false;

            if (responseData.success) {
                currentBookData = responseData.data;
                openModal(currentBookData.condizione);
            } else {
                showStatus(responseData.error || "Volume non identificato", "error");
                returnInp.value = "";
                returnInp.focus();
            }
        } catch (e) {
            returnInp.disabled = false;
            showStatus("Errore di connessione", "error");
        }
    }

    function openModal(startCondition = 'BUONO') {
        const conditionsMap = {'BUONO': 0, 'USURATO': 1, 'DANNEGGIATO': 2, 'SMARRITO': 3};
        const startLevel = conditionsMap[startCondition] || 0;

        modal.querySelectorAll('.space-y-3 button').forEach(button => {
            const buttonCondition = button.dataset.condition;
            const buttonLevel = conditionsMap[buttonCondition];
            button.disabled = buttonLevel < startLevel;
        });

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('modal-comment').focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        returnInp.value = "";
        returnInp.focus();
        currentBookData = null;
    }

    function confirmCondition(cond) {
        const comment = document.getElementById('modal-comment').value;
        addBookToReturnList(currentScannedCode, cond, comment, currentBookData.titolo, currentBookData.immagine_copertina);
        document.getElementById('modal-comment').value = "";
        closeModal();
    }

    function addBookToReturnList(code, cond, comment, title, cover) {
        const row = document.createElement('div');
        row.className = `book-entry item-${code} flex items-center justify-between p-5 bg-white border border-slate-100 rounded-2xl shadow-sm hover:border-emerald-100 transition-all border-l-4 border-l-emerald-500`;

        const badgeColor = {
            'BUONO': 'bg-green-100 text-green-700',
            'USURATO': 'bg-blue-100 text-blue-700',
            'DANNEGGIATO': 'bg-yellow-100 text-yellow-700',
            'SMARRITO': 'bg-red-100 text-red-700'
        }[cond];

        const coverImg = cover || '../../public/assets/img/placeholder.png';

        row.innerHTML = `
            <div class="flex items-center gap-4">
                <div class="w-14 h-20 bg-slate-50 rounded-lg overflow-hidden flex-shrink-0 shadow-sm border border-slate-100">
                    <img src="${coverImg}" class="w-full h-full object-cover" onerror="this.src='../../public/assets/img/placeholder.png'">
                </div>
                <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">ID Copia #${code}</p>
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-black px-2 py-0.5 rounded-full ${badgeColor} uppercase tracking-tighter">${cond}</span>
                        ${comment ? `<p class="text-[10px] text-slate-400 italic truncate max-w-[150px] font-medium">— ${comment}</p>` : ''}
                    </div>
                </div>
            </div>
            <input type="hidden" name="returns[]" value='${JSON.stringify({id: code, cond: cond, note: comment})}'>
            <button type="button" onclick="this.parentElement.remove(); updateCount();" class="w-10 h-10 flex items-center justify-center text-slate-200 hover:text-red-500 hover:bg-red-50 rounded-full transition-all">
                <i class="fas fa-times-circle"></i>
            </button>
        `;
        returnList.appendChild(row);
        updateCount();
    }

    function updateCount() {
        const count = returnList.querySelectorAll('.book-entry').length;
        const badge = document.getElementById('returns-count');
        const emptyMsg = document.getElementById('empty-return-msg');
        const btn = document.getElementById('submit-return-btn');

        badge.innerText = count;
        emptyMsg.style.display = count > 0 ? 'none' : 'flex';
        btn.disabled = count === 0;
    }

    function showStatus(msg, type) {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-8 right-8 p-4 rounded-xl shadow-2xl text-white font-black text-[10px] uppercase tracking-widest z-50 ${type === 'success' ? 'bg-emerald-600' : 'bg-red-600'}`;
        toast.innerText = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }
</script>
<?php
require_once __DIR__ . '/../../src/Views/layout/footer.php';
?>
</body>
</html>