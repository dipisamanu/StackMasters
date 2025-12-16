document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loginForm");
    if (!form) return;

    // Elementi UI specifici del login
    const ui = {
        pass: document.getElementById("password"),
        toggle: document.getElementById("togglePassword"),
        submitBtn: document.querySelector("button[type='submit']")
    };

    // Configurazione Campi (Nel login dobbiamo validare solo l'email come formato)
    const fieldsConfig = [
        {
            id: "email",
            err: "errEmail",
            check: (val) => {
                // Non vuoto E formato email valido
                return val !== "" && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
            }
        }
    ];

    // --- HELPER FUNCTIONS ---

    function toggleError(id, show) {
        const el = document.getElementById(id);
        if (el) el.style.display = show ? "block" : "none";

        // Aggiungi/Rimuovi classe input rossa (opzionale, per coerenza visiva)
        // Cerca l'input associato all'errore
        const inputId = id.replace('err', '').toLowerCase();
        // Nota: questo hack funziona solo se id errore è "errNomeCampo" e input è "nomecampo"
        // Per sicurezza nel login facciamo selezione diretta se serve
        if (id === 'errEmail') document.getElementById('email').classList.toggle('is-invalid', show);
        if (id === 'errPassword') document.getElementById('password').classList.toggle('is-invalid', show);
    }

    function validateForm(e) {
        let isValid = true;

        // 1. Validazione Email (usando la config)
        fieldsConfig.forEach(field => {
            const el = document.getElementById(field.id);
            const val = el ? el.value.trim() : "";

            if (!field.check(val)) {
                toggleError(field.err, true);
                isValid = false;
            } else {
                toggleError(field.err, false);
            }
        });

        // 2. Validazione Password (SOLO controllo vuoto, nessuna regex complessa)
        const passVal = ui.pass ? ui.pass.value : "";
        if (passVal === "") {
            toggleError("errPassword", true);
            isValid = false;
        } else {
            toggleError("errPassword", false);
        }

        if (!isValid) {
            e.preventDefault();
        } else {
            // Feedback visivo sul bottone (UX)
            if (ui.submitBtn) {
                ui.submitBtn.textContent = 'Accesso in corso...';
                ui.submitBtn.style.opacity = '0.7';
            }
        }
    }

    // --- EVENT LISTENERS ---

    // Toglie l'errore mentre l'utente scrive
    fieldsConfig.forEach(field => {
        const el = document.getElementById(field.id);
        if (el) {
            el.addEventListener("input", () => toggleError(field.err, false));
        }
    });

    if (ui.pass) {
        ui.pass.addEventListener("input", () => toggleError("errPassword", false));
    }

    // Toggle Mostra/Nascondi Password
    if (ui.toggle && ui.pass) {
        ui.toggle.addEventListener("click", () => {
            const isPass = ui.pass.type === "password";
            ui.pass.type = isPass ? "text" : "password";
            ui.toggle.textContent = isPass ? "Mostra" : "Nascondi";
        });
    }

    form.addEventListener("submit", (e) => validateForm(e));
});