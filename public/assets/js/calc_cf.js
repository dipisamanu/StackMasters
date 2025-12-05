let elencoComuni = [];
const CF_LEN = 16;

document.addEventListener("DOMContentLoaded", () => {
    // Caching degli elementi DOM
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

    // Helpers UI
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
        // Resetta lo stato visivo (toglie verde/rosso)
        ui.input.classList.remove('is-valid', 'is-invalid');
        toggleError(ui.errCF, "");
        toggleError(ui.errComune, "");

        const len = ui.input.value.length;
        if (ui.counter) ui.counter.textContent = `${len}/${CF_LEN}`;

        // Gestione stato bottone
        if (len === 0) {
            ui.btn.textContent = "Calcola";
            ui.btn.disabled = false;
        } else {
            ui.btn.textContent = "Verifica";
            ui.btn.disabled = len !== CF_LEN;
            // Abilita solo se 16 caratteri
        }
    }

    function processaCF() {
        const dati = {};
        let formValido = true;

        // Raccolta dati
        for (let key in ui.fields) {
            const val = ui.fields[key].value.toUpperCase().trim();
            dati[key] = val;
            if (!val) formValido = false;
        }

        if (!formValido) {
            toggleError(ui.errCF, "Compila tutti i dati anagrafici (Nome, Cognome, Data, Sesso, Comune).");
            ui.input.classList.add('is-invalid');
            return;
        }

        // Calcolo del CF atteso in base ai dati inseriti
        const cfGenerato = generaCodiceFiscale(dati, ui.errComune);

        if (!cfGenerato) {
            ui.input.classList.add('is-invalid');
            return;
        }

        const cfInput = ui.input.value;

        if (cfInput.length === 0) {
            // Caso 1: calcolo
            ui.input.value = cfGenerato;
            setStatus(true);
            toggleError(ui.errCF, "");
            // Aggiorniamo contatore e bottone dopo il riempimento
            resetUI();
            ui.input.classList.add('is-valid'); // Forziamo valid perché resetUI lo pulisce
        } else {
            // Caso 2: verifica
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

    // 1. Input sul campo CF
    if (ui.input) {
        ui.input.addEventListener("input", () => {
            ui.input.value = ui.input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            resetUI();
        });
    }

    // Se modifico nome, cognome ecc..., resetto lo stato di validazione del CF
    // così l'utente può premere nuovamente "Verifica" o "Calcola".
    Object.values(ui.fields).forEach(field => {
        if (field) {
            field.addEventListener("input", resetUI);
            field.addEventListener("change", resetUI);
        }
    });

    // 3. Bottone e Caricamento dati
    if (ui.btn) {
        ui.btn.disabled = true;
        ui.btn.textContent = "Caricamento...";

        fetch('/StackMasters/public/assets/data/comuni.json')
            .then(r => r.ok ? r.json() : Promise.reject("Err 404"))
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

        ui.btn.addEventListener("click", () => processaCF());
    }

    resetUI();
});

function analizzaIncongruenzaCF(inserito, atteso) {
    if (inserito.length !== 16) {
        return `Lunghezza errata: inseriti ${inserito.length} caratteri invece di 16.`;
    }

    const segCognome = inserito.substring(0, 3);
    const segNome = inserito.substring(3, 6);
    const segDataSesso = inserito.substring(6, 11);
    const segComune = inserito.substring(11, 15);

    const attComune = atteso.substring(11, 15);
    const attDataSesso = atteso.substring(6, 11);
    const attCognome = atteso.substring(0, 3);
    const attNome = atteso.substring(3, 6);

    if (segComune !== attComune) return "Il comune di nascita non corrisponde al Codice Fiscale inserito.";
    if (segDataSesso !== attDataSesso) return "La data di nascita o il sesso non corrispondono al CF.";
    if (segCognome !== attCognome) return "Il cognome non corrisponde al CF.";
    if (segNome !== attNome) return "Il nome non corrisponde al CF.";

    return "Il carattere di controllo (ultima lettera) non è valido.";
}

function generaCodiceFiscale(dati, errEl) {
    if (errEl) errEl.style.display = "none";

    const comuneTrovato = elencoComuni.find(c => c.nome.toUpperCase() === dati.comune);
    const codCatastale = comuneTrovato ? comuneTrovato.codiceCatastale : null;

    if (!codCatastale) {
        if (errEl) {
            errEl.textContent = `Comune "${dati.comune}" non trovato nel database.`;
            errEl.style.display = "block";
        }
        return null;
    }

    let cf = getCodice(dati.cognome, false) +
        getCodice(dati.nome, true) +
        getDataSesso(dati.nascita, dati.sesso) +
        codCatastale;

    return cf + calcolaCin(cf);
}

function getVocCons(str) {
    return {
        v: str.replace(/[^AEIOU]/g, ''),
        c: str.replace(/[^B-DF-HJ-NP-TV-Z]/g, '')
    };
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
        0:1, 1:0, 2:5, 3:7, 4:9, 5:13, 6:15, 7:17, 8:19, 9:21,
        A:1, B:0, C:5, D:7, E:9, F:13, G:15, H:17, I:19, J:21,
        K:2, L:4, M:18, N:20, O:11, P:3, Q:6, R:8, S:12, T:14,
        U:16, V:10, W:22, X:25, Y:24, Z:23
    };
    let sum = 0;
    for (let i = 0; i < 15; i++) {
        const c = cf[i];
        if (i % 2 === 0) sum += values[c];
        else sum += (c >= '0' && c <= '9') ? parseInt(c) : c.charCodeAt(0) - 65;
    }
    return String.fromCharCode((sum % 26) + 65);
}