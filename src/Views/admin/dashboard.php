<?php
$kpi = $data['kpi'] ?? [];
// Dati trend formattati per Chart.js (labels e data)
$trendData = $data['trendData'] ?? ['labels' => [], 'data' => []];
$topLibri = $data['topLibri'] ?? [];
$message = $data['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - KPI Biblioteca</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb;
        }
    </style>

    <!-- 🛑 INCLUSIONE DI CHART.JS TRAMITE CDN (NECESSARIA PER IL GRAFICO) 🛑 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

</head>
<body class="p-4 sm:p-8">

<div class="max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8 flex items-center">
        <i class="fas fa-chart-line text-green-600 mr-3"></i>
        Dashboard Amministrativa (KPI)
    </h1>

    <!-- MESSAGGI -->
    <?php if ($message): ?>
        <div class="p-3 mb-6 bg-indigo-100 text-indigo-700 rounded-lg"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- SEZIONE KPI (Key Performance Indicators) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">

        <!-- KPI 1: Prestiti Attivi -->
        <div class="bg-white p-5 rounded-xl shadow-lg border-l-4 border-indigo-500">
            <p class="text-sm font-medium text-gray-500">Prestiti Attivi</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $kpi['prestitiAttivi'] ?? 0; ?></p>
        </div>

        <!-- KPI 2: Copie Fisiche -->
        <div class="bg-white p-5 rounded-xl shadow-lg border-l-4 border-blue-500">
            <p class="text-sm font-medium text-gray-500">Copie Totali</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $kpi['copieFisiche'] ?? 0; ?></p>
        </div>

        <!-- KPI 3: Prestiti Scaduti (Allarme) -->
        <?php
        $alert_class = ($kpi['prestitiScaduti'] ?? 0) > 30 ? 'border-red-500 text-red-700' : 'border-green-500 text-green-700';
        ?>
        <div class="bg-white p-5 rounded-xl shadow-lg border-l-4 <?php echo $alert_class; ?>">
            <p class="text-sm font-medium text-gray-500">Scaduti/In Ritardo</p>
            <p class="text-3xl font-bold mt-1"><?php echo $kpi['prestitiScaduti'] ?? 0; ?></p>
        </div>

        <!-- KPI 4: Utenti Registrati -->
        <div class="bg-white p-5 rounded-xl shadow-lg border-l-4 border-yellow-500">
            <p class="text-sm font-medium text-gray-500">Utenti Totali</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $kpi['utentiRegistrati'] ?? 0; ?></p>
        </div>
    </div>

    <!-- SEZIONE GRAFICI (Epic 10.2) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Grafico 1: Trend Prestiti -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Trend Prestiti Ultimi 12 Mesi</h2>
            <div class="p-4 bg-gray-50 rounded-lg">
                <!-- CANVAS PER CHART.JS -->
                <canvas id="loanTrendChart" height="150"></canvas>
            </div>
        </div>

        <!-- Tabella 1: Top 10 Libri Prestati -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Top 10 Libri (Mese)</h2>
            <ul class="divide-y divide-gray-200">
                <?php if (!empty($topLibri)): ?>
                    <?php foreach ($topLibri as $i => $libro): ?>
                        <li class="py-3 flex justify-between items-center">
                            <span class="font-medium text-gray-700"><?php echo ($i + 1) . '. ' . htmlspecialchars($libro['titolo']); ?></span>
                            <span class="px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800 rounded-full">
                                    <?php echo $libro['prestiti']; ?> Prestiti
                                </span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-gray-500 text-sm">Nessun dato disponibile.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- SCRIPT DI INIZIALIZZAZIONE CHART.JS -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Recupero i dati PHP passati dal Controller, li converto in JavaScript
        const trendData = <?php echo json_encode($trendData); ?>;

        if (trendData.labels && trendData.labels.length > 0) {
            const ctx = document.getElementById('loanTrendChart');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trendData.labels,
                    datasets: [{
                        label: 'Numero Prestiti',
                        data: trendData.data,
                        borderColor: '#4f46e5', // Colore Viola Indigo-600
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        tension: 0.4, // Curva morbida
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Prestiti'
                            },
                            ticks: {
                                // Assicura che i conteggi siano numeri interi
                                stepSize: 1,
                                callback: function (value) {
                                    if (value % 1 === 0) return value;
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    }
                }
            });
        } else {
            // Messaggio di fallback se non ci sono dati
            const chartArea = document.getElementById('loanTrendChart').parentElement;
            chartArea.innerHTML = '<p class="text-center text-gray-500 py-4">Nessun dato prestito disponibile per l\'ultimo anno.</p>';
            chartArea.classList.remove('bg-gray-50'); // Rimuove il colore di sfondo se necessario
        }
    });
</script>
</body>
</html>