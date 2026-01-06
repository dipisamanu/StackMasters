/**
 * scanner.js - Supporto Duale: Scanner HID (Veloce) e Inserimento Manuale (Lento)
 * Ottimizzato per test senza hardware.
 */

let buffer = "";
let lastKeyTime = Date.now();

// Regex per il riconoscimento dei formati
const CF_REGEX = /^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i;
const BOOK_REGEX = /^(?:\d{1,13}|[A-Z0-9-]{3,})$/i;

document.addEventListener("DOMContentLoaded", () => {
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");

    /**
     * 1. GESTIONE MANUALE (TAB / CLICK FUORI)
     * Quando finisci di scrivere e passi al campo successivo
     */
    if (userInp) {
        userInp.addEventListener("blur", () => {
            if (userInp.value.trim().length > 0) {
                processInput(userInp.value.trim(), "user");
            }
        });
    }

    if (bookInp) {
        bookInp.addEventListener("blur", () => {
            if (bookInp.value.trim().length > 0) {
                processInput(bookInp.value.trim(), "book");
            }
        });
    }
});

/**
 * 2. GESTIONE TASTIERA (INVIO / SCANNER)
 */
document.addEventListener("keydown", function (e) {
    const now = Date.now();

    // Logica Buffer per Scanner HID (molto veloce)
    if (now - lastKeyTime < 50) {
        if (e.key !== "Enter") buffer += e.key;
    } else {
        if (e.key !== "Enter") buffer = e.key;
    }
    lastKeyTime = now;

    // Quando viene premuto INVIO (sia da tastiera che da scanner)
    if (e.key === "Enter") {
        const input = (buffer.length > 1 ? buffer : document.activeElement.value).trim().toUpperCase();

        if (input.length > 0) {
            // Se siamo nei campi di input, blocca l'invio del form e processa il codice
            if (document.activeElement.id === "user_barcode" || document.activeElement.id === "book_barcode") {
                e.preventDefault();
                processInput(input);
            }
        }
        buffer = "";
    }
});

/**
 * Funzione Centrale di Elaborazione
 */
async function processInput(code, forceType = null) {
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");
    const codeUpper = code.toUpperCase();

    // Se il focus è sull'utente o se il codice è un CF
    if (forceType === "user" || document.activeElement === userInp || CF_REGEX.test(codeUpper)) {
        userInp.value = codeUpper;
        const success = await fetchUserInfo(codeUpper, userInp);
        // Se l'utente è valido, sposta il cursore sul libro automaticamente
        if (success && bookInp) bookInp.focus();
        return;
    }

    // Se il focus è sul libro
    if (forceType === "book" || document.activeElement === bookInp) {
        await addBookToList(codeUpper, bookInp);
        return;
    }
}

/**
 * AJAX - Ricerca Utente
 */
async function fetchUserInfo(code, inputElement) {
    const infoDiv = document.getElementById('user-info-display');

    // Applica stile base per il bordo
    inputElement.classList.remove('border-green-500', 'border-red-500', 'bg-green-50', 'bg-red-50');
    inputElement.classList.add('border-2');

    try {
        const res = await fetch(`ajax-fetch-user.php?code=${code}`);
        const data = await res.json();

        if (data.success) {
            inputElement.classList.add('border-green-500', 'bg-green-50');
            infoDiv.innerHTML = `
                <div class="flex items-center gap-4 p-4 bg-green-50 border border-green-200 rounded-xl shadow-sm animate-fade-in">
                    <div class="h-12 w-12 bg-green-600 rounded-full flex items-center justify-center text-white text-xl">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <p class="font-black text-green-900 leading-none">${data.nome} ${data.cognome}</p>
                        <p class="text-[10px] text-green-700 uppercase font-black mt-1 tracking-widest">${data.ruolo}</p>
                    </div>
                </div>`;
            return true;
        } else {
            inputElement.classList.add('border-red-500', 'bg-red-50');
            infoDiv.innerHTML = `
                <div class="p-3 bg-red-50 text-red-700 border border-red-200 rounded-xl text-xs font-bold flex items-center gap-2">
                    <i class="fas fa-user-times"></i> Utente non trovato (ID: ${code})
                </div>`;
            return false;
        }
    } catch (e) {
        console.error("Errore Fetch Utente");
        return false;
    }
}

/**
 * AJAX - Ricerca Libro e Lista
 */
async function addBookToList(code, inputElement) {
    inputElement.classList.remove('border-green-500', 'border-red-500', 'bg-green-50', 'bg-red-50');
    inputElement.classList.add('border-2');

    // Evita duplicati nella lista corrente
    if (document.querySelector(`input[name="book_ids[]"][value="${code}"]`)) {
        showStatus("Libro già aggiunto", "error");
        return;
    }

    try {
        const res = await fetch(`ajax-fetch-book.php?id=${code}`);
        const data = await res.json();

        if (data.success) {
            inputElement.classList.add('border-green-500', 'bg-green-50');
            inputElement.value = ""; // Pulisce solo se ha successo

            const list = document.getElementById('scanned-books-list');
            const row = document.createElement('div');
            row.className = "book-entry flex items-center justify-between p-4 bg-white border-b hover:bg-slate-50 transition-all border-l-4 border-l-green-500 mb-1";
            row.innerHTML = `
                <div class="flex items-center gap-4">
                    <img src="${data.immagine_copertina}" class="h-12 w-9 object-cover rounded shadow-sm border border-slate-100">
                    <div>
                        <p class="text-sm font-black text-slate-800 uppercase leading-tight">${data.titolo}</p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">ID: ${data.id_inventario} | POS: ${data.collocazione}</p>
                    </div>
                </div>
                <input type="hidden" name="book_ids[]" value="${data.id_inventario}">
                <button type="button" onclick="this.parentElement.remove()" class="h-8 w-8 text-slate-300 hover:text-red-500 transition-all">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
            list.appendChild(row);
            showStatus("Libro pronto per il prestito", "success");
        } else {
            inputElement.classList.add('border-red-500', 'bg-red-50');
            showStatus("Copia non trovata", "error");
        }
    } catch (e) {
        console.error("Errore Fetch Libro");
    }
}

function showStatus(msg, type) {
    const status = document.getElementById('scan-status');
    if (!status) return;
    status.innerText = msg;
    status.className = `fixed bottom-8 right-8 p-4 rounded-xl shadow-2xl text-white font-bold transition-all duration-300 z-50 pointer-events-none ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
    status.style.opacity = '1';
    setTimeout(() => { status.style.opacity = '0'; }, 2500);
}