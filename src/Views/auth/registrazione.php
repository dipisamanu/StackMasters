<?php

echo $_POST['nome'];
echo $_POST['cognome'];
echo $_POST['dataNascita'];
echo $_POST['sesso'];
echo $_POST['comune'];
echo $_POST['codiceFiscale'];
echo $_POST['email'];
echo $_POST['password'];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registrazione Utente - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
    <link rel="stylesheet" href="/StackMasters/public/assets/css/auth.css">
</head>

<body>

<div class="container">
    <h2>Registrazione Utente</h2>

    <form id="registrationForm" action="registrazione" method="POST" novalidate>
<!--    convalidate serve a gestire autonomamente i required-->
        <?php if (isset($error)): ?>
            <div class="error" style="display:block; text-align:center; margin-bottom:15px;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <label for="nome">Nome *</label>
        <input type="text" id="nome" name="nome" value="<?= $_POST['nome'] ?? '' ?>" required>
        <div class="error" id="errNome">Inserisci il nome</div>

        <!-- facendo post ?? sto facendo un salvataggio della vecchia variabile
         nel caso in cui aggiorni la pagina ma non in caso di una get. null coalescing operator -->

        <label for="cognome">Cognome *</label>
        <input type="text" id="cognome" name="cognome" value="<?= $_POST['cognome'] ?? '' ?>" required>
        <div class="error" id="errCognome">Inserisci il cognome</div>

        <label for="dataNascita">Data di nascita *</label>
        <input type="date" id="dataNascita" name="dataNascita" value="<?= $_POST['dataNascita'] ?? '' ?>" required>
        <div class="error" id="errData">Inserisci una data valida</div>

        <label for="sesso">Sesso *</label>
        <select id="sesso" name="sesso" required>
            <option value="">-- Seleziona --</option>
            <option value="M" <?= (isset($_POST['sesso']) && $_POST['sesso'] === 'M') ? 'selected' : '' ?>>Maschio</option>
            <option value="F" <?= (isset($_POST['sesso']) && $_POST['sesso'] === 'F') ? 'selected' : '' ?>>Femmina</option>
            <option value="X" <?= (isset($_POST['sesso']) && $_POST['sesso'] === 'X') ? 'selected' : '' ?>>Non Binario</option>
        </select>
        <div class="error" id="errSesso">Seleziona il sesso</div>

        <label for="comune">Comune di nascita *</label>
        <input type="text" id="comune" name="comune" value="<?= $_POST['comune'] ?? '' ?>" required>
        <div class="error" id="errComune">Inserisci il comune di nascita</div>

        <label for="codiceFiscale">Codice Fiscale (opzionale)</label>
        <input type="text" id="codiceFiscale" name="codiceFiscale" maxlength="16" value="<?= $_POST['codiceFiscale'] ?? '' ?>">
        <div class="error" id="errCF">Formato Codice Fiscale non valido</div>

        <label for="email">Email *</label>
        <input type="email" id="email" name="email" value="<?= $_POST['email'] ?? '' ?>" required>
        <div class="error" id="errEmail">Inserisci una email valida</div>

        <label for="password">Password *</label>
        <input type="password" id="password" name="password" required>
        <div class="error" id="errPassword">
            La password deve contenere almeno:<br>
            • 8 caratteri<br>
            • 1 maiuscola<br>
            • 1 numero<br>
            • 1 simbolo
        </div>
        <button type="submit">Registrati</button>

    </form>
</div>

<script src="/StackMasters/public/assets/js/validation.js"></script>

</body>
</html>