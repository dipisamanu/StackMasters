<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registrazione Utente - Biblioteca ITIS Rossi</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 450px;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            margin-top: 10px;
            display: block;
        }

        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .error {
            font-size: 0.9em;
            color: red;
            display: none;
        }

        button {
            width: 100%;
            padding: 10px;
            margin-top: 20px;
            font-size: 16px;
            background: #0077cc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover { background: #005fa3; }
    </style>
</head>

<body>

<div class="container">
    <h2>Registrazione Utente</h2>

    <form id="registrationForm" novalidate>

        <label>Nome *</label>
        <input type="text" id="nome" required>
        <div class="error" id="errNome">Inserisci il nome</div>

        <label>Cognome *</label>
        <input type="text" id="cognome" required>
        <div class="error" id="errCognome">Inserisci il cognome</div>

        <label>Data di nascita *</label>
        <input type="date" id="dataNascita" required>
        <div class="error" id="errData">Inserisci una data valida</div>

        <label>Sesso *</label>
        <select id="sesso" required>
            <option value="">-- Seleziona --</option>
            <option value="M">Maschio</option>
            <option value="F">Femmina</option>
        </select>
        <div class="error" id="errSesso">Seleziona il sesso</div>

        <label>Comune di nascita *</label>
        <input type="text" id="comune" required>
        <div class="error" id="errComune">Inserisci il comune di nascita</div>

        <label>Codice Fiscale (opzionale)</label>
        <input type="text" id="codiceFiscale" maxlength="16">
        <div class="error" id="errCF">Formato Codice Fiscale non valido</div>

        <label>Email *</label>
        <input type="email" id="email" required>
        <div class="error" id="errEmail">Inserisci una email valida</div>

        <label>Password *</label>
        <input type="password" id="password" required>
        <div class="error" id="errPassword">
            La password deve contenere almeno:
            <br>• 8 caratteri
            <br>• 1 maiuscola
            <br>• 1 numero
            <br>• 1 simbolo
        </div>

        <button type="submit">Registrati</button>

    </form>
</div>

<script>
    const form = document.getElementById("registrationForm");

    form.addEventListener("submit", function (e) {
        e.preventDefault(); // evita invio se ci sono errori

        let valid = true;

        // VALIDAZIONE NOME
        if (document.getElementById("nome").value.trim() === "") {
            document.getElementById("errNome").style.display = "block";
            valid = false;
        } else document.getElementById("errNome").style.display = "none";

        // VALIDAZIONE COGNOME
        if (document.getElementById("cognome").value.trim() === "") {
            document.getElementById("errCognome").style.display = "block";
            valid = false;
        } else document.getElementById("errCognome").style.display = "none";

        // DATA DI NASCITA
        const data = document.getElementById("dataNascita").value;
        if (!data) {
            document.getElementById("errData").style.display = "block";
            valid = false;
        } else document.getElementById("errData").style.display = "none";

        // SESSO
        if (document.getElementById("sesso").value === "") {
            document.getElementById("errSesso").style.display = "block";
            valid = false;
        } else document.getElementById("errSesso").style.display = "none";

        // COMUNE
        if (document.getElementById("comune").value.trim() === "") {
            document.getElementById("errComune").style.display = "block";
            valid = false;
        } else document.getElementById("errComune").style.display = "none";

        // CODICE FISCALE
        const cf = document.getElementById("codiceFiscale").value.trim();
        const regexCF = /^[A-Z0-9]{16}$/i;

        if (cf !== "" && !regexCF.test(cf)) {
            document.getElementById("errCF").style.display = "block";
            valid = false;
        } else document.getElementById("errCF").style.display = "none";

        // EMAIL
        const email = document.getElementById("email").value;
        const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!regexEmail.test(email)) {
            document.getElementById("errEmail").style.display = "block";
            valid = false;
        } else document.getElementById("errEmail").style.display = "none";

        // regex
        const password = document.getElementById("password").value;
        const regexPass = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

        if (!regexPass.test(password)) {
            document.getElementById("errPassword").style.display = "block";
            valid = false;
        } else document.getElementById("errPassword").style.display = "none";

        if (valid) {
            alert("Validazione superata");
        }
    });
</script>

</body>
</html>
