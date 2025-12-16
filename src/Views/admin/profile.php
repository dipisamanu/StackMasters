<?php
// FILE: src/Views/admin/profile.php
// Pagina Profilo Utente con menu laterale per navigare tra sezioni
//if (!isset($_SESSION['user_id'])) {
//    header('Location: /login');
//}

$utente = $data['utente'] ?? [];
$ruoli = $data['ruoli'] ?? [];
$prestiti_attivi = $data['prestiti_attivi'] ?? [];
$message = $data['message'] ?? '';
$message_type = $data['message_type'] ?? '';
$active_section = $data['active_section'] ?? 'profile'; // 'profile' o 'loans'
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Utente - Biblioteca</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fb; }
    </style>
</head>
<body class="p-4 sm:p-8">

<!-- CONTENITORE PRINCIPALE -->
<div class="max-w-7xl mx-auto">

    <!-- SIDEBAR MENU -->
    <div class="flex flex-col lg:flex-row gap-6">
        <aside class="w-full lg:w-64 bg-white rounded-xl shadow-lg p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-user-circle text-indigo-600 mr-3"></i>
                    Menu Profilo
                </h2>
            </div>

            <nav class="space-y-1">
                <!-- Link Profilo -->
                <a href="/utente/profilo?section=profile"
                   class="flex items-center px-4 py-3 rounded-lg transition duration-150 ease-in-out <?php echo $active_section === 'profile' ? 'bg-indigo-100 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-id-card mr-3"></i>
                    Dati Personali
                </a>

                <!-- Link Prestiti -->
                <a href="/utente/profilo?section=loans"
                   class="flex items-center px-4 py-3 rounded-lg transition duration-150 ease-in-out <?php echo $active_section === 'loans' ? 'bg-indigo-100 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-book-open mr-3"></i>
                    Prestiti Attivi
                    <?php if (!empty($prestiti_attivi)): ?>
                        <span class="ml-auto bg-red-600 text-white text-xs font-bold rounded-full px-2 py-1">
                            <?php echo count($prestiti_attivi); ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-4"></div>

                <!-- Logout -->
                <a href="/logout"
                   class="flex items-center px-4 py-3 rounded-lg transition duration-150 ease-in-out text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Esci
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1">

            <!-- AREA MESSAGGI -->
            <?php if ($message): ?>
                <div id="alert-message" class="p-4 mb-8 rounded-lg font-medium text-sm
                        <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>"
                     role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- SEZIONE DATI PERSONALI -->
            <?php if ($active_section === 'profile'): ?>
                <h1 class="text-3xl font-bold text-gray-800 mb-8 flex items-center">
                    <i class="fas fa-user text-indigo-600 mr-3"></i>
                    I Miei Dati Personali
                </h1>

                <?php if (empty($utente)): ?>
                    <div class="bg-red-50 border border-red-300 text-red-700 p-4 rounded-lg">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Impossibile caricare i dati utente. Effettua il login.
                    </div>
                <?php else: ?>

                    <!-- Card Informazioni Principali -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                            Informazioni Anagrafiche
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Nome -->
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Nome</p>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($utente['nome']); ?></p>
                            </div>

                            <!-- Cognome -->
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Cognome</p>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($utente['cognome']); ?></p>
                            </div>

                            <!-- Username -->
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Username</p>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($utente['username']); ?></p>
                            </div>

                            <!-- Email -->
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Email</p>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($utente['email']); ?></p>
                                <?php if ($utente['email_verificata']): ?>
                                    <span class="inline-block mt-1 text-xs bg-green-100 text-green-700 px-2 py-1 rounded">
                                        <i class="fas fa-check-circle mr-1"></i>Verificata
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block mt-1 text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">
                                        <i class="fas fa-exclamation-circle mr-1"></i>Non verificata
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Codice Fiscale -->
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Codice Fiscale</p>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($utente['cf']); ?></p>
                            </div>

                            <!-- Data Nascita -->
                            <?php if (!empty($utente['data_nascita'])): ?>
                                <div class="p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-500 mb-1">Data di Nascita</p>
                                    <p class="text-lg font-semibold text-gray-900"><?php echo date('d/m/Y', strtotime($utente['data_nascita'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Sesso -->
                            <?php if (!empty($utente['sesso'])): ?>
                                <div class="p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-500 mb-1">Sesso</p>
                                    <p class="text-lg font-semibold text-gray-900"><?php echo $utente['sesso'] === 'M' ? 'Maschio' : 'Femmina'; ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Comune Nascita -->
                            <?php if (!empty($utente['comune_nascita'])): ?>
                                <div class="p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-500 mb-1">Comune di Nascita</p>
                                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($utente['comune_nascita']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Ruoli -->
                    <?php if (!empty($ruoli)): ?>
                        <div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                                <i class="fas fa-shield-alt text-indigo-600 mr-2"></i>
                                I Miei Ruoli
                            </h2>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($ruoli as $ruolo): ?>
                                    <span class="inline-flex items-center px-4 py-2 bg-indigo-50 text-indigo-700 font-semibold rounded-lg border border-indigo-200">
                                        <?php
                                        // Icone diverse per ruolo
                                        $icon = match($ruolo['nome']) {
                                            'Admin' => 'fa-crown',
                                            'Bibliotecario' => 'fa-user-tie',
                                            'Docente' => 'fa-chalkboard-teacher',
                                            'Studente' => 'fa-user-graduate',
                                            default => 'fa-user'
                                        };
                                        ?>
                                        <i class="fas <?php echo $icon; ?> mr-2"></i>
                                        <?php echo htmlspecialchars($ruolo['nome']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card XP e Gamification (se presente) -->
                    <?php if (isset($utente['livello_xp'])): ?>
                        <div class="bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl shadow-lg p-6 text-white">
                            <h2 class="text-xl font-semibold mb-4">
                                <i class="fas fa-star mr-2"></i>
                                Livello XP
                            </h2>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-4xl font-bold"><?php echo $utente['livello_xp']; ?></p>
                                    <p class="text-sm opacity-90 mt-1">Punti Esperienza</p>
                                </div>
                                <div class="text-6xl opacity-80">
                                    <i class="fas fa-trophy"></i>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card Data Creazione -->
                    <?php if (!empty($utente['data_creazione'])): ?>
                        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                                Informazioni Account
                            </h2>
                            <p class="text-gray-600">
                                <i class="fas fa-calendar-alt text-indigo-600 mr-2"></i>
                                Membro dal: <span class="font-semibold"><?php echo date('d/m/Y', strtotime($utente['data_creazione'])); ?></span>
                            </p>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            <!-- SEZIONE PRESTITI ATTIVI -->
            <?php elseif ($active_section === 'loans'): ?>
                <h1 class="text-3xl font-bold text-gray-800 mb-8 flex items-center">
                    <i class="fas fa-book-open text-indigo-600 mr-3"></i>
                    I Miei Prestiti Attivi
                </h1>

                <?php if (empty($prestiti_attivi)): ?>
                    <div class="bg-white p-8 rounded-xl shadow-lg border border-gray-200 text-center">
                        <i class="fas fa-book-open fa-4x mb-4 text-gray-300"></i>
                        <p class="text-lg text-gray-700 font-medium">Non hai prestiti attivi al momento.</p>
                        <p class="text-sm text-gray-500 mt-2">Visita il catalogo per scoprire nuovi libri!</p>
                        <a href="/catalogo" class="mt-4 inline-block bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition duration-150 ease-in-out">
                            <i class="fas fa-search mr-2"></i>Vai al Catalogo
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($prestiti_attivi as $prestito): ?>
                            <?php
                            // Calcolo stato
                            $giorni_rimanenti = (int)$prestito['giorni_rimanenti'];
                            $is_overdue = $giorni_rimanenti < 0;
                            $is_due_soon = $giorni_rimanenti >= 0 && $giorni_rimanenti <= 3;

                            // Classi CSS per feedback visivo
                            $card_class = $is_overdue ? 'border-red-500 bg-red-50' : ($is_due_soon ? 'border-yellow-500 bg-yellow-50' : 'border-indigo-200 bg-white');
                            $text_class = $is_overdue ? 'text-red-700' : 'text-gray-900';
                            $badge_class = $is_overdue ? 'bg-red-600' : ($is_due_soon ? 'bg-yellow-600' : 'bg-green-600');
                            $due_text = $is_overdue ? 'SCADUTO DA ' . abs($giorni_rimanenti) . ' GIORNI' : ($is_due_soon ? 'SCADE TRA ' . $giorni_rimanenti . ' GIORNI' : 'Restituisci entro ' . $giorni_rimanenti . ' giorni');
                            ?>

                            <div class="bg-white p-5 rounded-xl shadow-md flex flex-col md:flex-row justify-between items-start md:items-center border-l-4 <?php echo $card_class; ?>">

                                <div class="flex-1 min-w-0 mb-4 md:mb-0">
                                    <h3 class="text-lg font-bold <?php echo $text_class; ?>">
                                        <i class="fas fa-book mr-2"></i>
                                        <?php echo htmlspecialchars($prestito['titolo']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-user-edit mr-1"></i>
                                        Autore: <?php echo htmlspecialchars($prestito['autore'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="fas fa-hashtag mr-1"></i>
                                        ID Prestito: #<?php echo $prestito['id_prestito']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-calendar mr-1"></i>
                                        Data Prestito: <?php echo date('d/m/Y', strtotime($prestito['data_prestito'])); ?>
                                    </p>
                                </div>

                                <div class="text-right md:ml-6">
                                    <!-- Badge Scadenza -->
                                    <div class="inline-block <?php echo $badge_class; ?> text-white text-sm font-bold px-3 py-1.5 rounded-lg mb-2">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo $due_text; ?>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        Scadenza: <?php echo date('d/m/Y', strtotime($prestito['scadenza_prestito'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Legenda -->
                    <div class="mt-6 bg-white p-4 rounded-lg border border-gray-200">
                        <h3 class="font-semibold text-gray-700 mb-3">Legenda:</h3>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <div class="flex items-center">
                                <span class="w-3 h-3 bg-green-600 rounded mr-2"></span>
                                <span class="text-gray-600">Nei Tempi</span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-3 h-3 bg-yellow-600 rounded mr-2"></span>
                                <span class="text-gray-600">In Scadenza (≤3 giorni)</span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-3 h-3 bg-red-600 rounded mr-2"></span>
                                <span class="text-gray-600">Scaduto</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

</body>
</html>
