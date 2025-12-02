<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registrazione Utente - Biblioteca ITIS Rossi</title>

    <link rel="stylesheet" href="/StackMasters/public/assets/css/auth.css">
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
            <option value="X">Non Binario</option>
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

<script src="/StackMasters/public/assets/js/validation.js"></script>

</body>
</html>
