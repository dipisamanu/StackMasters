// /public/assets/js/scanner.js

let buffer = "";
let lastKeyTime = 0;

// Regex
const CF_REGEX = /^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i;
// Codice libro: EAN13 o codice interno alfanumerico minimo 3 caratteri
const BOOK_REGEX = /^(\d{13}|[A-Z0-9]{3,})$/i;

// Funzioni helper per icona
function setBarcodeIcon(el, state) {
    const icon = el.parentElement.querySelector(".barcode-icon");
    if (!icon) return;

    icon.classList.remove("text-gray-400", "text-green-500", "text-red-500");
    if (state === "ok") icon.classList.add("text-green-500");
    else if (state === "error") icon.classList.add("text-red-500");
    else icon.classList.add("text-gray-400");
}

// Alert blocco
function forceError(msg) {
    alert(msg); // BLOCCANTE
}

// Scanner veloce
document.addEventListener("keydown", function (e) {
    const now = Date.now();

    // Se la digitazione è veloce → scanner
    if (now - lastKeyTime < 40) {
        if (e.key !== "Enter") buffer += e.key;
    } else {
        if (e.key !== "Enter") buffer = e.key;
    }
    lastKeyTime = now;

    // Scanner invia Enter alla fine
    if (e.key === "Enter") {
        e.preventDefault();
        if (buffer.length > 2) {
            processBarcode(buffer.trim().toUpperCase());
        }
        buffer = "";
    }
});

// Process barcode
function processBarcode(code) {
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");

    // --- Codice Fiscale ---
    if (CF_REGEX.test(code)) {
        userInp.value = code;
        userInp.classList.add("border-green-500", "bg-green-50");
        setBarcodeIcon(userInp, "ok");
        bookInp.focus();
        return;
    }

    // --- Codice Libro ---
    if (BOOK_REGEX.test(code)) {
        bookInp.value = code;
        bookInp.classList.add("border-green-500", "bg-green-50");
        setBarcodeIcon(bookInp, "ok");
        return;
    }

    // --- Errore ---
    setBarcodeIcon(userInp, "error");
    setBarcodeIcon(bookInp, "error");
    forceError(
        "❌ CODICE NON VALIDO\n\n" +
        "Il codice scannerizzato non è né:\n" +
        "- un Codice Fiscale valido\n" +
        "- un Codice Libro valido (EAN13 o codice interno minimo 3 caratteri)"
    );
}

// Validazione input manuale
function checkFieldLogic(el, type) {
    const val = el.value.trim().toUpperCase();
    if (!val) return;

    if (type === "user") {
        if (!CF_REGEX.test(val)) {
            forceError("❌ Codice Fiscale NON valido");
            el.value = "";
            el.focus();
            setBarcodeIcon(el, "error");
            return;
        }
        setBarcodeIcon(el, "ok");
        document.getElementById("book_barcode").focus();
    }

    if (type === "book") {
        if (!BOOK_REGEX.test(val)) {
            forceError("❌ Codice Libro NON valido");
            el.value = "";
            el.focus();
            setBarcodeIcon(el, "error");
            return;
        }
        setBarcodeIcon(el, "ok");
    }
}

// Blocca submit se dati non validi
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loan-form");
    form.addEventListener("submit", (e) => {
        const user = document.getElementById("user_barcode").value.trim();
        const book = document.getElementById("book_barcode").value.trim();

        if (!CF_REGEX.test(user)) {
            e.preventDefault();
            forceError("Inserire un Codice Fiscale valido");
            document.getElementById("user_barcode").focus();
            return;
        }

        if (!BOOK_REGEX.test(book)) {
            e.preventDefault();
            forceError("Inserire un codice libro valido (EAN13 o codice interno minimo 3 caratteri)");
            document.getElementById("book_barcode").focus();
            return;
        }
    });
});
