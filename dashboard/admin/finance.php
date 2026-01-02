<?php
/**
 * Vista: Gestione Finanziaria (Epic 10)
 * Percorso: dashboard/admin/finance.php
 * Riceve $data dal FineController
 */

$user = $data['user'] ?? null;
$fines = $data['fines'] ?? [];
$discount = $data['discount'] ?? 0;
$debtors = $data['debtors'] ?? [];
$msg = $_GET['msg'] ?? '';
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro Finanziario - StackMasters</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<!-- Navigazione Superiore -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-book-reader text-white text-sm"></i>
            </div>
            <span class="font-bold text-slate-800 tracking-tight">StackMasters <span class="text-indigo-600">Admin</span></span>
        </div>
        <nav class="flex items-center gap-6">
            <a href="index.php" class="text-sm font-semibold text-slate-500 hover:text-indigo-600 transition">Dashboard</a>
            <a href="returns.php" class="text-sm font-semibold text-slate-500 hover:text-indigo-600 transition">Restituzioni</a>
            <div class="h-4 w-px bg-slate-200"></div>
            <a href="../../public/logout.php" class="text-sm font-bold text-rose-500 hover:text-rose-600">Esci</a>
        </nav>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-10">

    <!-- Intestazione Pagina -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Amministrazione Finanziaria</h1>
            <p class="text-slate-500">Gestione multe, pagamenti FIFO e reportistica per la segreteria.</p>
        </div>
        <a href="finance_report.php" class="inline-flex items-center gap-2 bg-white border border-slate-200 px-5 py-2.5 rounded-xl font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition">
            <i class="fas fa-chart-pie text-indigo-500"></i>
            Report Contabile
        </a>
    </div>

    <!-- Notifiche di Sistema -->
    <?php if($msg): ?>
        <div class="mb-8 p-4 rounded-2xl border-l-4 shadow-sm flex items-center justify-between <?= $status === 'success' ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-rose-50 border-rose-500 text-rose-800' ?>">
            <div class="flex items-center gap-3">
                <i class="fas <?= $status === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
                <span class="font-medium"><?= htmlspecialchars(str_replace('_', ' ', $msg)) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100">&times;</button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

        <!-- Colonna Principale (3/4) -->
        <div class="lg:col-span-3 space-y-8">

            <!-- Card di Ricerca -->
            <section class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200">
                <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Seleziona Utente</h2>
                <form action="" method="GET" class="flex gap-3">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="number" name="user_id" placeholder="Cerca per ID Utente (es. 101)"
                               class="w-full bg-slate-50 border-transparent rounded-2xl py-4 pl-12 pr-4 outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition text-lg font-medium"
                               value="<?= $_GET['user_id'] ?? '' ?>">
                    </div>
                    <button type="submit" class="bg-indigo-600 text-white px-8 rounded-2xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 active:scale-95">
                        Cerca
                    </button>
                </form>
            </section>

            <?php if($user): ?>
                <!-- Dettaglio Portafoglio Utente -->
                <section class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
                    <div class="bg-slate-900 p-8 text-white">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                            <div class="flex items-center gap-5">
                                <div class="w-16 h-16 bg-indigo-500/20 rounded-2xl flex items-center justify-center border border-white/10">
                                    <i class="fas fa-user text-2xl text-indigo-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold tracking-tight"><?= $user['nome'] ?> <?= $user['cognome'] ?></h3>
                                    <p class="text-slate-400 font-medium"><?= $user['email'] ?></p>
                                </div>
                            </div>
                            <div class="bg-white/5 px-6 py-4 rounded-2xl border border-white/10 backdrop-blur-sm">
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 block mb-1">Pendenza Totale</span>
                                <span class="text-4xl font-black text-rose-400"><?= number_format($user['debito_totale'], 2) ?> €</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-8">
                        <!-- Alert Algoritmo Affidabilità (Epic 10.4) -->
                        <?php if($discount > 0): ?>
                            <div class="bg-emerald-50 border border-emerald-100 p-5 rounded-2xl mb-10 flex items-start gap-4">
                                <div class="w-10 h-10 bg-emerald-500 text-white rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-emerald-900">Algoritmo Fedeltà: Utente Affidabile</p>
                                    <p class="text-emerald-700 text-sm">Lo storico prestiti è eccellente. Applica lo sconto manuale del <strong><?= $discount*100 ?>%</strong> sul totale.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <!-- Pannello Pagamento -->
                            <div class="space-y-6">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm">
                                        <i class="fas fa-cash-register"></i>
                                    </span>
                                    <h4 class="font-bold text-slate-800">Registra Incasso</h4>
                                </div>
                                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200">
                                    <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                                        Il pagamento estinguerà le sanzioni in sospeso. Verrà generata una quietanza PDF valida come liberatoria.
                                    </p>
                                    <form action="../../src/Controllers/FineController.php?action=pay" method="POST">
                                        <input type="hidden" name="user_id" value="<?= $user['id_utente'] ?>">
                                        <input type="number" step="0.01" name="pay_amount" max="<?= $user['debito_totale'] ?>"
                                               placeholder="Importo versato €" class="w-full bg-white border border-slate-200 rounded-xl p-3 mb-4 font-bold outline-none focus:ring-2 focus:ring-emerald-500" required>
                                        <button type="submit" class="w-full bg-emerald-600 text-white py-4 rounded-xl font-bold hover:bg-emerald-700 transition shadow-lg shadow-emerald-100 flex items-center justify-center gap-2">
                                            <i class="fas fa-check-double"></i>
                                            Conferma e Stampa PDF
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Pannello Addebito Manuale (Epic 10.3) -->
                            <div class="space-y-6">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 bg-rose-100 text-rose-600 rounded-lg flex items-center justify-center text-sm">
                                        <i class="fas fa-plus"></i>
                                    </span>
                                    <h4 class="font-bold text-slate-800">Nuovo Addebito</h4>
                                </div>
                                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200">
                                    <form action="../../src/Controllers/FineController.php?action=charge" method="POST" class="space-y-4">
                                        <input type="hidden" name="user_id" value="<?= $user['id_utente'] ?>">
                                        <div class="flex gap-3">
                                            <input type="number" step="0.01" name="amount" placeholder="Importo €"
                                                   class="w-1/2 bg-white border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-rose-500" required>
                                            <select name="causa" class="w-1/2 bg-white border border-slate-200 rounded-xl p-3 font-semibold outline-none focus:ring-2 focus:ring-rose-500">
                                                <option value="DANNI">Danni Libro</option>
                                                <option value="RITARDO">Ritardo Grave</option>
                                            </select>
                                        </div>
                                        <textarea name="commento" placeholder="Specificare causale (es. copertina macchiata)..."
                                                  class="w-full bg-white border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-rose-500" rows="2" required></textarea>
                                        <button type="submit" class="w-full bg-slate-800 text-white py-3 rounded-xl font-bold hover:bg-slate-900 transition">
                                            Aggiungi al Conto
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Lista Multe Pendenti -->
                        <div class="mt-12">
                            <h4 class="font-bold text-slate-800 mb-6">Dettaglio Pendenze Attive</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                    <tr class="text-left border-b border-slate-100">
                                        <th class="pb-4 text-xs font-black text-slate-400 uppercase tracking-widest">Data</th>
                                        <th class="pb-4 text-xs font-black text-slate-400 uppercase tracking-widest">Causale</th>
                                        <th class="pb-4 text-xs font-black text-slate-400 uppercase tracking-widest">Descrizione</th>
                                        <th class="pb-4 text-right text-xs font-black text-slate-400 uppercase tracking-widest">Importo</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 text-sm">
                                    <?php if(empty($fines)): ?>
                                        <tr>
                                            <td colspan="4" class="py-6 text-center text-slate-400 italic">Nessuna multa pendente per questo utente.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach($fines as $fine): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="py-4 text-slate-500"><?= date('d/m/Y', strtotime($fine['data_creazione'])) ?></td>
                                            <td class="py-4 font-bold text-indigo-600"><?= $fine['causa'] ?></td>
                                            <td class="py-4 text-slate-600"><?= htmlspecialchars($fine['commento']) ?></td>
                                            <td class="py-4 text-right font-black text-slate-800"><?= number_format($fine['importo'], 2) ?> €</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <!-- Colonna Sidebar (1/4) - Debitori Critici (Epic 10.6) -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-900 mb-6 flex items-center gap-2">
                    <i class="fas fa-triangle-exclamation text-rose-500"></i>
                    Debitori Critici
                </h2>
                <div class="space-y-4">
                    <?php if(empty($debtors)): ?>
                        <p class="text-center py-6 text-slate-400 text-sm italic">Nessuna pendenza rilevata.</p>
                    <?php endif; ?>
                    <?php foreach($debtors as $d): ?>
                        <a href="?user_id=<?= $d['id_utente'] ?>" class="block group">
                            <div class="p-4 rounded-2xl bg-slate-50 border border-transparent group-hover:border-rose-200 group-hover:bg-rose-50 transition-all flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-bold text-slate-800 group-hover:text-rose-900"><?= $d['nome'] ?> <?= substr($d['cognome'], 0, 1) ?>.</p>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">ID: <?= $d['id_utente'] ?></p>
                                </div>
                                <span class="font-black text-rose-600 group-hover:scale-110 transition"><?= number_format($d['debito'], 2) ?> €</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-indigo-600 p-6 rounded-3xl shadow-lg shadow-indigo-200 text-white">
                <h3 class="font-bold mb-2">Supporto Amministrativo</h3>
                <p class="text-sm text-indigo-100 mb-4 opacity-80 leading-relaxed">
                    I dati qui visualizzati sono sincronizzati con il database centrale della segreteria.
                </p>
                <div class="flex items-center gap-2 text-xs font-bold bg-white/10 p-3 rounded-xl border border-white/10">
                    <i class="fas fa-info-circle"></i>
                    Ultimo aggiornamento: Oggi
                </div>
            </div>
        </div>

    </div>
</main>

<footer class="max-w-7xl mx-auto px-6 py-10 text-center border-t border-slate-200 mt-20">
    <p class="text-slate-400 text-sm font-medium">&copy; 2026 StackMasters Library Management System - Modulo Finanza</p>
</footer>

</body>
</html>