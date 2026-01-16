/**
 * loans-filter.js - Ricerca Avanzata per la Lista Prestiti
 * Supporta: Logica AND (più parole), Highlighting e Case-Insensitivity
 */

function filterTable() {
    const input = document.getElementById("loanSearch");
    if (!input) return;

    const filter = input.value.toLowerCase().trim();
    const terms = filter.split(/\s+/); // Divide la ricerca in più parole (es: "Rossi Promessi")
    const table = document.getElementById("loansTable");
    const tr = table.getElementsByTagName("tr");

    // Partiamo da 1 per saltare l'header della tabella
    for (let i = 1; i < tr.length; i++) {
        // Ignoriamo la riga "Archivio Corrente Vuoto" se presente
        if (tr[i].cells.length < 2) continue;

        let rowText = "";
        const cells = tr[i].getElementsByTagName("td");
        let showRow = true;

        // Estraiamo il testo da tutte le celle (Titolo, Nome, CF, etc.)
        for (let j = 0; j < cells.length; j++) {
            rowText += cells[j].textContent.toLowerCase() + " ";
        }

        // Verifica logica AND: la riga deve contenere TUTTE le parole cercate
        for (let term of terms) {
            if (rowText.indexOf(term) === -1) {
                showRow = false;
                break;
            }
        }

        if (showRow) {
            tr[i].style.display = "";
            if (filter !== "") {
                applyHighlight(tr[i], terms);
            } else {
                removeHighlight(tr[i]);
            }
        } else {
            tr[i].style.display = "none";
        }
    }
}

/**
 * Applica l'evidenziazione ai termini trovati senza rompere i tag HTML interni
 */
function applyHighlight(row, terms) {
    const cells = row.getElementsByTagName("td");
    // Evidenziamo solo le prime 2 celle (Libro e Utente) per non sporcare i badge di stato
    for (let i = 0; i < 2; i++) {
        let cell = cells[i];
        // Selezioniamo solo i paragrafi o span che contengono il testo vero e proprio
        // per evitare di distruggere l'HTML delle immagini o dei badge
        const textElements = cell.querySelectorAll('p, span:not(.status-badge-premium)');

        textElements.forEach(el => {
            if (el.children.length > 0) return; // Salta se ha figli (per sicurezza)

            let html = el.textContent;
            terms.forEach(term => {
                if (term.length < 2) return;
                const regex = new RegExp(`(${term})`, "gi");
                html = html.replace(regex, '<mark class="bg-yellow-200 text-black rounded px-0.5">$1</mark>');
            });
            el.innerHTML = html;
        });
    }
}

function removeHighlight(row) {
    const textElements = row.querySelectorAll('p, span:not(.status-badge-premium)');
    textElements.forEach(el => {
        if (el.children.length > 0 && el.querySelector('mark')) {
            el.innerHTML = el.textContent;
        }
    });
}