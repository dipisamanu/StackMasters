document.addEventListener("DOMContentLoaded",()=> {
    const form = document.getElementById("registrationForm");

    // Se il form non esiste in questa pagina, esce senza errori
    if (!form) return;

    form.addEventListener("submit", function (e) {
        let valid = true;

        if (document.getElementById("nome").value.trim() === "") {
            document.getElementById("errNome").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errNome").style.display = "none";
        }

        if (document.getElementById("cognome").value.trim() === "") {
            document.getElementById("errCognome").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errCognome").style.display = "none";
        }

        const data = document.getElementById("dataNascita").value;
        if (!data) {
            document.getElementById("errData").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errData").style.display = "none";
        }

        if (document.getElementById("sesso").value === "") {
            document.getElementById("errSesso").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errSesso").style.display = "none";
        }

        if (document.getElementById("comune").value.trim() === "") {
            document.getElementById("errComune").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errComune").style.display = "none";
        }

        const cf = document.getElementById("codiceFiscale").value.trim();
        const regexCF = /^[A-Z0-9]{16}$/i;

        if (cf !== "" && !regexCF.test(cf)) {
            document.getElementById("errCF").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errCF").style.display = "none";
        }

        const email = document.getElementById("email").value;
        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!regexEmail.test(email)) {
            document.getElementById("errEmail").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errEmail").style.display = "none";
        }

        const password = document.getElementById("password").value;
        const regexPass = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

        if (!regexPass.test(password)) {
            document.getElementById("errPassword").style.display = "block";
            valid = false;
        } else {
            document.getElementById("errPassword").style.display = "none";
        }

        if (!valid) {
            e.preventDefault(); // Blocca l'invio SOLO se ci sono errori
        }
        // Se valid è true, lo script finisce e il browser invia il form a PHP normalmente
    });
});