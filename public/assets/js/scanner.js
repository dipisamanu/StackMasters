/**
 * scanner.js - Versione ottimizzata per inserimento MANUALE da tastiera.
 * Gestisce l'identificazione Utenti e Libri tramite tasto Invio o pulsanti.
 * Estetica migliorata per il pannello utente e la lista libri (Copertine + Titoli).
 */

document.addEventListener("DOMContentLoaded", () => {
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");
    const loanForm = document.getElementById("loan-form");

    // 1. GESTIONE INPUT UTENTE (CF o ID)
    if (userInp) {
        userInp.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                const code = userInp.value.trim().toUpperCase();
                if (code) lookupUser(code);
            }
        });

        // Trigger anche al cambio focus se il valore è stato inserito
        userInp.addEventListener("change", () => {
            const code = userInp.value.trim().toUpperCase();
            if (code) lookupUser(code);
        });
    }

    // 2. GESTIONE INPUT LIBRO (ID Inventario o ISBN)
    if (bookInp) {
        bookInp.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                const code = bookInp.value.trim();
                if (code) lookupBook(code);
            }
        });
    }

    // 3. VALIDAZIONE FINALE AL SUBMIT
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

/**
 * Funzione di ricerca Utente (AJAX) - Pannello grafico migliorato
 */
async function lookupUser(code) {
    const infoDiv = document.getElementById('user-info-display');
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");

    try {
        const res = await fetch(`ajax-fetch-user.php?code=${encodeURIComponent(code)}`);
        const data = await res.json();

        if (data.success) {
            // Mostra il pannello del ruolo con grafica premium
            infoDiv.innerHTML = `
                <div class="p-5 bg-white border border-slate-200 rounded-2xl shadow-lg border-l-8 border-l-indigo-600 animate-pop-in relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-5 text-6xl">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="h-14 w-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg rotate-3">
                            <i class="fas fa-user-check text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Utente Identificato</p>
                            <p class="font-black text-slate-800 text-xl leading-none">${data.nome} ${data.cognome}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-tighter bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full border border-indigo-200">
                                    <i class="fas fa-shield-alt mr-1"></i> ${data.ruolo}
                                </span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ID: #${data.id_utente}</span>
                            </div>
                        </div>
                    </div>
                </div>`;

            userInp.classList.remove('border-red-500');
            userInp.classList.add('border-indigo-500', 'bg-indigo-50/30');

            // Passa automaticamente al campo libro
            if (bookInp) bookInp.focus();
            showToast("Utente verificato", "success");
        } else {
            infoDiv.innerHTML = `
                <div class="p-4 bg-red-50 text-red-700 border border-red-100 rounded-2xl text-xs font-black uppercase tracking-widest flex items-center gap-3 shadow-sm">
                    <div class="h-8 w-8 bg-red-600 text-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </div>
                    Utente non trovato nel sistema
                </div>`;
            userInp.classList.add('border-red-500');
            showToast("Codice errato", "error");
        }
    } catch (e) {
        showToast("Errore di rete", "error");
    }
}

/**
 * Funzione di ricerca Libro (AJAX) - Lista grafica migliorata
 */
async function lookupBook(code) {
    const bookInp = document.getElementById("book_barcode");
    const list = document.getElementById('scanned-books-list');
    const emptyMsg = document.getElementById('empty-list-msg');

    // 1. Evita duplicati
    if (document.querySelector(`input[name="book_ids[]"][value="${code}"]`)) {
        showToast("Libro già in carrello", "error");
        bookInp.value = "";
        return;
    }

    try {
        const res = await fetch(`ajax-fetch-book.php?id=${encodeURIComponent(code)}`);
        const data = await res.json();

        if (data.success) {
            if (emptyMsg) emptyMsg.style.display = 'none';

            // Creazione riga libro con stile card orizzontale
            const row = document.createElement('div');
            row.className = "book-entry flex items-center justify-between p-4 bg-white border border-slate-100 rounded-2xl shadow-md hover:border-red-200 transition-all border-l-4 border-l-red-500 mb-3 group";

            const cover = data.immagine_copertina || '../../public/assets/img/placeholder.png';
            const autori = data.autori || 'Autore non specificato';

            row.innerHTML = `
                <div class="flex items-center gap-5">
                    <div class="relative">
                        <img src="${cover}" class="h-20 w-14 object-cover rounded-lg shadow-md group-hover:scale-105 transition-transform" 
                             onerror="this.src='../../public/assets/img/placeholder.png'">
                        <div class="absolute -bottom-2 -right-2 bg-slate-800 text-white text-[8px] font-black px-1.5 py-0.5 rounded shadow">
                            #${data.id_inventario}
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-red-600 uppercase tracking-widest mb-1">Copia Disponibile</p>
                        <p class="text-base font-black text-slate-800 leading-tight mb-1 uppercase">${data.titolo}</p>
                        <p class="text-xs font-bold text-slate-400 italic">di ${autori}</p>
                        <div class="mt-2 flex items-center gap-3">
                             <span class="text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md uppercase tracking-tighter">
                                <i class="fas fa-map-marker-alt mr-1"></i> ${data.collocazione || 'N/D'}
                             </span>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="book_ids[]" value="${data.id_inventario}">
                <button type="button" onclick="this.parentElement.remove(); checkEmptyList();" 
                        class="w-10 h-10 flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 rounded-full transition-all">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;

            list.appendChild(row);

            // Reset input
            bookInp.value = "";
            bookInp.classList.remove('border-red-500');
            bookInp.classList.add('border-green-500', 'bg-green-50/30');
            bookInp.focus();

            showToast("Copia aggiunta alla lista", "success");
        } else {
            showToast(data.error || "Copia non disponibile", "error");
            bookInp.classList.add('border-red-500');
            bookInp.value = "";
            bookInp.focus();
        }
    } catch (e) {
        showToast("Errore di caricamento libro", "error");
    }
}

/**
 * Utilità per ripristinare il messaggio di carrello vuoto
 */
function checkEmptyList() {
    const list = document.getElementById('scanned-books-list');
    const emptyMsg = document.getElementById('empty-list-msg');
    if (list && list.querySelectorAll('.book-entry').length === 0) {
        if (emptyMsg) emptyMsg.style.display = 'flex';
    }
}

/**
 * Feedback visivo Toast (in basso a destra)
 */
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