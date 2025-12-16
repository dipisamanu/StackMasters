let buffer = "";
let lastKeyTime = 0;

document.addEventListener("keydown", function (e) {
    const now = Date.now();

    // Se la digitazione è veloce → scanner
    if (now - lastKeyTime < 40) {
        buffer += e.key;
    } else {
        buffer = e.key;
    }
    lastKeyTime = now;

    // Lo scanner invia Enter alla fine
    if (e.key === "Enter") {
        e.preventDefault();
        processBarcode(buffer.trim());
        buffer = "";
    }
});


function processBarcode(code) {
    console.log("Scansionato:", code);

    // --- TESSERA UTENTE ---
    if (/^U\d{4,}$/.test(code)) {
        fillUserField(code);
        return;
    }

    // --- EAN13 ---
    if (/^\d{13}$/.test(code)) {
        fillBookField(code);
        return;
    }

    // --- CODE128 interno ---
    if (/^[A-Z]\d{3,}$/.test(code)) {
        fillBookField(code);
        return;
    }

    alert("Codice non riconosciuto: " + code);
}

// ----------- PRESTITO -----------
function fillUserField(code) {
    const userInput = document.getElementById("user_barcode");
    if (userInput) {
        userInput.value = code;

        const next = document.getElementById("book_barcode");
        if (next) next.focus();
    }
}

function fillBookField(code) {
    const campoPrestito = document.getElementById("book_barcode");
    const campoRest = document.getElementById("book_barcode_display");

    if (campoPrestito) {
        campoPrestito.value = code;
        return;
    }

    // ----------- RESTITUZIONE -----------
    if (campoRest) {
        campoRest.value = code;

        const btn = document.getElementById("trigger_evaluation_button");
        if (btn) btn.click();
        return;
    }
}
