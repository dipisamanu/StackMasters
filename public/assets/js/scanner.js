/**
 * scanner.js - Versione ottimizzata per inserimento MANUALE da tastiera.
 * Gestisce l'identificazione Utenti e Libri, e la selezione della condizione.
 */

document.addEventListener("DOMContentLoaded", () => {
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");
    const loanForm = document.getElementById("loan-form");

    if (userInp) {
        userInp.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                const code = userInp.value.trim().toUpperCase();
                if (code) lookupUser(code);
            }
        });
        userInp.addEventListener("change", () => {
            const code = userInp.value.trim().toUpperCase();
            if (code) lookupUser(code);
        });
    }

    if (bookInp) {
        bookInp.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                const code = bookInp.value.trim();
                if (code) lookupBook(code);
            }
        });
    }

    if (loanForm) {
        loanForm.addEventListener("submit", (e) => {
            const user = userInp?.value.trim();
            const books = document.querySelectorAll('input[name="book_ids[]"]');
            if (!user) {
                e.preventDefault();
                showToast("Identifica prima un utente!", "error");
                userInp.focus();
            } else if (books.length === 0) {
                e.preventDefault();
                showToast("Aggiungi almeno un libro alla lista!", "error");
                bookInp.focus();
            }
        });
    }
});

async function lookupUser(code) {
    const infoDiv = document.getElementById('user-info-display');
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");

    try {
        const res = await fetch(`ajax-fetch-user.php?code=${encodeURIComponent(code)}`);
        const data = await res.json();
        if (data.success) {
            infoDiv.innerHTML = `<div class="p-5 bg-white border border-slate-200 rounded-2xl shadow-lg border-l-8 border-l-indigo-600 animate-pop-in relative overflow-hidden"><div class="absolute top-0 right-0 p-4 opacity-5 text-6xl"><i class="fas fa-user-circle"></i></div><div class="flex items-center gap-4 relative z-10"><div class="h-14 w-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg rotate-3"><i class="fas fa-user-check text-xl"></i></div><div><p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Utente Identificato</p><p class="font-black text-slate-800 text-xl leading-none">${data.nome} ${data.cognome}</p><div class="mt-2 flex items-center gap-2"><span class="text-[10px] font-black uppercase tracking-tighter bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full border border-indigo-200"><i class="fas fa-shield-alt mr-1"></i> ${data.ruolo}</span><span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ID: #${data.id_utente}</span></div></div></div></div>`;
            userInp.classList.remove('border-red-500');
            userInp.classList.add('border-indigo-500', 'bg-indigo-50/30');
            if (bookInp) bookInp.focus();
            showToast("Utente verificato", "success");
        } else {
            infoDiv.innerHTML = `<div class="p-4 bg-red-50 text-red-700 border border-red-100 rounded-2xl text-xs font-black uppercase tracking-widest flex items-center gap-3 shadow-sm"><div class="h-8 w-8 bg-red-600 text-white rounded-lg flex items-center justify-center"><i class="fas fa-times"></i></div>Utente non trovato</div>`;
            userInp.classList.add('border-red-500');
            showToast("Codice errato", "error");
        }
    } catch (e) {
        showToast("Errore di rete", "error");
    }
}

async function lookupBook(code) {
    const bookInp = document.getElementById("book_barcode");
    const list = document.getElementById('scanned-books-list');
    const emptyMsg = document.getElementById('empty-list-msg');

    if (document.querySelector(`input[name="book_ids[]"][value="${code}"]`)) {
        showToast("Libro già in carrello", "error");
        bookInp.value = "";
        return;
    }

    try {
        const res = await fetch(`ajax-fetch-book.php?id=${encodeURIComponent(code)}`);
        const responseData = await res.json();

        if (responseData.success) {
            const bookData = responseData.data;
            if (emptyMsg) emptyMsg.style.display = 'none';

            const row = document.createElement('div');
            row.className = "book-entry flex flex-col p-4 bg-white border border-slate-100 rounded-2xl shadow-md mb-3";
            
            const cover = bookData.immagine_copertina || '../../public/assets/img/placeholder.png';
            const autori = bookData.autori || 'Autore non specificato';

            row.innerHTML = `
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-5">
                        <div class="relative"><img src="${cover}" class="h-20 w-14 object-cover rounded-lg shadow-md" onerror="this.src='../../public/assets/img/placeholder.png'"><div class="absolute -bottom-2 -right-2 bg-slate-800 text-white text-[8px] font-black px-1.5 py-0.5 rounded shadow">#${bookData.id_inventario}</div></div>
                        <div><p class="text-base font-black text-slate-800 leading-tight mb-1 uppercase">${bookData.titolo}</p><p class="text-xs font-bold text-slate-400 italic">di ${autori}</p></div>
                    </div>
                    <button type="button" onclick="this.closest('.book-entry').remove(); checkEmptyList();" class="w-10 h-10 flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 rounded-full transition-all"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Condizione in Uscita</label>
                    <div class="flex items-center gap-2 condition-selector">
                        <input type="hidden" name="conditions[${bookData.id_inventario}]" value="${bookData.condizione || 'BUONO'}">
                        <input type="hidden" name="book_ids[]" value="${bookData.id_inventario}">
                        <button type="button" data-condition="BUONO" class="flex-1 p-2 text-xs font-bold rounded-lg">Buono</button>
                        <button type="button" data-condition="USURATO" class="flex-1 p-2 text-xs font-bold rounded-lg">Usurato</button>
                        <button type="button" data-condition="DANNEGGIATO" class="flex-1 p-2 text-xs font-bold rounded-lg">Danneggiato</button>
                    </div>
                </div>
            `;
            list.appendChild(row);

            const conditionSelector = row.querySelector('.condition-selector');
            const hiddenInput = conditionSelector.querySelector('input[type="hidden"]');
            
            function updateButtonStyles(selectedCondition) {
                conditionSelector.querySelectorAll('button').forEach(btn => {
                    const condition = btn.dataset.condition;
                    btn.classList.remove('border-green-500', 'bg-green-100', 'text-green-800', 'border-yellow-500', 'bg-yellow-100', 'text-yellow-800', 'border-red-500', 'bg-red-100', 'text-red-800');
                    btn.classList.add('border-2', 'border-transparent', 'text-slate-500');
                    
                    if (condition === selectedCondition) {
                        const colors = { 'BUONO': 'green', 'USURATO': 'yellow', 'DANNEGGIATO': 'red' };
                        const color = colors[condition];
                        btn.classList.add(`border-${color}-500`, `bg-${color}-100`, `text-${color}-800`);
                        btn.classList.remove('border-transparent', 'text-slate-500');
                    }
                });
            }

            conditionSelector.addEventListener('click', (e) => {
                if (e.target.tagName === 'BUTTON') {
                    const selectedCondition = e.target.dataset.condition;
                    hiddenInput.value = selectedCondition;
                    updateButtonStyles(selectedCondition);
                }
            });

            // Init styles
            updateButtonStyles(hiddenInput.value);

            bookInp.value = "";
            bookInp.focus();
            showToast("Copia aggiunta. Verifica la condizione.", "success");
        } else {
            showToast(responseData.error || "Copia non disponibile", "error");
            bookInp.classList.add('border-red-500');
        }
    } catch (e) {
        showToast("Errore di caricamento libro", "error");
    }
}

function checkEmptyList() {
    const list = document.getElementById('scanned-books-list');
    const emptyMsg = document.getElementById('empty-list-msg');
    if (list && list.querySelectorAll('.book-entry').length === 0) {
        if (emptyMsg) emptyMsg.style.display = 'flex';
    }
}

function showToast(msg, type) {
    let status = document.getElementById('scan-status');
    if (!status) return;
    status.innerText = msg;
    status.className = `fixed bottom-8 right-8 p-4 rounded-xl shadow-2xl text-white font-black text-[10px] uppercase tracking-widest z-50 pointer-events-none transition-all duration-300 ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
    status.style.opacity = '1';
    status.style.transform = 'translateY(0)';
    setTimeout(() => {
        status.style.opacity = '0';
        status.style.transform = 'translateY(10px)';
    }, 2500);
}
