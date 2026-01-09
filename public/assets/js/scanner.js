// =======================================================
// CONFIGURAZIONE
// =======================================================
const SCANNER_CONFIG = {
    maxInterval: 40,          // ms tra i tasti → scanner
    minLength: 3,
    beepOk: "/sounds/beep.mp3",
    beepError: "/sounds/buzzer.mp3"
};

// =======================================================
// REGEX
// =======================================================
const CF_REGEX = /^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i;
const BOOK_REGEX = /^(?:\d{13}|(?=.*[A-Z])[A-Z0-9]{6,20})$/i;

// =======================================================
// STATO SCANNER
// =======================================================
let buffer = "";
let lastKeyTime = Date.now();

// =======================================================
// AUDIO FEEDBACK
// =======================================================
const AudioFeedback = {
    ok() {
        new Audio(SCANNER_CONFIG.beepOk).play().catch(() => {});
    },
    error() {
        new Audio(SCANNER_CONFIG.beepError).play().catch(() => {});
    }
};

// =======================================================
// UTILS
// =======================================================
function isPrintableKey(e) {
    return e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey;
}

// =======================================================
// ICONA BARCODE
// =======================================================
function setBarcodeIcon(el, state) {
    const icon = el?.parentElement?.querySelector(".barcode-icon");
    if (!icon) return;

    icon.classList.remove("text-gray-400", "text-green-500", "text-red-500");
    if (state === "ok") icon.classList.add("text-green-500");
    else if (state === "error") icon.classList.add("text-red-500");
    else icon.classList.add("text-gray-400");
}

// =======================================================
// ERRORE NON BLOCCANTE
// =======================================================
function showError(msg) {
    console.error(msg);
    AudioFeedback.error();
    // Sostituibile con toast / modal
}

// =======================================================
// SCANNER LISTENER GLOBALE
// =======================================================
document.addEventListener("keydown", (e) => {
    const now = Date.now();

    if (!isPrintableKey(e) && e.key !== "Enter") return;

    if (now - lastKeyTime < SCANNER_CONFIG.maxInterval) {
        if (e.key !== "Enter") buffer += e.key;
    } else {
        buffer = e.key !== "Enter" ? e.key : "";
    }

    lastKeyTime = now;

    if (e.key === "Enter") {
        e.preventDefault();

        if (buffer.length >= SCANNER_CONFIG.minLength) {
            processBarcode(buffer.trim().toUpperCase());
        }

        buffer = "";
    }
});

// =======================================================
// PROCESS BARCODE
// =======================================================
function processBarcode(code) {
    const userInp = document.getElementById("user_barcode");
    const bookInp = document.getElementById("book_barcode");

    // --- Codice Fiscale ---
    if (CF_REGEX.test(code)) {
        if (!userInp) return;

        userInp.value = code;
        userInp.classList.add("border-green-500", "bg-green-50");
        setBarcodeIcon(userInp, "ok");
        AudioFeedback.ok();

        bookInp?.focus();
        return;
    }

    // --- Codice Libro ---
    if (BOOK_REGEX.test(code)) {
        if (!bookInp) return;

        bookInp.value = code;
        bookInp.classList.add("border-green-500", "bg-green-50");
        setBarcodeIcon(bookInp, "ok");
        AudioFeedback.ok();
        return;
    }

    // --- ERRORE ---
    setBarcodeIcon(userInp, "error");
    setBarcodeIcon(bookInp, "error");
    showError(
        "Codice non valido: " + code +
        " (non CF, non EAN13, non codice interno)"
    );
}

// =======================================================
// VALIDAZIONE INPUT MANUALE
// =======================================================
function checkFieldLogic(el, type) {
    const val = el.value.trim().toUpperCase();
    if (!val) return;

    if (type === "user") {
        if (!CF_REGEX.test(val)) {
            setBarcodeIcon(el, "error");
            showError("Codice Fiscale non valido");
            el.focus();
            return;
        }
        setBarcodeIcon(el, "ok");
        AudioFeedback.ok();
        document.getElementById("book_barcode")?.focus();
    }

    if (type === "book") {
        if (!BOOK_REGEX.test(val)) {
            setBarcodeIcon(el, "error");
            showError("Codice libro non valido");
            el.focus();
            return;
        }
        setBarcodeIcon(el, "ok");
        AudioFeedback.ok();
    }
}

// =======================================================
// BLOCCO SUBMIT FORM
// =======================================================
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loan-form");
    if (!form) return;

    form.addEventListener("submit", (e) => {
        const user = document.getElementById("user_barcode")?.value.trim();
        const book = document.getElementById("book_barcode")?.value.trim();

        if (!CF_REGEX.test(user)) {
            e.preventDefault();
            showError("Inserire un Codice Fiscale valido");
            document.getElementById("user_barcode")?.focus();
            return;
        }

        if (!BOOK_REGEX.test(book)) {
            e.preventDefault();
            showError("Inserire un codice libro valido");
            document.getElementById("book_barcode")?.focus();
            return;
        }

        // Reset per scansioni a raffica
        setTimeout(() => {
            document.getElementById("user_barcode").value = "";
            document.getElementById("book_barcode").value = "";
            document.getElementById("user_barcode")?.focus();
        }, 300);
    });
});
