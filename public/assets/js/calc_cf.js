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
        if (isValid) ui.btn.disabled = true;
    }

    function resetUI() {
        ui.input.classList.remove('is-valid', 'is-invalid');
        toggleError(ui.errCF, "");
        toggleError(ui.errComune, "");

        const len = ui.input.value.length;
        if (ui.counter) ui.counter.textContent = `${len}/${CF_LEN}`;

        ui.btn.textContent = len === 0 ? "Calcola" : "Verifica";
        ui.btn.disabled = len > 0 && len !== CF_LEN;
    }

    // Logica Principale
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
            toggleError(ui.errCF, "Compila tutti i dati anagrafici.");
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
            // Caso: CALCOLO
            ui.input.value = cfGenerato;
            setStatus(true);
            toggleError(ui.errCF, ""); // Assicuriamoci di pulire eventuali errori precedenti
            if (ui.counter) ui.counter.textContent = `${CF_LEN}/${CF_LEN}`;
        } else {
            // Caso: VERIFICA
            const match = cfInput === cfGenerato;
            setStatus(match);

            if (match) {
                toggleError(ui.errCF, ""); // Pulisce l'errore se verificato correttamente
            } else {
                toggleError(ui.errCF, "Il CF inserito non corrisponde ai dati.");
            }
        }
    }

    // Listener Input CF
    if (ui.input) {
        ui.input.addEventListener("input", () => {
            ui.input.value = ui.input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            resetUI();
        });
        resetUI();
    }

    if (ui.btn) {
        ui.btn.disabled = true;
        ui.btn.textContent = "Caricamento...";

        // Caricamento comuni
        fetch('/StackMasters/public/assets/data/comuni.json')
            .then(r => r.ok ? r.json() : Promise.reject("Err 404"))
            .then(data => {
                elencoComuni = data;
                console.log(`Caricati ${data.length} comuni.`);
                resetUI();
            })
            .catch(e => {
                console.error(e);
                ui.btn.textContent = "Errore";
                toggleError(ui.errCF, "Errore caricamento database comuni.");
            });

        ui.btn.addEventListener("click", () => processaCF());
    }
});

// --- ALGORITMI calcolo CF ---

function generaCodiceFiscale(dati, errEl) {
    if (errEl) errEl.style.display = "none";

    const codCatastale = elencoComuni.find(c => c.nome.toUpperCase() === dati.comune)?.codiceCatastale;

    if (!codCatastale) {
        if (errEl) {
            errEl.textContent = `Comune "${dati.comune}" non trovato.`;
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
        A:1, B:0, C:5, D:7, E:9, F:13, G:15, H:17, I:19, J:21, K:2, L:4, M:18, N:20, O:11, P:3, Q:6, R:8, S:12, T:14, U:16, V:10, W:22, X:25, Y:24, Z:23
    };
    let sum = 0;
    for (let i = 0; i < 15; i++) {
        const c = cf[i];
        if (i % 2 === 0) sum += values[c];
        else sum += (c >= '0' && c <= '9') ? parseInt(c) : c.charCodeAt(0) - 65;
    }
    return String.fromCharCode((sum % 26) + 65);
}