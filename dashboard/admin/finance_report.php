<?php
/**
 * Vista: Report Contabile per la Segreteria
 * Percorso: dashboard/admin/finance_report.php
 * Riceve $data dal FineController (metodo report)
 */

$report = $data['report'] ?? [];
$startDate = $data['start'] ?? date('Y-m-01');
$endDate = $data['end'] ?? date('Y-m-d');

// Calcolo del totale complessivo del periodo
$grandTotal = 0;
foreach ($report as $row) {
    $grandTotal += $row['incasso'] ?? $row['totale_incassato'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Contabile - StackMasters</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .print-shadow { shadow: none; border: 1px solid #eee; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<!-- Navigazione (Nascosta in stampa) -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-10 no-print">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-invoice-dollar text-white text-sm"></i>
            </div>
            <span class="font-bold text-slate-800 tracking-tight">StackMasters <span class="text-indigo-600">Accounting</span></span>
        </div>
        <nav class="flex items-center gap-4">
            <a href="finance.php" class="text-sm font-bold text-slate-600 hover:text-indigo-600 transition">
                <i class="fas fa-chevron-left mr-2"></i>Gestione Multe
            </a>
        </nav>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-10">

    <!-- Header Report -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-10">
        <div>
            <span class="text-indigo-600 font-black text-xs uppercase tracking-[0.3em] mb-2 block">Documentazione Interna</span>
            <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight">Rendicontazione Incassi</h1>
            <p class="text-slate-500 mt-1">Report aggregato per la segreteria amministrativa.</p>
        </div>
        <div class="flex gap-3 no-print">
            <button onclick="window.print()" class="bg-slate-800 text-white px-6 py-3 rounded-2xl font-bold hover:bg-slate-900 transition flex items-center gap-2 shadow-lg shadow-slate-200">
                <i class="fas fa-print"></i> Stampa Report
            </button>
        </div>
    </div>

    <!-- Filtri Periodo (Nascosti in stampa) -->
    <section class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 mb-8 no-print">
        <form action="" method="GET" class="flex flex-wrap items-end gap-6">
            <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Data Inizio</label>
                <input type="date" name="start" value="<?= $startDate ?>"
                       class="block bg-slate-50 border-transparent rounded-xl p-3 outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700">
            </div>
            <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Data Fine</label>
                <input type="date" name="end" value="<?= $endDate ?>"
                       class="block bg-slate-50 border-transparent rounded-xl p-3 outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700">
            </div>
            <button type="submit" class="bg-indigo-50 text-indigo-600 px-8 py-3 rounded-xl font-bold hover:bg-indigo-100 transition active:scale-95">
                Aggiorna Dati
            </button>
        </form>
    </section>

    <!-- Riepilogo Statistico -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-indigo-600 p-8 rounded-3xl text-white shadow-xl shadow-indigo-100">
            <span class="text-[10px] font-black uppercase tracking-widest opacity-60 block mb-2">Totale Incassato</span>
            <span class="text-4xl font-black italic">€ <?= number_format($grandTotal, 2) ?></span>
        </div>
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Periodo Riferimento</span>
            <span class="text-xl font-bold text-slate-700">Dal <?= date('d/m/y', strtotime($startDate)) ?> al <?= date('d/m/y', strtotime($endDate)) ?></span>
        </div>
        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Giorni Operativi</span>
            <span class="text-xl font-bold text-slate-700"><?= count($report) ?> Giorni con transazioni</span>
        </div>
    </div>

    <!-- Tabella Dati -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden print-shadow">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="bg-slate-50">
                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Data Operazione</th>
                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Numero Transazioni</th>
                <th class="px-8 py-5 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Incasso Giornaliero</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if(empty($report)): ?>
                <tr>
                    <td colspan="3" class="px-8 py-12 text-center text-slate-400 italic font-medium">
                        <i class="fas fa-folder-open mb-3 text-3xl block opacity-20"></i>
                        Nessun movimento finanziario registrato nel periodo selezionato.
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach($report as $row): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-8 py-5 font-bold text-slate-700 italic">
                        <?= date('l d F Y', strtotime($row['data'] ?? $row['data_operazione'])) ?>
                    </td>
                    <td class="px-8 py-5 text-slate-500">
                            <span class="bg-slate-100 px-3 py-1 rounded-full text-xs font-bold text-slate-600">
                                <?= $row['operazione'] ?? $row['num_transazioni'] ?> pagamenti
                            </span>
                    </td>
                    <td class="px-8 py-5 text-right font-black text-indigo-600 text-lg">
                        + <?= number_format($row['totale'] ?? $row['totale_incassato'], 2) ?> €
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr class="bg-slate-50/50">
                <td colspan="2" class="px-8 py-6 font-black text-slate-800 text-lg uppercase tracking-tight text-right">Saldo Finale Periodo</td>
                <td class="px-8 py-6 text-right font-black text-slate-900 text-2xl tracking-tighter italic border-l border-slate-100">
                    € <?= number_format($grandTotal, 2) ?>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>

    <!-- Footer per la stampa -->
    <div class="mt-12 hidden print:block border-t border-dashed border-slate-300 pt-8">
        <div class="flex justify-between">
            <div class="text-sm text-slate-500 italic">
                Generato automaticamente dal sistema StackMasters<br>
                Data di generazione: <?= date('d/m/Y H:i') ?>
            </div>
            <div class="text-center">
                <div class="w-48 border-b border-slate-400 mb-2"></div>
                <span class="text-[10px] font-bold uppercase text-slate-400">Firma del Bibliotecario</span>
            </div>
        </div>
    </div>

</main>

<footer class="max-w-5xl mx-auto px-6 py-10 text-center text-slate-400 text-xs no-print">
    &copy; 2026 StackMasters Library Management System - Area Amministrazione e Finanza
</footer>

</body>
</html>