<?php

// ?? è detto coalescence operator
// se (dato) esiste e non è nullo, bene, altrimenti quello di destra
// --> vorrei una coca cola, se non l'avete un'acqua

return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname' => $_ENV['DB_NAME'] ?? 'biblioteca_db',
    'user' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
];