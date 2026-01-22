<?php
/**
 * lista-prestiti.php - Monitoraggio Professionale della Circolazione Attiva
 * Percorso: dashboard/librarian/lista-prestiti.php
 */

require_once __DIR__ . '/../../src/config/session.php';
require_once __DIR__ . '/../../src/config/database.php';

// Protezione dell'accesso
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit;
}

require_once __DIR__ . '/../../src/Views/layout/header.php';

try {
    $db = Database::getInstance()->getConnection();

    /**
     * Recupero dei prestiti attivi con i nomi corretti dei campi del database
     */
    $sql = "SELECT 
                p.id_prestito,
                p.data_prestito,
                p.scadenza_prestito,
                u.nome,
                u.cognome,
                u.cf,
                l.titolo,
                i.id_inventario,
                l.immagine_copertina
            FROM prestiti p
            JOIN utenti u ON p.id_utente = u.id_utente
            JOIN inventari i ON p.id_inventario = i.id_inventario
            JOIN libri l ON i.id_libro = l.id_libro
            WHERE p.data_restituzione IS NULL
            ORDER BY p.scadenza_prestito";

    $stmt = $db->query($sql);
    $prestiti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiche rapide
    $totaleAttivi = count($prestiti);
    $inRitardo = 0;
    $rientriOggi = 0;
    $oggi = new DateTime();
    $oggiString = $oggi->format('Y-m-d');

    foreach ($prestiti as $p) {
        $scadenzaDT = new DateTime($p['scadenza_prestito']);
        $scadenzaString = $scadenzaDT->format('Y-m-d');

        // Conteggio in ritardo
        if ($scadenzaDT < $oggi && $scadenzaString !== $oggiString) {
            $inRitardo++;
        }

        // Conteggio rientri previsti per oggi
        if ($scadenzaString === $oggiString) {
            $rientriOggi++;
        }
    }

} catch (Exception $e) {
    die("<div class='p-10 text-center text-red-600 font-bold'>Errore critico del sistema: " . $e->getMessage() . "</div>");
}
?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .glass-card {
            background: var(--card-bg);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 24px;
            padding: 1.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 15px -3px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
            border-color: var(--primary);
        }

        .stat-icon-wrapper {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .table-main-wrapper {
            background: var(--card-bg);
            border-radius: 28px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .table-toolbar {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
        }

        .search-pill-container {
            position: relative;
            flex-grow: 1;
            max-width: 500px;
        }

        .search-pill-container i {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .search-pill-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            background: #f1f5f9;
            border: 2px solid transparent;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-pill-input:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 5px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .search-pill-input:focus + i {
            color: var(--primary);
        }

        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .modern-table thead th {
            padding: 1.25rem 2rem;
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            border-bottom: 2px solid #f1f5f9;
        }

        .modern-table tbody tr {
            transition: all 0.2s ease;
        }

        .modern-table tbody td {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .modern-table tbody tr:hover td {
            background-color: #fcfdfe;
            cursor: default;
        }

        .modern-table tbody tr:hover {
            box-shadow: inset 4px 0 0 0 var(--primary);
        }

        .book-cover-art {
            width: 52px;
            height: 76px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.15);
            border: 2px solid #fff;
            transition: transform 0.3s ease;
        }

        tr:hover .book-cover-art {
            transform: scale(1.1) rotate(-2deg);
        }

        .status-badge-premium {
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
        }

        .bg-ok {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #d1fae5;
        }

        .bg-late {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #ffe4e6;
        }

        .btn-action-primary {
            background: var(--primary);
            color: white;
            padding: 0.85rem 1.75rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-action-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 15px 20px -5px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .btn-action-return {
            background: rgba(16, 185, 129, 0.08);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 0.6rem 1.1rem;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
        }

        .btn-action-return:hover {
            background: #10b981;
            color: white;
            border-color: #10b981;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.25);
        }

        .btn-action-return i {
            font-size: 0.85rem;
            transition: transform 0.3s ease;
        }

        .btn-action-return:hover i {
            transform: rotate(-15deg) translateX(-2px);
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>

    <div class="dashboard-container">
        <!-- Intestazione -->
        <div class="mb-12">
            <h1 class="text-4xl font-black tracking-tighter text-slate-900 uppercase">
                Gestione <span class="text-indigo-600">Circolazione</span>
            </h1>
            <p class="text-slate-500 font-medium text-lg">Monitoraggio in tempo reale del patrimonio librario in
                uscita.</p>
        </div>

        <!-- Statistiche -->
        <div class="stats-grid">
            <div class="glass-card">
                <div class="stat-icon-wrapper bg-indigo-50 text-indigo-600">
                    <i class="fas fa-book-reader"></i>
                </div>
                <div>
                    <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest leading-none mb-2">
                        Prestiti Attivi</p>
                    <span class="text-3xl font-black text-slate-800"><?= $totaleAttivi ?></span>
                </div>
            </div>

            <div class="glass-card" style="<?= $inRitardo > 0 ? 'border-color: #fecaca;' : '' ?>">
                <div class="stat-icon-wrapper <?= $inRitardo > 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-50 text-slate-400' ?>">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest leading-none mb-2">
                        Scadenze Superate</p>
                    <span class="text-3xl font-black <?= $inRitardo > 0 ? 'text-rose-600' : 'text-slate-800' ?>"><?= $inRitardo ?></span>
                </div>
            </div>

            <div class="glass-card">
                <div class="stat-icon-wrapper bg-emerald-50 text-emerald-600">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <p class="text-[0.65rem] font-black text-slate-400 uppercase tracking-widest leading-none mb-2">
                        Rientri Previsti Oggi</p>
                    <span class="text-3xl font-black text-slate-800"><?= $rientriOggi ?></span>
                </div>
            </div>
        </div>

        <!-- Main Table UI -->
        <div class="table-main-wrapper">
            <div class="table-toolbar">
                <div class="search-pill-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="loanSearch" onkeyup="filterTable()"
                           class="search-pill-input"
                           placeholder="Cerca per nominativo, codice fiscale o titolo...">
                </div>

                <div class="flex items-center gap-4">
                    <a href="new-loan.php" class="btn-action-primary flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i> Nuovo Prestito
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="modern-table" id="loansTable">
                    <thead>
                    <tr>
                        <th class="w-1/3">Esemplare</th>
                        <th>Assegnatario</th>
                        <th>Termini</th>
                        <th>Stato Operativo</th>
                        <th class="text-right">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($prestiti)): ?>
                        <tr>
                            <td colspan="5" class="py-32 text-center">
                                <div class="flex flex-col items-center opacity-30">
                                    <i class="fas fa-archive text-6xl mb-4"></i>
                                    <p class="text-sm font-bold uppercase tracking-widest">Archivio Corrente Vuoto</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prestiti as $p):
                            try {
                                $scadenza = new DateTime($p['scadenza_prestito']);
                            } catch (DateMalformedStringException $e) {
                                $scadenza = null;
                            }
                            $isRitardo = $scadenza < $oggi && $scadenza->format('Y-m-d') !== $oggiString;
                            $cover = $p['immagine_copertina'] ?: '../../public/assets/img/placeholder.png';
                            $scadenzaFormatted = date('d M Y', strtotime($p['scadenza_prestito']));
                            ?>
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-5">
                                        <img src="<?= $cover ?>" class="book-cover-art" alt="Libro">
                                        <div class="max-w-[280px]">
                                            <p class="font-extrabold text-slate-800 text-sm leading-snug line-clamp-2 uppercase mb-1"><?= htmlspecialchars($p['titolo']) ?></p>
                                            <span class="bg-slate-100 text-slate-500 text-[9px] font-black px-2 py-0.5 rounded uppercase font-mono">
                                                ID: #<?= $p['id_inventario'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-700 text-sm mb-1"><?= htmlspecialchars($p['cognome'] . ' ' . $p['nome']) ?></p>
                                    <p class="text-[10px] font-bold text-indigo-500 font-mono tracking-tighter"><?= htmlspecialchars($p['cf']) ?></p>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="text-[9px] font-black text-slate-400 uppercase mb-1">Ritorno previsto</span>
                                        <span class="text-sm font-extrabold <?= $isRitardo ? 'text-rose-600' : 'text-slate-700' ?>">
                                            <?= $scadenzaFormatted ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isRitardo): ?>
                                        <span class="status-badge-premium bg-late">
                                            <i class="fas fa-exclamation-circle text-rose-500"></i> Scaduto
                                        </span>
                                    <?php elseif ($scadenza->format('Y-m-d') === $oggiString): ?>
                                        <span class="status-badge-premium bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="fas fa-hourglass-half"></i> In Scadenza
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge-premium bg-ok">
                                            <i class="fas fa-check-circle text-emerald-500"></i> In Corso
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <a href="returns.php?id=<?= $p['id_inventario'] ?>" class="btn-action-return">
                                        <i class="fas fa-undo-alt"></i> Registra Rientro
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="/../../public/assets/js/filter-table-librarian.js"></script>
<?php require_once __DIR__ . '/../../src/Views/layout/footer.php'; ?>