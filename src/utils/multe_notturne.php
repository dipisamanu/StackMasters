<?php
/**
 * Script Cron per il calcolo automatico delle multe
 * Esecuzione: php CalcolaMulteCron.php
 */

namespace Ottaviodipisa\StackMasters\utils\MulteNotturne;

// 1. Caricamento Autoload e Ambiente
require_once __DIR__ . '/../../vendor/autoload.php';

use PDO;
use PDOException;
use Exception;
use Dotenv\Dotenv;

class CalcolaMulteCron
{
    private PDO $db;
    private string $logFile;

    // Costanti di configurazione
    private const GIORNI_TOLLERANZA = 3;
    private const IMPORTO_GIORNALIERO = 0.50;
    private const SCONTO_UTENTI_AFFIDABILI = 0.10;
    private const SOGLIA_PRESTITI_AFFIDABILE = 20;

    public function __construct()
    {
        $this->inizializzaAmbiente();
        $this->connettiDatabase();
        $this->setupLog();
    }

    private function inizializzaAmbiente(): void
    {
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
        }
    }

    private function connettiDatabase(): void
    {
        // Recupero parametri da ENV (coerente con i file di ieri)
        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'] ;

        try {
            $this->db = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            $this->log("ERRORE CONNESSIONE: " . $e->getMessage(), 'CRITICAL');
            die("Exit: Database non raggiungibile.\n");
        }
    }

    private function setupLog(): void
    {
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . 'cron_multe_' . date('Y-m-d') . '.log';
    }

    public function esegui(): void
    {
        $this->log("--- Inizio Processo Notturno ---");

        try {
            $this->db->beginTransaction();

            $prestiti = $this->getPrestitiScaduti();

            if (empty($prestiti)) {
                $this->log("Nessun prestito scaduto oggi.");
                $this->db->commit();
                return;
            }

            $contatori = [
                'processati' => 0,
                'nuove' => 0,
                'aggiornate' => 0,
                'totale_euro' => 0
            ];

            foreach ($prestiti as $p) {
                $res = $this->elaboraSingoloPrestito($p);
                $contatori['processati']++;
                if ($res['tipo'] === 'creata') $contatori['nuove']++;
                if ($res['tipo'] === 'aggiornata') $contatori['aggiornate']++;
                $contatori['totale_euro'] += $res['importo'];
            }

            $this->db->commit();
            $this->log("FINE: Processati {$contatori['processati']} record. Totale maturato: €{$contatori['totale_euro']}");

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->log("ERRORE DURANTE L'ESECUZIONE: " . $e->getMessage(), 'ERROR');
        }
    }

    private function getPrestitiScaduti(): array
    {
        // Query ottimizzata con PDO
        $sql = "SELECT p.*, u.nome, u.email, ur.prestiti_tot, l.titolo
                FROM Prestiti p
                JOIN Utenti u ON p.id_utente = u.id_utente
                JOIN Inventari i ON p.id_inventario = i.id_inventario
                JOIN Libri l ON i.id_libro = l.id_libro
                LEFT JOIN Utenti_Ruoli ur ON u.id_utente = ur.id_utente
                WHERE p.data_restituzione IS NULL 
                AND p.scadenza_prestito < CURDATE()";

        return $this->db->query($sql)->fetchAll();
    }

    private function elaboraSingoloPrestito(array $p): array
    {
        $oggi = new \DateTime();
        $scadenza = new \DateTime($p['scadenza_prestito']);
        $diff = $oggi->diff($scadenza)->days;

        if ($diff <= self::GIORNI_TOLLERANZA) {
            return ['tipo' => 'skip', 'importo' => 0];
        }

        $giorniEffettivi = $diff - self::GIORNI_TOLLERANZA;
        $importo = $giorniEffettivi * self::IMPORTO_GIORNALIERO;

        // Applica sconto se utente affidabile
        if (($p['prestiti_tot'] ?? 0) >= self::SOGLIA_PRESTITI_AFFIDABILE) {
            $importo -= ($importo * self::SCONTO_UTENTI_AFFIDABILI);
        }

        // Controllo se esiste già una multa per oggi (evita duplicati se il cron gira 2 volte)
        $stmt = $this->db->prepare("SELECT id_multa FROM Multe WHERE id_utente = ? AND DATE(data_creazione) = CURDATE() AND causa = 'RITARDO'");
        $stmt->execute([$p['id_utente']]);
        $esistente = $stmt->fetch();

        if ($esistente) {
            $upd = $this->db->prepare("UPDATE Multe SET importo = ?, giorni = ? WHERE id_multa = ?");
            $upd->execute([$importo, $giorniEffettivi, $esistente['id_multa']]);
            return ['tipo' => 'aggiornata', 'importo' => $importo];
        } else {
            $ins = $this->db->prepare("INSERT INTO Multe (id_utente, importo, giorni, causa, data_creazione) VALUES (?, ?, ?, 'RITARDO', NOW())");
            $ins->execute([$p['id_utente'], $importo, $giorniEffettivi]);
            return ['tipo' => 'creata', 'importo' => $importo];
        }
    }

    private function log(string $msg, string $level = 'INFO'): void
    {
        $date = date('Y-m-d H:i:s');
        $formattato = "[$date] [$level] $msg" . PHP_EOL;
        file_put_contents($this->logFile, $formattato, FILE_APPEND);
        echo $formattato; // Utile per il debug manuale da terminale
    }
}

// === BOOTSTRAP ESECUZIONE ===
if (php_sapi_name() !== 'cli') {
    die("Accesso negato. Solo CLI.");
}

$cron = new CalcolaMulteCron();
$cron->esegui();