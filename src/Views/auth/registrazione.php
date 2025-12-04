<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title> Registrazione Utente - Biblioteca ITIS Rossi </title>
    <link rel="stylesheet" href="/StackMasters/public/assets/css/auth.css">
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">
</head>

<body>

<div class="container">
    <h2> Registrazione Utente </h2>

    <form id="registrationForm" action="registrazione" method="POST" novalidate>

        <?php if (isset($error)): ?>
            <div class="error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome *</label>
                <input type="text" id="nome" name="nome" value="<?= $_POST['nome'] ?? '' ?>" required>
                <div class="error" id="errNome">Inserisci il nome</div>
            </div>

            <div class="form-group">
                <label for="cognome">Cognome *</label>
                <input type="text" id="cognome" name="cognome" value="<?= $_POST['cognome'] ?? '' ?>" required>
                <div class="error" id="errCognome">Inserisci il cognome</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="dataNascita">Data di nascita *</label>
                <input type="date" id="dataNascita" name="dataNascita" min="1900-01-01" max="2025-12-31" value="<?= $_POST['dataNascita'] ?? '' ?>" required>
                <div class="error" id="errData">Inserisci una data valida</div>
            </div>

            <div class="form-group">
                <label for="sesso">Sesso *</label>
                <select id="sesso" name="sesso" required>
                    <option value=""> -- Seleziona --</option>
                    <option value="M" <?= (isset($_POST['sesso']) && $_POST['sesso'] === 'M') ? 'selected' : '' ?>>Maschio</option>
                    <option value="F" <?= (isset($_POST['sesso']) && $_POST['sesso'] === 'F') ? 'selected' : '' ?>>Femmina</option>
                </select>
                <div class="error" id="errSesso">Seleziona il sesso</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="comune">Comune di nascita *</label>
                <input type="text" id="comune" name="comune" value="<?= $_POST['comune'] ?? '' ?>" required>
                <div class="error" id="errComune">Inserisci il comune di nascita</div>
            </div>

            <div class="form-group">
                <label for="codiceFiscale">Codice Fiscale (opzionale)</label>
                <div id="calcolaCFdiv">
                    <input type="text" id="codiceFiscale" name="codiceFiscale" maxlength="16" value="<?= $_POST['codiceFiscale'] ?? '' ?>">
                    <button type="button" id="btnCalcolaCF">Calcola</button>
                </div>
                <div class="error" id="errCF">Formato Codice Fiscale non valido</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group full">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" value="<?= $_POST['email'] ?? '' ?>" required>
                <div class="error" id="errEmail">Inserisci una email valida</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Password *</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-pass" id="togglePassword">Mostra</button>
                </div>

                <div class="pw-requirements" id="pwHelp">
                    <div class="pw-item invalid" id="pwLen">8 caratteri minimi</div>
                    <div class="pw-item invalid" id="pwUpper">1 lettera maiuscola</div>
                    <div class="pw-item invalid" id="pwDigit">1 numero</div>
                    <div class="pw-item invalid" id="pwSpecial">1 simbolo</div>
                </div>

                <div class="error" id="errPassword">
                    La password non soddisfa i requisiti
                </div>
            </div>

            <div class="form-group">
                <label for="confermaPassword">Conferma password *</label>
                <input type="password" id="confermaPassword" name="confermaPassword" required>
                <div class="error" id="errConfermaPassword">
                    Le due password non coincidono
                </div>
            </div>
        </div>

        <button type="submit">Registrati</button>

    </form>
</div>

<script src="/StackMasters/public/assets/js/validation.js"></script>
<script src="/StackMasters/public/assets/js/calc_cf.js"></script>

</body>
</html>