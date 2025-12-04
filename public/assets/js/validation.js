document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registrationForm");

    // Se il form non esiste in questa pagina, esce senza errori
    if (!form) return;

    form.addEventListener("submit", function (e) {
        let valid = true;

        // Validazione Nome
        if (document.getElementById("nome").value.trim() === "") {
            document.getElementById("errNome").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errNome").style.display = "none";
        }

        // Validazione Cognome
        if (document.getElementById("cognome").value.trim() === "") {
            document.getElementById("errCognome").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errCognome").style.display = "none";
        }

        // Validazione Data
        const data = document.getElementById("dataNascita").value;
        if (!data) {
            document.getElementById("errData").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errData").style.display = "none";
        }

        // Validazione Sesso
        if (document.getElementById("sesso").value === "") {
            document.getElementById("errSesso").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errSesso").style.display = "none";
        }

        // Validazione Comune (nota: nel form completo avevamo messo id="cittaNascita", controlla che corrisponda)
        // Se nel tuo HTML l'ID è "comune", usa questo blocco:
        const comuneInput = document.getElementById("comune") || document.getElementById("cittaNascita");
        const errComuneDiv = document.getElementById("errComune") || document.getElementById("errCittaNascita");

        if (comuneInput && comuneInput.value.trim() === "") {
            if(errComuneDiv) errComuneDiv.style.display = "block";
            valid = false;
        } else if (errComuneDiv) {
            errComuneDiv.style.display = "none";
        }

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

        const password = document.getElementById("password").value;
        const confermaPassword = document.getElementById("confermaPassword").value;

        // Controllo Complessità Password
        const regexPass = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

        if (!regexPass.test(password)) {
            document.getElementById("errPassword").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errPassword").style.display = "none";
        }

        // Controllo Corrispondenza Password
        if (password !== confermaPassword) {
            document.getElementById("errConfermaPassword").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errConfermaPassword").style.display = "none";
        }

        // Blocco invio se non valido
        if (!valid) {
            e.preventDefault();
        }
    });
});