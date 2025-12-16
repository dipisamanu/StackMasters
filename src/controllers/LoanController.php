<?php

namespace Ottaviodipisa\StackMasters\controllers;

use Ottaviodipisa\StackMasters\Core\Controller; // Assumendo che il tuo Base Controller sia qui
use Ottaviodipisa\StackMasters\models\PrestitoManager;

/**
 * Gestisce tutte le operazioni relative al Prestito e alla Restituzione
 * per l'interfaccia del Bibliotecario.
 */
class LoanController extends Controller
{
    private PrestitoManager $manager;

    public function __construct()
    {
        // Inizializza il PrestitoManager che gestirà la logica di business e le transazioni DB.
        $this->manager = new PrestitoManager();
        // TODO: [Implementare qui la verifica dell'autenticazione per il ruolo Bibliotecario]
    }

    // =====================================================================
    // 1. FUNZIONALITÀ PRESTITO (Nuovo)
    // Mappa a: GET /admin/prestito
    // =====================================================================

    /**
     * Mostra l'interfaccia per la registrazione di un nuovo prestito (scansione utente + copia).
     */
    public function nuovoPrestitoForm()
    {
        $data = ['message' => '', 'message_type' => '', 'scanned_user' => ''];
        $this->view('admin/nuovo_prestito', $data);
    }

    /**
     * Elabora la richiesta POST per registrare il prestito.
     * Logica:
     * 1. Recupera gli ID Utente e Inventario dal POST.
     * 2. Chiama PrestitoManager::registraPrestito (Logica Atomica).
     * 3. Gestisce Successo o Eccezioni (Multe, Limiti, Prenotazioni).
     */
    public function registraPrestito()
    {
        $message = '';
        $message_type = '';
        $utenteId = null;

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            // Recupero e sanificazione degli ID scansionati
            $utenteId = filter_input(INPUT_POST, 'user_barcode', FILTER_VALIDATE_INT);
            $inventarioId = filter_input(INPUT_POST, 'book_barcode', FILTER_VALIDATE_INT);

            if (!$utenteId || !$inventarioId) {
                $message = "Errore: i codici Utente e Libro devono essere numerici e non possono essere vuoti.";
                $message_type = 'error';
            } else {
                try {
                    // Esecuzione della transazione di prestito nel Model
                    $risultato = $this->manager->registraPrestito($utenteId, $inventarioId);

                    $message = "✅ Prestito Registrato! Scadenza: " . date('d/m/Y', strtotime($risultato['data_scadenza']));
                    $message .= " | " . htmlspecialchars($risultato['messaggio']);
                    $message_type = 'success';

                    // TODO: Attivare Sub-issue 5.7 (Generazione PDF ricevuta) & 5.8 (Trigger email)

                } catch (\Exception $e) {
                    // Cattura i blocchi e gli errori di business
                    $message = "❌ Errore Prestito: " . htmlspecialchars($e->getMessage());
                    $message_type = 'error';
                }
            }
        }

        // Ritorna alla vista con l'esito
        $data = ['message' => $message, 'message_type' => $message_type, 'scanned_user' => $utenteId];
        $this->view('admin/nuovo_prestito', $data);
    }

    // =====================================================================
    // 2. FUNZIONALITÀ RESTITUZIONE
    // Mappa a: GET /admin/restituzione
    // =====================================================================

    /**
     * Mostra l'interfaccia per la registrazione di una restituzione.
     */
    public function restituzioneForm()
    {
        $data = ['message' => '', 'message_type' => '', 'condizione' => 'BUONO'];
        $this->view('admin/registra_restituzione', $data);
    }

    /**
     * Elabora la richiesta POST per registrare la restituzione.
     * Logica:
     * 1. Recupera ID Inventario, Condizione e Commento Danno dal POST.
     * 2. Chiama PrestitoManager::registraRestituzione (Calcolo multe, cambio stato).
     * 3. Visualizza il riepilogo (multe e successioni di prenotazione).
     */
    public function registraRestituzione()
    {
        $message = '';
        $message_type = '';
        $condizione = 'BUONO';
        $dannoCommento = null;

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            // Recupero e sanificazione degli input (inclusi quelli del Modale)
            $inventarioId = filter_input(INPUT_POST, 'book_barcode', FILTER_VALIDATE_INT);
            $condizione = filter_input(INPUT_POST, 'condizione', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $dannoCommento = filter_input(INPUT_POST, 'commento_danno', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (!$inventarioId) {
                $message = "Errore: Il codice della Copia Libro (ID Inventario) non può essere vuoto.";
                $message_type = 'error';
            } else {
                try {
                    // Esecuzione della transazione di restituzione nel Model
                    $risultato = $this->manager->registraRestituzione($inventarioId, $condizione, $dannoCommento);

                    // Messaggio di riepilogo
                    $multaMsg = $risultato['multa_totale'] > 0 ? " (Multa Totale: {$risultato['multa_totale']} €)" : "";

                    $message = "✅ Restituzione Registrata!" . $multaMsg . "<br>";
                    $message .= implode('<br>', $risultato['messaggi']);
                    $message_type = 'success';

                } catch (\Exception $e) {
                    $message = "❌ Errore Restituzione: " . htmlspecialchars($e->getMessage());
                    $message_type = 'error';
                }
            }
        }

        $data = ['message' => $message, 'message_type' => $message_type, 'condizione' => $condizione];
        $this->view('admin/registra_restituzione', $data);
    }
}