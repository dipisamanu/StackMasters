document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registrationForm");

    if (!form) return;
    const getById = (id) => document.getElementById(id);

    const password = getById("password");
    const confermaPass = getById("confermaPassword");
    const toggleBtn = getById("togglePassword");

    function checkPasswordRules(pw) {
        return {
            len: pw.length >= 8,
            upper: /[A-Z]/.test(pw),
            digit: /[0-9]/.test(pw),
            special: /[\W_]/.test(pw)
        };
    }

    function updatePwUi(pw) {
        const rules = checkPasswordRules(pw);
        const rulesMap = {
            pwLen: rules.len,
            pwUpper: rules.upper,
            pwDigit: rules.digit,
            pwSpecial: rules.special
        };

        Object.keys(rulesMap).forEach((id) => {
            const element = getById(id);
            element.classList.toggle("valid", rulesMap[id]);
            element.classList.toggle("invalid", !rulesMap[id]);
        });
    }

    // Live check while typing
    if (password) {
        password.addEventListener("input", () => {
            updatePwUi(password.value);
            const errPassword = getById("errPassword");
            if (errPassword) errPassword.style.display = "none";
            if (confermaPass && confermaPass.value !== "") {
                const errConf = getById("errConfermaPassword");
                if (errConf) errConf.style.display = (password.value !== confermaPass.value) ? "block" : "none";
            }
        });
    }

    // Check conferma password live
    if (confermaPass) {
        confermaPass.addEventListener("input", () => {
            const errConf = getById("errConfermaPassword");
            if (errConf && password) errConf.style.display = (password.value !== confermaPass.value) ? "block" : "none";
        });
    }

    // Toggle visibilità password
    if (toggleBtn && password) {
        toggleBtn.addEventListener("click", () => {
            const type = password.type === "password" ? "text" : "password";
            password.type = type;
            toggleBtn.textContent = (type === "text") ? "Nascondi" : "Mostra";
        });
    }

    // Funzione generica di validazione campi obbligatori
    function validateField(id, errorId) {
        const field = document.getElementById(id);
        const error = document.getElementById(errorId);

        if (field && field.value.trim() === "") {
            if (error) error.style.display = "block";
            return false;
        } else {
            if (error) error.style.display = "none";
            return true;
        }
    }

    form.addEventListener("submit", function (e) {
        let valid = true;

        // Validazione campi obbligatori
        valid &= validateField("nome", "errNome");
        valid &= validateField("cognome", "errCognome");
        valid &= validateField("dataNascita", "errData");
        valid &= validateField("sesso", "errSesso");
        valid &= validateField("comune", "errComune");

        // Validazione Codice Fiscale
        const cf = document.getElementById("codiceFiscale").value.trim();
        const regexCF = /^[A-Z0-9]{16}$/i;

        if (cf !== "" && !regexCF.test(cf)) {
            document.getElementById("errCF").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errCF").style.display = "none";
        }

        // Validazione Email
        const email = document.getElementById("email").value;
        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!regexEmail.test(email)) {
            document.getElementById("errEmail").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errEmail").style.display = "none";
        }

        // Validazione Password
        const pw = password ? password.value : "";
        const confermaPw = confermaPass ? confermaPass.value : "";

        const rules = checkPasswordRules(pw);
        if (!(rules.len && rules.upper && rules.digit && rules.special)) {
            const err = getById("errPassword");
            if (err) err.style.display = "block";
            valid = false;
        } else {
            const err = getById("errPassword");
            if (err) err.style.display = "none";
        }

        if (pw !== confermaPw) {
            const err = getById("errConfermaPassword");
            if (err) err.style.display = "block";
            valid = false;
        } else {
            const err = getById("errConfermaPassword");
            if (err) err.style.display = "none";
        }

        // Blocco invio se non valido
        if (!valid) {
            e.preventDefault();
        }
    });

    if (password) updatePwUi(password.value);
});