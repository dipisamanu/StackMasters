<?php
/**
 * Pagina di Registrazione Moderna
 * File: public/register.php
 */

require_once __DIR__ . '/../src/config/session.php';

// Se già loggato, redirect
if (Session::isLoggedIn()) {
    header('Location: home.php');
    exit;
}

// Recupera dati sessione
$errors = $_SESSION['register_errors'] ?? [];
$oldData = $_SESSION['register_data'] ?? [];
$flash = $_SESSION['flash'] ?? null;

unset($_SESSION['register_errors'], $_SESSION['register_data'], $_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - BiblioSystem</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-register {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            overflow: hidden;
            background: white;
            width: 100%;
            max-width: 1100px;
        }

        .register-sidebar {
            background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            position: relative;
            overflow: hidden;
        }

        .register-sidebar::before {
            content: '';
            position: absolute;
            top: -50px; right: -50px;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .register-sidebar::after {
            content: '';
            position: absolute;
            bottom: -50px; left: -50px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #0d6efd;
            font-weight: 600;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #dee2e6;
            padding-left: 1rem;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .btn-register {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .btn-register:hover,
        .btn-register:focus,
        .btn-register:active {
            background-color: #0b5ed7;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
            outline: none;
        }

        .password-requirements {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
            scrollbar-width: none;
        }

        .password-requirements::-webkit-scrollbar {
            display: none;
        }

        .password-requirements span {
            font-size: 0.8rem;
            color: #adb5bd;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }

        .password-requirements span.valid { color: #198754; font-weight: 600; }
        .password-requirements span i { font-size: 0.65rem; margin-right: 4px; }

        #btnCalcolaCF {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
            height: 58px;
        }

        .input-group-text {
            height: 58px;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="card card-register mx-auto">
        <div class="row g-0">

            <div class="col-lg-4 d-none d-lg-flex register-sidebar">
                <div class="position-relative z-1 text-center">
                    <div class="mb-4">
                        <i class="fas fa-book-reader fa-4x mb-3"></i>
                        <h2 class="fw-bold">BiblioSystem</h2>
                    </div>
                    <p class="opacity-75 fs-5">La tua biblioteca digitale, a portata di click.</p>
                    <hr class="border-light opacity-25 my-4">
                    <p class="small opacity-75">Unisciti a noi per accedere a migliaia di risorse, prenotare libri e gestire i tuoi prestiti online.</p>
                </div>
            </div>

            <div class="col-lg-8 bg-white p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-dark m-0">Crea Account</h3>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Home</a>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> shadow-sm border-0 rounded-3 mb-4">
                        <i class="fas <?= $flash['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> me-2"></i>
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="registrationForm" action="process-register.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="nome" name="nome" placeholder="Mario" value="<?= htmlspecialchars($oldData['nome'] ?? '') ?>" required>
                                <label for="nome">Nome</label>
                                <div class="invalid-feedback">Inserisci il nome.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="cognome" name="cognome" placeholder="Rossi" value="<?= htmlspecialchars($oldData['cognome'] ?? '') ?>" required>
                                <label for="cognome">Cognome</label>
                                <div class="invalid-feedback">Inserisci il cognome.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="dataNascita" name="dataNascita" min="1920-01-01" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($oldData['dataNascita'] ?? '') ?>" required>
                                <label for="dataNascita">Data di Nascita</label>
                                <div class="invalid-feedback">Data non valida.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="sesso" name="sesso" required>
                                    <option value="" disabled <?= !isset($oldData['sesso']) ? 'selected' : '' ?>>Seleziona</option>
                                    <option value="M" <?= ($oldData['sesso'] ?? '') === 'M' ? 'selected' : '' ?>>Maschio</option>
                                    <option value="F" <?= ($oldData['sesso'] ?? '') === 'F' ? 'selected' : '' ?>>Femmina</option>
                                </select>
                                <label for="sesso">Sesso</label>
                                <div class="invalid-feedback">Seleziona il sesso.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="comune" name="comune" placeholder="Roma" value="<?= htmlspecialchars($oldData['comune'] ?? '') ?>" required>
                                <label for="comune">Comune di Nascita</label>
                                <div class="invalid-feedback" id="errComune">Inserisci il comune.</div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="input-group has-validation">
                                <div class="form-floating flex-grow-1">
                                    <input type="text" class="form-control rounded-0 rounded-start" id="codiceFiscale" name="codiceFiscale" placeholder="CF" maxlength="16" value="<?= htmlspecialchars($oldData['codiceFiscale'] ?? '') ?>" style="border-right:0;" required>
                                    <label for="codiceFiscale">Codice Fiscale</label>
                                    <div class="invalid-feedback" id="errCF">Inserisci il codice fiscale.</div>
                                </div>
                                <button class="btn btn-light border" type="button" id="btnCalcolaCF" style="min-width: 80px;">
                                    <i class="fas fa-magic text-primary"></i> <span class="d-none d-sm-inline">Calcola</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?= htmlspecialchars($oldData['email'] ?? '') ?>" required>
                        <label for="email">Indirizzo Email</label>
                        <div class="invalid-feedback">Inserisci un'email valida.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="input-group has-validation">
                                <div class="form-floating flex-grow-1">
                                    <input type="password" class="form-control border-end-0 rounded-0 rounded-start" id="password" name="password" placeholder="Password" required>
                                    <label for="password">Password</label>
                                    <div class="invalid-feedback">Password richiesta.</div>
                                </div>
                                <span class="input-group-text bg-white border-start-0" style="cursor: pointer;" onclick="togglePass('password', this)">
                                    <i class="far fa-eye text-muted"></i>
                                </span>
                            </div>
                            <div class="password-requirements mt-2 d-flex">
                                <span id="req-len"><i class="fas fa-circle"></i> 8 caratteri</span>
                                <span id="req-upper"><i class="fas fa-circle"></i> Maiuscola</span>
                                <span id="req-num"><i class="fas fa-circle"></i> Numero</span>
                                <span id="req-spec"><i class="fas fa-circle"></i> Speciale</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group has-validation">
                                <div class="form-floating flex-grow-1">
                                    <input type="password" class="form-control border-end-0 rounded-0 rounded-start" id="confermaPassword" name="confermaPassword" placeholder="Conferma" required>
                                    <label for="confermaPassword">Conferma Password</label>
                                    <div class="invalid-feedback">Le password non coincidono.</div>
                                </div>
                                <span class="input-group-text bg-white border-start-0" style="cursor: pointer;" onclick="togglePass('confermaPassword', this)">
                                    <i class="far fa-eye text-muted"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-register w-100 text-white shadow-sm mb-3">
                        REGISTRATI <i class="fas fa-arrow-right ms-2"></i>
                    </button>

                    <p class="text-center text-muted mb-0">
                        Hai già un account? <a href="login.php" class="text-primary fw-bold text-decoration-none">Accedi qui</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle Password Visibility
    function togglePass(inputId, iconSpan) {
        const input = document.getElementById(inputId);
        const icon = iconSpan.querySelector('i');
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const form = document.getElementById("registrationForm");
        const passInput = document.getElementById("password");
        const confirmInput = document.getElementById("confermaPassword");

        // Array Comuni
        let comuniDB = [];

        // Validazione Password Live
        passInput.addEventListener("input", function() {
            const val = this.value;
            const reqs = {
                'req-len': val.length >= 8,
                'req-upper': /[A-Z]/.test(val),
                'req-num': /[0-9]/.test(val),
                'req-spec': /[\W_]/.test(val)
            };

            for (const [id, isValid] of Object.entries(reqs)) {
                const el = document.getElementById(id);
                const icon = el.querySelector('i');
                if (isValid) {
                    el.classList.add('valid');
                    icon.classList.remove('fa-circle');
                    icon.classList.add('fa-check-circle');
                } else {
                    el.classList.remove('valid');
                    icon.classList.add('fa-circle');
                    icon.classList.remove('fa-check-circle');
                }
            }

            if(confirmInput.value) checkMatch();
        });

        confirmInput.addEventListener("input", checkMatch);

        function checkMatch() {
            if (confirmInput.value !== passInput.value) {
                confirmInput.setCustomValidity("Le password non coincidono");
                confirmInput.classList.add('is-invalid');
                confirmInput.classList.remove('is-valid');
            } else {
                confirmInput.setCustomValidity("");
                confirmInput.classList.remove('is-invalid');
                confirmInput.classList.add('is-valid');
            }
        }

        // Caricamento Comuni JSON
        fetch('/StackMasters/public/assets/data/comuni.json')
            .then(r => r.json())
            .then(data => { comuniDB = data; })
            .catch(err => console.error("Errore comuni:", err));

        // Calcolo Codice Fiscale
        document.getElementById('btnCalcolaCF').addEventListener('click', function() {
            const inputs = {
                nome: document.getElementById('nome').value.trim().toUpperCase(),
                cognome: document.getElementById('cognome').value.trim().toUpperCase(),
                data: document.getElementById('dataNascita').value,
                sesso: document.getElementById('sesso').value,
                comune: document.getElementById('comune').value.trim().toUpperCase()
            };

            // Reset errori
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Validazione campi base
            if (!inputs.nome || !inputs.cognome || !inputs.data || !inputs.sesso || !inputs.comune) {
                alert("Compila tutti i dati anagrafici prima di calcolare.");
                return;
            }

            // Cerca Comune
            const comuneTrovato = comuniDB.find(c => c.nome.toUpperCase() === inputs.comune);
            if (!comuneTrovato) {
                const comEl = document.getElementById('comune');
                comEl.classList.add('is-invalid');
                document.getElementById('errComune').innerText = "Comune non trovato nel database.";
                return;
            }

            // Generazione
            const cf = calcolaCF(inputs.nome, inputs.cognome, inputs.data, inputs.sesso, comuneTrovato.codiceCatastale);
            const cfInput = document.getElementById('codiceFiscale');
            cfInput.value = cf;
            cfInput.classList.add('is-valid'); // Feedback verde
        });

        // Funzioni Helper CF Minimizzate
        function calcolaCF(nome, cognome, data, sesso, codCat) {
            const voc = str => str.replace(/[^AEIOU]/g, '');
            const cons = str => str.replace(/[^B-DF-HJ-NP-TV-Z]/g, '');

            const getCod = (str, isNome) => {
                const c = cons(str), v = voc(str);
                const t = c + v + 'XXX';
                if (isNome && c.length >= 4) return c[0] + c[2] + c[3];
                return t.substring(0, 3);
            };

            const [Y, M, D] = data.split('-');
            const mesi = 'ABCDEHLMPRST';
            const gg = parseInt(D) + (sesso === 'F' ? 40 : 0);

            let tempCF = getCod(cognome, false) + getCod(nome, true) +
                Y.substring(2) + mesi[parseInt(M)-1] + (gg < 10 ? '0'+gg : gg) + codCat;

            // Calcolo CIN
            const pari = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const dispari = {0:1,1:0,2:5,3:7,4:9,5:13,6:15,7:17,8:19,9:21,A:1,B:0,C:5,D:7,E:9,F:13,G:15,H:17,I:19,J:21,K:2,L:4,M:18,N:20,O:11,P:3,Q:6,R:8,S:12,T:14,U:16,V:10,W:22,X:25,Y:24,Z:23};
            let s = 0;
            for(let i=0; i<15; i++) {
                let char = tempCF[i];
                if((i+1)%2 === 0) s += pari.indexOf(char); // Pari (0-based index su stringa è dispari)
                else s += dispari[(!isNaN(char) ? parseInt(char) : char)];
            }
            return tempCF + String.fromCharCode(65 + (s % 26));
        }

        // Validazione Form al Submit
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
</script>

</body>
</html>