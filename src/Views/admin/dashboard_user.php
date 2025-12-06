<?php
// FILE: views/user/dashboard_prestiti.php
// Questa View mostra i prestiti attivi, la scadenza e il pulsante Rinnova (5.12).

$prestiti = $data['prestiti'] ?? [];
$message = $data['message'] ?? '';
$message_type = $data['message_type'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Mia Dashboard - Biblioteca</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fb; }
    </style>
</head>
<body class="p-4 sm:p-8">

<div class="max-w-4xl mx-auto">
    <!-- Layout condiviso: Qui andrebbe incluso header.php (views/layouts/header.php) -->

    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center">
        <i class="fas fa-home text-indigo-600 mr-3"></i>
        La Mia Dashboard Biblioteca
    </h1>
    <p class="text-gray-600 mb-8">
        Visualizza i tuoi prestiti attivi e gestisci i rinnovi.
    </p>

    <!-- AREA MESSAGGI -->
    <?php if ($message): ?>
        <div id="alert-message" class="p-4 mb-8 rounded-lg font-medium text-sm
                <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>"
             role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <h2 class="text-2xl font-semibold text-gray-700 mb-4">I Miei Prestiti Attivi</h2>

    <?php if (empty($prestiti)): ?>
        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 text-center text-gray-500">
            <i class="fas fa-book-open fa-3x mb-3 text-gray-300"></i>
            <p>Non hai prestiti attivi al momento. Buona lettura!</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($prestiti as $prestito): ?>
                <?php
                // Calcolo stato (logica display)
                $giorni_rimanenti = (int)$prestito['giorni_rimanenti'];
                $is_overdue = $giorni_rimanenti < 0;
                $is_due_soon = $giorni_rimanenti >= 0 && $giorni_rimanenti <= 3;
                $already_renewed = (int)$prestito['rinnovi'] > 0;

                // Il rinnovo è consentito se: non è scaduto E non è già stato rinnovato.
                $can_renew = !$is_overdue && !$already_renewed;

                // Classi CSS per feedback visivo
                $card_class = $is_overdue ? 'border-red-500 bg-red-50' : ($is_due_soon ? 'border-yellow-500 bg-yellow-50' : 'border-indigo-200 bg-white');
                $text_class = $is_overdue ? 'text-red-700' : 'text-gray-900';
                $due_text = $is_overdue ? 'SCADUTO DA ' . abs($giorni_rimanenti) . ' GG' : ($is_due_soon ? 'SCADE TRA ' . $giorni_rimanenti . ' GG' : 'OK');
                ?>

                <div class="p-4 rounded-xl shadow-md flex flex-col md:flex-row justify-between items-center border-l-4 <?php echo $card_class; ?>">

                    <div class="flex-1 min-w-0 mb-3 md:mb-0">
                        <p class="text-lg font-semibold <?php echo $text_class; ?>">
                            <i class="fas fa-book mr-2"></i><?php echo htmlspecialchars($prestito['titolo']); ?>
                        </p>
                        <p class="text-sm text-gray-600 truncate">
                            Autore: <?php echo htmlspecialchars($prestito['autore']); ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            ID Prestito: #<?php echo $prestito['id_prestito']; ?> | Rinnovi: <?php echo $prestito['rinnovi']; ?>/1
                        </p>
                    </div>

                    <div class="text-right md:ml-4 flex items-center space-x-4">
                        <!-- Countdown e Scadenza -->
                        <div class="text-sm font-medium">
                            <p class="<?php echo $text_class; ?>"><?php echo $due_text; ?></p>
                            <p class="text-xs text-gray-500">
                                Scadenza: <?php echo date('d/m/Y', strtotime($prestito['scadenza_prestito'])); ?>
                            </p>
                        </div>

                        <!-- Pulsante Rinnova (Sub-issue 5.12) -->
                        <?php if ($can_renew): ?>
                            <form method="POST" action="/utente/rinnova" class="inline-block" onsubmit="return confirm('Sei sicuro di voler rinnovare questo prestito? Verrà consumato l\'unico rinnovo disponibile.');">
                                <input type="hidden" name="prestito_id" value="<?php echo $prestito['id_prestito']; ?>">
                                <button type="submit"
                                        class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-150 shadow-md">
                                    <i class="fas fa-redo mr-1"></i> Rinnova
                                </button>
                            </form>
                        <?php else: ?>
                            <button disabled class="bg-gray-300 text-gray-600 text-sm px-4 py-2 rounded-lg cursor-not-allowed shadow-inner">
                                <?php echo $already_renewed ? 'Già Rinnovato' : 'Non Disponibile'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Qui andrebbe incluso footer.php (views/layouts/footer.php) -->

</body>
</html>