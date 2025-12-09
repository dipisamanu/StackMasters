<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carica session.php - ora funziona!
require_once __DIR__ . '/../src/config/session.php';

// Recupera eventuali errori dalla sessione
$errors = $_SESSION['register_errors'] ?? [];
$oldData = $_SESSION['register_data'] ?? [];
unset($_SESSION['register_errors'], $_SESSION['register_data']);

// Recupera flash message se esiste
$flashMessage = null;
if (isset($_SESSION['flash'])) {
    $flashMessage = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registrazione Utente - Biblioteca ITIS Rossi</title>
    <link rel="icon" href="/StackMasters/public/assets/img/itisrossi.png">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: rgba(202, 201, 201, 0.97);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 1000px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
            font-size: 24px;
            font-weight: 700;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 250px;
        }

        .form-group.full {
            flex: 100%;
        }

        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
            font-size: 0.9rem;
        }

        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            transition: all 0.2s ease;
            background-color: #fff;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #bf2121;
            box-shadow: 0 0 0 3px rgba(191, 33, 33, 0.1);
        }

        input.is-valid {
            border-color: #28a745;
        }

        input.is-valid:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        }

        input.is-invalid {
            border-color: #dc3545;
        }

        input.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2);
        }

        button {
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            margin-top: 20px;
            font-size: 16px;
            font-weight: bold;
            background: #bf2121;
            color: white;
            border: none;
            border-radius: 6px;
        }

        button[type="submit"]:hover {
            background: #931b1b;
        }

        button[type="submit"]:active {
            transform: scale(0.98);
        }

        #calcolaCFdiv {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #calcolaCFdiv input {
            flex: 1;
        }

        .cf-counter {
            font-size: 0.8rem;
            color: #666;
            white-space: nowrap;
            padding: 0 5px;
        }

        #btnCalcolaCF {
            padding: 10px 16px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            color: #333;
            border-radius: 6px;
            font-weight: 600;
            white-space: nowrap;
        }

        #btnCalcolaCF:hover {
            background-color: #e0e0e0;
            border-color: #bbb;
        }

        #btnCalcolaCF:disabled {
            background-color: #f5f5f5;
            color: #aaa;
            cursor: not-allowed;
            border-color: #ddd;
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            padding-right: 70px;
        }

        .toggle-pass {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #666;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 8px;
            cursor: pointer;
        }

        .toggle-pass:hover {
            color: #bf2121;
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
        }

        .pw-requirements {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pw-item {
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 12px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            color: #666;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-align: center;
        }

        .pw-item.invalid {
            border-color: #ffcccc;
            background-color: #fff5f5;
            color: #cc0000;
        }

        .pw-item.valid {
            border-color: #c3e6cb;
            background-color: #d4edda;
            color: #155724;
        }

        .error {
            font-size: 0.85rem;
            color: #dc3545;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert ul {
            margin: 8px 0 0 20px;
            padding: 0;
        }

    </style>
</head>

<body>

<div class="container">
    <h2>Registrazione Utente</h2>

    <form id="registrationForm" action="process-register.php" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Attenzione:</strong>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($flashMessage): ?>
            <div class="alert alert-<?= $flashMessage['type'] === 'error' ? 'danger' : 'success' ?>">
                <?= htmlspecialchars($flashMessage['message']) ?>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome *</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($oldData['nome'] ?? '') ?>" required>
                <div class="error" id="errNome">Inserisci il nome</div>
            </div>

            <div class="form-group">
                <label for="cognome">Cognome *</label>
                <input type="text" id="cognome" name="cognome" value="<?= htmlspecialchars($oldData['cognome'] ?? '') ?>" required>
                <div class="error" id="errCognome">Inserisci il cognome</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="dataNascita">Data di nascita *</label>
                <input type="date" id="dataNascita" name="dataNascita" min="1900-01-01" max="2025-12-31" value="<?= htmlspecialchars($oldData['dataNascita'] ?? '') ?>" required>
                <div class="error" id="errData">Inserisci una data valida</div>
            </div>

            <div class="form-group">
                <label for="sesso">Sesso *</label>
                <select id="sesso" name="sesso" required>
                    <option value="">-- Seleziona --</option>
                    <option value="M" <?= (isset($oldData['sesso']) && $oldData['sesso'] === 'M') ? 'selected' : '' ?>>Maschio</option>
                    <option value="F" <?= (isset($oldData['sesso']) && $oldData['sesso'] === 'F') ? 'selected' : '' ?>>Femmina</option>
                </select>
                <div class="error" id="errSesso">Seleziona il sesso</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="comune">Comune di nascita *</label>
                <input type="text" id="comune" name="comune" value="<?= htmlspecialchars($oldData['comune'] ?? '') ?>" required>
                <div class="error" id="errComune">Inserisci il comune di nascita</div>
            </div>

            <div class="form-group">
                <label for="codiceFiscale">Codice Fiscale *</label>
                <div id="calcolaCFdiv">
                    <input type="text" id="codiceFiscale" name="codiceFiscale" maxlength="16" value="<?= htmlspecialchars($oldData['codiceFiscale'] ?? '') ?>">
                    <span id="cfCounter" class="cf-counter">0/16</span>
                    <button type="button" id="btnCalcolaCF">Calcola</button>
                </div>
                <div class="error" id="errCF">Formato Codice Fiscale non valido</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group full">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($oldData['email'] ?? '') ?>" required>
                <div class="error" id="errEmail">Inserisci una email valida</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Password *</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-pass" id="togglePassword" tabindex="-1">Mostra</button>
                </div>

                <div class="pw-requirements" id="pwHelp">
                    <div class="pw-item invalid" id="pwLen">8 caratteri</div>
                    <div class="pw-item invalid" id="pwUpper">1 maiuscola</div>
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

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const form = document.getElementById("registrationForm");
        if (!form) return;

        const ui = {
            pass: document.getElementById("password"),
            conf: document.getElementById("confermaPassword"),
            toggle: document.getElementById("togglePassword")
        };

        const fieldsConfig = [
            { id: "nome", err: "errNome" },
            { id: "cognome", err: "errCognome" },
            { id: "sesso", err: "errSesso" },
            { id: "comune", err: "errComune" },
            {
                id: "dataNascita",
                err: "errData",
                check: function(val) {
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
                check: function(val) {
                    return val !== "" && /^[A-Z0-9]{16}$/i.test(val);
                }
            },
            {
                id: "email",
                err: "errEmail",
                check: function(val) {
                    return val !== "" && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
                }
            }
        ];

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

            toggleError("errPassword", false);

            if (ui.conf && ui.conf.value !== "") {
                toggleError("errConfermaPassword", val !== ui.conf.value);
            }
        }

        function validateForm(e) {
            let isValid = true;

            fieldsConfig.forEach(field => {
                const el = document.getElementById(field.id);
                const val = el ? el.value.trim() : "";
                let fieldOk = true;

                if (field.check) {
                    fieldOk = field.check(val);
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

        if (ui.pass) {
            ui.pass.addEventListener("input", updatePasswordUI);
            updatePasswordUI();
        }

        if (ui.conf) {
            ui.conf.addEventListener("input", () => {
                if (ui.pass) {
                    toggleError("errConfermaPassword", ui.pass.value !== ui.conf.value);
                }
            });
        }

        if (ui.toggle && ui.pass) {
            ui.toggle.addEventListener("click", () => {
                const isPassword = ui.pass.type === "password";
                ui.pass.type = isPassword ? "text" : "password";
                ui.toggle.textContent = isPassword ? "Nascondi" : "Mostra";
            });
        }

        form.addEventListener("submit", validateForm);
    });

    // CODICE FISCALE
    let elencoComuni = [];
    const CF_LEN = 16;

    document.addEventListener("DOMContentLoaded", () => {
        const ui = {
            btn: document.getElementById("btnCalcolaCF"),
            input: document.getElementById("codiceFiscale"),
            counter: document.getElementById("cfCounter"),
            errCF: document.getElementById("errCF"),
            errComune: document.getElementById("errComune"),
            fields: {
                nome: document.getElementById("nome"),
                cognome: document.getElementById("cognome"),
                nascita: document.getElementById("dataNascita"),
                sesso: document.getElementById("sesso"),
                comune: document.getElementById("comune")
            }
        };

        function toggleError(el, msg = "") {
            if (!el) return;
            el.style.display = msg ? "block" : "none";
            el.textContent = msg;
        }

        function setStatus(isValid) {
            ui.input.classList.toggle('is-valid', isValid);
            ui.input.classList.toggle('is-invalid', !isValid);
        }

        function resetUI() {
            ui.input.classList.remove('is-valid', 'is-invalid');
            toggleError(ui.errCF, "");
            toggleError(ui.errComune, "");

            const len = ui.input.value.length;
            if (ui.counter) ui.counter.textContent = `${len}/${CF_LEN}`;

            if (len === 0) {
                ui.btn.textContent = "Calcola";
                ui.btn.disabled = false;
            } else {
                ui.btn.textContent = "Verifica";
                ui.btn.disabled = len !== CF_LEN;
            }
        }

        function processaCF() {
            const dati = {};
            let formValido = true;

            for (let key in ui.fields) {
                const val = ui.fields[key].value.toUpperCase().trim();
                dati[key] = val;
                if (!val) formValido = false;
            }

            if (!formValido) {
                toggleError(ui.errCF, "Compila tutti i dati anagrafici prima di calcolare/verificare il CF.");
                ui.input.classList.add('is-invalid');
                return;
            }

            const cfGenerato = generaCodiceFiscale(dati, ui.errComune);

            if (!cfGenerato) {
                ui.input.classList.add('is-invalid');
                return;
            }

            const cfInput = ui.input.value;

            if (cfInput.length === 0) {
                ui.input.value = cfGenerato;
                setStatus(true);
                toggleError(ui.errCF, "");
                resetUI();
                ui.input.classList.add('is-valid');
            } else {
                if (cfInput === cfGenerato) {
                    setStatus(true);
                    toggleError(ui.errCF, "");
                } else {
                    setStatus(false);
                    const msgErrore = analizzaIncongruenzaCF(cfInput, cfGenerato);
                    toggleError(ui.errCF, msgErrore);
                }
            }
        }

        if (ui.input) {
            ui.input.addEventListener("input", () => {
                ui.input.value = ui.input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                resetUI();
            });
        }

        Object.values(ui.fields).forEach(field => {
            if (field) {
                field.addEventListener("input", resetUI);
                field.addEventListener("change", resetUI);
            }
        });

        if (ui.btn) {
            ui.btn.disabled = true;
            ui.btn.textContent = "Caricamento...";

            fetch('/StackMasters/public/assets/data/comuni.json')
                .then(r => r.ok ? r.json() : Promise.reject("Errore"))
                .then(data => {
                    elencoComuni = data;
                    console.log(`Caricati ${data.length} comuni.`);
                    resetUI();
                })
                .catch(e => {
                    console.error(e);
                    ui.btn.textContent = "Errore DB";
                    toggleError(ui.errCF, "Errore caricamento database comuni.");
                });

            ui.btn.addEventListener("click", processaCF);
        }

        resetUI();
    });

    function analizzaIncongruenzaCF(inserito, atteso) {
        if (inserito.length !== 16) return `Lunghezza errata: ${inserito.length} caratteri invece di 16.`;
        const segComune = inserito.substring(11, 15);
        const attComune = atteso.substring(11, 15);
        if (segComune !== attComune) return "Il comune di nascita non corrisponde.";
        return "Dati non corrispondenti al CF inserito.";
    }

    function generaCodiceFiscale(dati, errEl) {
        if (errEl) errEl.style.display = "none";
        const comuneTrovato = elencoComuni.find(c => c.nome.toUpperCase() === dati.comune);
        if (!comuneTrovato) {
            if (errEl) {
                errEl.textContent = `Comune "${dati.comune}" non trovato.`;
                errEl.style.display = "block";
            }
            return null;
        }
        let cf = getCodice(dati.cognome, false) + getCodice(dati.nome, true) +
            getDataSesso(dati.nascita, dati.sesso) + comuneTrovato.codiceCatastale;
        return cf + calcolaCin(cf);
    }

    function getVocCons(str) {
        return { v: str.replace(/[^AEIOU]/g, ''), c: str.replace(/[^B-DF-HJ-NP-TV-Z]/g, '') };
    }

    function getCodice(str, isNome) {
        const { v, c } = getVocCons(str);
        if (isNome && c.length >= 4) return c[0] + c[2] + c[3];
        return (c + v + "XXX").substring(0, 3);
    }

    function getDataSesso(iso, sesso) {
        const [anno, mese, giorno] = iso.split('-');
        const codMesi = "ABCDEHLMPRST";
        const gg = parseInt(giorno) + (sesso === 'F' ? 40 : 0);
        return anno.substr(2) + codMesi[parseInt(mese) - 1] + (gg < 10 ? '0' + gg : gg);
    }

    function calcolaCin(cf) {
        const values = {
            0:1,1:0,2:5,3:7,4:9,5:13,6:15,7:17,8:19,9:21,
            A:1,B:0,C:5,D:7,E:9,F:13,G:15,H:17,I:19,J:21,
            K:2,L:4,M:18,N:20,O:11,P:3,Q:6,R:8,S:12,T:14,
            U:16,V:10,W:22,X:25,Y:24,Z:23
        };
        let sum = 0;
        for (let i = 0; i < 15; i++) {
            const c = cf[i];
            sum += (i % 2 === 0) ? values[c] : ((c >= '0' && c <= '9') ? parseInt(c) : c.charCodeAt(0) - 65);
        }
        return String.fromCharCode((sum % 26) + 65);
    }
</script>

</body>
</html>