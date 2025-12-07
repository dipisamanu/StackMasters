document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registrationForm");
    if (!form) return;

    // Elementi frequenti
    const ui = {
        pass: document.getElementById("password"),
        conf: document.getElementById("confermaPassword"),
        toggle: document.getElementById("togglePassword"),
        errPass: document.getElementById("errPassword"),
        errConf: document.getElementById("errConfermaPassword")
    };

    // Configurazione Campi e Validazioni
    const fieldsConfig = [
        { id: "nome", err: "errNome" },
        { id: "cognome", err: "errCognome" },
        { id: "sesso", err: "errSesso" },
        { id: "comune", err: "errComune" },
        {
            id: "dataNascita",
            err: "errData",
            check: (val) => {
                // Controlla non vuoto E range valido (opzionale, ma consigliato)
                if (!val) return false;
                const d = new Date(val);
                const min = new Date("1900-01-01");
                const max = new Date("2025-12-31");
                return d >= min && d <= max;
            }
        },
        {
            id: "codiceFiscale",
            err: "errCF",
            check: (val) => {
                // Opzionale: valido se vuoto O se regex corrisponde
                return val === "" || /^[A-Z0-9]{16}$/i.test(val);
            }
        },
        {
            id: "email",
            err: "errEmail",
            check: (val) => { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val); }
        }
    ];

    // --- HELPER FUNCTIONS ---

    function toggleError(id, show) {
        const el = document.getElementById(id);
        if (el) el.style.display = show ? "block" : "none";
    }

    function checkPasswordRules(pw) {
        return {
            len: pw.length >= 8,
            upper: /[A-Z]/.test(pw),
            digit: /[0-9]/.test(pw),
            special: /[\W_]/.test(pw)
        };
    }

    function updatePasswordUI() {
        if (!ui.pass) return;
        const val = ui.pass.value;
        const rules = checkPasswordRules(val);

        const map = {
            pwLen: rules.len,
            pwUpper: rules.upper,
            pwDigit: rules.digit,
            pwSpecial: rules.special
        };

        for (let id in map) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.toggle("valid", map[id]);
                el.classList.toggle("invalid", !map[id]);
            }
        }

        // Nascondi errore generale mentre si digita
        toggleError("errPassword", false);

        // Verifica match in tempo reale
        if (ui.conf && ui.conf.value !== "") {
            toggleError("errConfermaPassword", val !== ui.conf.value);
        }
    }

    function validateForm(e) {
        let isValid = true;

        // 1. Validazione campi standard
        fieldsConfig.forEach(field => {
            const el = document.getElementById(field.id);
            const val = el ? el.value.trim() : "";
            let fieldOk = true;

            if (field.check) {
                fieldOk = field.check(val);
                // Email è required nel form HTML, quindi non può essere vuota anche se la regex passerebbe stringa vuota
                if (field.id === "email" && val === "") fieldOk = false;
            } else {
                fieldOk = val !== "";
            }

            if (!fieldOk) {
                toggleError(field.err, true);
                isValid = false;
            } else {
                toggleError(field.err, false);
            }
        });

        // 2. Validazione Password solo se i campi esistono
        if (ui.pass && ui.conf) {
            const pwRules = checkPasswordRules(ui.pass.value);
            const isPwSecure = Object.values(pwRules).every(Boolean);

            if (!isPwSecure) {
                toggleError("errPassword", true);
                isValid = false;
            }

            if (ui.pass.value !== ui.conf.value) {
                toggleError("errConfermaPassword", true);
                isValid = false;
            }
        }

        if (!isValid) e.preventDefault();
    }

    // --- EVENT LISTENERS ---

    if (ui.pass) {
        ui.pass.addEventListener("input", () => updatePasswordUI());
        // importante per il PHP reload
        updatePasswordUI();
    }

    if (ui.conf) {
        ui.conf.addEventListener("input", () => {
            toggleError("errConfermaPassword", ui.pass.value !== ui.conf.value);
        });
    }

    if (ui.toggle && ui.pass) {
        ui.toggle.addEventListener("click", () => {
            const isPass = ui.pass.type === "password";
            ui.pass.type = isPass ? "text" : "password";
            ui.toggle.textContent = isPass ? "Mostra" : "Nascondi";
        });
    }

    form.addEventListener("submit", (e) => validateForm(e));
});