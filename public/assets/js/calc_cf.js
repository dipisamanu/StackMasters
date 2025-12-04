let elencoComuni = [];

document.addEventListener("DOMContentLoaded", () => {
    const btnCalcola = document.getElementById("btnCalcolaCF");

    //https://github.com/matteocontrini/comuni-json
    // 1. Carica il database JSON
    fetch('/StackMasters/assets/data/comuni.json')
        .then(response => {
            if (!response.ok) {
                throw new Error("Impossibile caricare il file comuni.json");
            }
            return response.json();
        })
        .then(data => {
            elencoComuni = data;
            // Verifica in console che stia leggendo il campo giusto (opzionale, per debug)
            if (elencoComuni.length > 0) {
                console.log(`Database caricato: ${elencoComuni.length} comuni.`);
                console.log(`Test lettura primo comune: ${elencoComuni[0].nome} -> Catastale: ${elencoComuni[0].codiceCatastale}`);
            }
        })
        .catch(error => {
            console.error("Errore nel caricamento dei comuni:", error);
        });

    if (btnCalcola) {
        btnCalcola.addEventListener("click", calcolaCodiceFiscale);
    }
});

function calcolaCodiceFiscale() {
    const nome = document.getElementById("nome").value.toUpperCase().trim();
    const cognome = document.getElementById("cognome").value.toUpperCase().trim();
    const dataNascita = document.getElementById("dataNascita").value; // YYYY-MM-DD
    const sesso = document.getElementById("sesso").value; // M o F
    const comuneInput = document.getElementById("comune").value.toUpperCase().trim();
    const inputCF = document.getElementById("codiceFiscale");

    // Validazione base
    if (!nome || !cognome || !dataNascita || !sesso || !comuneInput) {
        alert("Compila tutti i dati anagrafici prima di calcolare.");
        return;
    }

    if (elencoComuni.length === 0) {
        alert("Attendi il caricamento della lista comuni o ricarica la pagina.");
        return;
    }

    try {
        let cf = "";

        // --- A. COGNOME ---
        cf += calcolaCognome(cognome);

        // --- B. NOME ---
        cf += calcolaNome(nome);

        // --- C. DATA E SESSO ---
        cf += calcolaDataSesso(dataNascita, sesso);

        // --- D. COMUNE (Modificato per usare codiceCatastale) ---
        const codiceBelfiore = getCodiceBelfiore(comuneInput);

        if (!codiceBelfiore) {
            alert(`Comune "${comuneInput}" non trovato. Controlla di averlo scritto esattamente come appare nei documenti ufficiali.`);
            return;
        }
        cf += codiceBelfiore;

        // --- E. CARATTERE DI CONTROLLO ---
        cf += calcolaCin(cf);

        // Output
        inputCF.value = cf;

        // Pulizia errori visuali
        inputCF.classList.remove('is-invalid');
        const errDiv = document.getElementById('errCF');
        if(errDiv) errDiv.style.display = 'none';

    } catch (e) {
        console.error(e);
        alert("Errore durante il calcolo.");
    }
}

/* --- FUNZIONI DI SUPPORTO --- */

function getCodiceBelfiore(nomeInserito) {
    // Cerchiamo nell'array caricato dal JSON.
    // Il JSON ha la proprietà "nome" (es: "Torino") e "codiceCatastale" (es: "L219").

    // Usiamo .find() confrontando i nomi in maiuscolo per evitare errori di case-sensitivity
    const comuneTrovato = elencoComuni.find(c => c.nome.toUpperCase() === nomeInserito);

    // Se trovato, restituiamo codiceCatastale. Altrimenti null.
    return comuneTrovato ? comuneTrovato.codiceCatastale : null;
}

function getConsonanti(str) {
    return str.replace(/[^B-DF-HJ-NP-TV-Z]/g, "");
}

function getVocali(str) {
    return str.replace(/[^AEIOU]/g, "");
}

function calcolaCognome(cognome) {
    const cons = getConsonanti(cognome);
    const voc = getVocali(cognome);
    const text = cons + voc + "XXX";
    return text.substring(0, 3);
}

function calcolaNome(nome) {
    const cons = getConsonanti(nome);
    const voc = getVocali(nome);

    if (cons.length >= 4) {
        return cons[0] + cons[2] + cons[3];
    } else {
        const text = cons + voc + "XXX";
        return text.substring(0, 3);
    }
}

function calcolaDataSesso(dataIso, sesso) {
    const anno = dataIso.substr(2, 2);
    const mese = parseInt(dataIso.substr(5, 2));
    let giorno = parseInt(dataIso.substr(8, 2));

    const codiciMese = ['A', 'B', 'C', 'D', 'E', 'H', 'L', 'M', 'P', 'R', 'S', 'T'];

    if (sesso === 'F') {
        giorno += 40;
    }

    const giornoStr = giorno < 10 ? "0" + giorno : giorno.toString();
    return anno + codiciMese[mese - 1] + giornoStr;
}

function calcolaCin(parziale) {
    const dispari = {
        '0': 1, '1': 0, '2': 5, '3': 7, '4': 9, '5': 13, '6': 15, '7': 17, '8': 19, '9': 21,
        'A': 1, 'B': 0, 'C': 5, 'D': 7, 'E': 9, 'F': 13, 'G': 15, 'H': 17, 'I': 19, 'J': 21,
        'K': 2, 'L': 4, 'M': 18, 'N': 20, 'O': 11, 'P': 3, 'Q': 6, 'R': 8, 'S': 12, 'T': 14,
        'U': 16, 'V': 10, 'W': 22, 'X': 25, 'Y': 24, 'Z': 23
    };

    let somma = 0;
    for (let i = 0; i < 15; i++) {
        const char = parziale[i];
        if ((i + 1) % 2 !== 0) {
            somma += dispari[char];
        } else {
            if (char >= '0' && char <= '9') {
                somma += parseInt(char);
            } else {
                somma += char.charCodeAt(0) - 'A'.charCodeAt(0);
            }
        }
    }
    const resto = somma % 26;
    return String.fromCharCode(resto + 'A'.charCodeAt(0));
}