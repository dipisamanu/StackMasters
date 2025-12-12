<?php
/**
 * Script Cron per il calcolo automatico delle multe sui prestiti scaduti
 * Adattato allo schema database biblioteca_db
 *
 * Esecuzione: ogni notte alle 02:00
 * Crontab: 0 2 * * *
 */

namespace Ottaviodipisa\StackMasters\Utils\MulteNotturne;

require_once __DIR__ . '/../../vendor/autoload.php';

use PDO;
use Dotenv\Dotenv;

class CalcolaMulteCron
{
    private PDO $db;
    private string $logFile;

    // Configurazione multe
    private const GIORNI_TOLLERANZA = 3;
    private const IMPORTO_GIORNALIERO = 0.50;
    private const SCONTO_UTENTI_AFFIDABILI = 0.10; // 10%
    private const SOGLIA_PRESTITI_AFFIDABILE = 20;

    public function __construct()
    {
        // Carica le variabili d'ambiente
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Carica la configurazione del database
        $config = require __DIR__ . '/../config/database.php';

        try {
            $this->db = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['user'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\PDOException $e) {
            die("Errore connessione DB: " . $e->getMessage());
        }

        // Setup log
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $this->logFile = $logDir . 'multe_' . date('Y-m-d') . '.log';

        $this->log("=== AVVIO CALCOLO MULTE NOTTURNO ===");
        $this->log("Data/Ora: " . date('Y-m-d H:i:s'));
    }

    public function esegui(): void
    {
        try {
            $this->db->beginTransaction();

            $prestitiScaduti = $this->trovaPrestitiScaduti();
            $this->log("Trovati " . count($prestitiScaduti) . " prestiti scaduti");

            if (empty($prestitiScaduti)) {
                $this->log("Nessun prestito scaduto da processare");
                $this->db->commit();
                return;
            }

            $risultati = $this->calcolaERegistraMulte($prestitiScaduti);
            $this->bloccaUtentiInRitardo($prestitiScaduti);

            $this->db->commit();
            $this->logRiepilogo($risultati);
            $this->inviaNotifiche($risultati);

        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->log("ERRORE CRITICO: " . $e->getMessage(), 'ERROR');
            $this->log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
        }
    }

    private function trovaPrestitiScaduti(): array
    {
        $query = "
            SELECT 
                p.id_prestito,
                p.id_utente,
                p.id_inventario,
                p.scadenza_prestito,
                p.data_prestito,
                u.nome,
                u.cognome,
                u.email,
                l.titolo,
                l.id_libro,
                DATEDIFF(CURDATE(), p.scadenza_prestito) AS giorni_ritardo,
                ur.prestiti_tot
            FROM Prestiti p
            INNER JOIN Utenti u ON p.id_utente = u.id_utente
            INNER JOIN Inventari i ON p.id_inventario = i.id_inventario
            INNER JOIN Libri l ON i.id_libro = l.id_libro
            LEFT JOIN Utenti_Ruoli ur ON u.id_utente = ur.id_utente
            WHERE p.data_restituzione IS NULL
              AND p.scadenza_prestito < CURDATE()
            ORDER BY p.scadenza_prestito ASC
        ";

        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calcolaERegistraMulte(array $prestiti): array
    {
        $risultati = [
            'totale_processati' => 0,
            'nuove_multe' => 0,
            'multe_aggiornate' => 0,
            'importo_totale_maturato' => 0,
            'errori' => 0,
            'dettagli' => []
        ];

        foreach ($prestiti as $prestito) {
            try {
                $risultati['totale_processati']++;
                $giorniRitardo = (int)$prestito['giorni_ritardo'];
                $utenteId = $prestito['id_utente'];
                $prestitoId = $prestito['id_prestito'];

                if ($giorniRitardo <= self::GIORNI_TOLLERANZA) {
                    $this->log("Prestito {$prestitoId}: ancora in tolleranza ({$giorniRitardo} giorni)", 'DEBUG');
                    continue;
                }

                $giorniMulta = $giorniRitardo - self::GIORNI_TOLLERANZA;
                $importoBase = $giorniMulta * self::IMPORTO_GIORNALIERO;

                $prestitiCompletati = (int)($prestito['prestiti_tot'] ?? 0);
                $sconto = ($prestitiCompletati >= self::SOGLIA_PRESTITI_AFFIDABILE)
                    ? $importoBase * self::SCONTO_UTENTI_AFFIDABILI
                    : 0;

                $importoFinale = round($importoBase - $sconto, 2);

                $multaEsistente = $this->trovaMultaOggi($utenteId, $giorniMulta);

                if ($multaEsistente) {
                    $this->aggiornaMulta($multaEsistente['id_multa'], $importoFinale, $giorniMulta);
                    $risultati['multe_aggiornate']++;
                    $azione = 'aggiornata';
                } else {
                    $this->creaMulta($utenteId, $giorniMulta, $importoFinale, $prestito['titolo']);
                    $risultati['nuove_multe']++;
                    $azione = 'creata';
                }

                $risultati['importo_totale_maturato'] += $importoFinale;
                $risultati['dettagli'][] = [
                    'utente_id' => $utenteId,
                    'email' => $prestito['email'],
                    'nome' => $prestito['nome'] . ' ' . $prestito['cognome'],
                    'libro' => $prestito['titolo'],
                    'giorni_ritardo' => $giorniRitardo,
                    'importo' => $importoFinale,
                    'azione' => $azione
                ];

                $this->log("Prestito {$prestitoId}: multa {$azione} - €{$importoFinale} ({$giorniMulta} giorni)");

            } catch (\Exception $e) {
                $risultati['errori']++;
                $this->log("Errore prestito {$prestitoId}: " . $e->getMessage(), 'ERROR');
            }
        }

        return $risultati;
    }

    private function trovaMultaOggi(int $utenteId, int $giorni): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_multa, importo
            FROM Multe
            WHERE id_utente = ?
              AND causa = 'RITARDO'
              AND giorni = ?
              AND DATE(data_creazione) = CURDATE()
            ORDER BY data_creazione DESC
            LIMIT 1
        ");
        $stmt->execute([$utenteId, $giorni]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function aggiornaMulta(int $multaId, float $nuovoImporto, int $giorniRitardo): void
    {
        $stmt = $this->db->prepare("
            UPDATE Multe SET importo = ?, giorni = ? WHERE id_multa = ?
        ");
        $stmt->execute([$nuovoImporto, $giorniRitardo, $multaId]);
    }

    private function creaMulta(int $utenteId, int $giorni, float $importo, string $titoloLibro): void
    {
        $commento = "Ritardo restituzione libro: {$titoloLibro}";
        $stmt = $this->db->prepare("
            INSERT INTO Multe (id_utente, giorni, importo, causa, commento, data_creazione)
            VALUES (?, ?, ?, 'RITARDO', ?, NOW())
        ");
        $stmt->execute([$utenteId, $giorni, $importo, $commento]);
    }

    private function bloccaUtentiInRitardo(array $prestiti): void
    {
        $utentiDaBloccare = [];
        foreach ($prestiti as $prestito) {
            if ((int)$prestito['giorni_ritardo'] > self::GIORNI_TOLLERANZA) {
                $utentiDaBloccare[] = $prestito['id_utente'];
            }
        }
        $utentiDaBloccare = array_unique($utentiDaBloccare);
        if (empty($utentiDaBloccare)) return;

        $dataBloccofino = date('Y-m-d H:i:s', strtotime('+1 year'));
        $placeholders = implode(',', array_fill(0, count($utentiDaBloccare), '?'));
        $params = array_merge([$dataBloccofino], $utentiDaBloccare);

        $stmt = $this->db->prepare("
            UPDATE Utenti
            SET blocco_account_fino_al = ?
            WHERE id_utente IN ({$placeholders})
              AND (blocco_account_fino_al IS NULL OR blocco_account_fino_al < NOW())
        ");
        $stmt->execute($params);
        $this->log("Bloccati {$stmt->rowCount()} utenti con prestiti in ritardo");
    }

    private function inviaNotifiche(array $risultati): void
    {
        foreach ($risultati['dettagli'] as $dettaglio) {
            $giorniRitardo = $dettaglio['giorni_ritardo'];
            if ($giorniRitardo >= 4 && $giorniRitardo <= 7) $tipo = 'PRIMO_AVVISO';
            elseif ($giorniRitardo >= 8 && $giorniRitardo <= 14) $tipo = 'SECONDO_AVVISO';
            else $tipo = 'COMUNICAZIONE_FORMALE';

            $this->log("Email {$tipo} per: {$dettaglio['email']} - Multa: €{$dettaglio['importo']}");
        }
    }

    private function logRiepilogo(array $risultati): void
    {
        $this->log("=== RIEPILOGO ELABORAZIONE ===");
        $this->log("Prestiti processati: {$risultati['totale_processati']}");
        $this->log("Nuove multe create: {$risultati['nuove_multe']}");
        $this->log("Multe aggiornate: {$risultati['multe_aggiornate']}");
        $this->log("Importo totale maturato: €" . number_format($risultati['importo_totale_maturato'], 2));
        $this->log("Errori: {$risultati['errori']}");
        $this->log("=== ELABORAZIONE COMPLETATA ===\n");
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        $line = "[" . date('Y-m-d H:i:s') . "] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $line, FILE_APPEND);
        echo $line;
    }
}

// =====================================================================
// ESECUZIONE SCRIPT
// =====================================================================
if (php_sapi_name() !== 'cli') {
    die("Questo script può essere eseguito solo da command line.\n");
}

$cron = new CalcolaMulteCron();
$cron->esegui();
exit(0);
