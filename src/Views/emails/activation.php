<!DOCTYPE html>
<html>
<head>
    <style>
        .email-container { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .header { background-color: #0056b3; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .btn { display: inline-block; background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; }
        .footer { background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h2>Benvenuto in Biblioteca!</h2>
    </div>
    <div class="content">
        <p>Ciao <strong><?= htmlspecialchars($userName ?? 'Utente') ?></strong>,</p>

        <p>Grazie per esserti registrato al portale della Biblioteca ITIS Rossi.</p>
        <p>Per completare la creazione del tuo account e iniziare a prenotare i libri, ti preghiamo di confermare il tuo indirizzo email cliccando sul pulsante qui sotto:</p>

        <div style="text-align: center;">
            <a href="<?= $activationLink ?? '#' ?>" class="btn">Attiva Account</a>
        </div>

        <p>Se il pulsante non funziona, copia e incolla questo link nel tuo browser:</p>
        <p><small><a href="<?= $activationLink ?? '#' ?>"><?= $activationLink ?? 'Link non disponibile' ?></a></small></p>

        <p>Il link scadrà tra 24 ore.</p>
    </div>
    <div class="footer">
        &copy; <?= date('Y') ?> Biblioteca Scolastica ITIS Rossi. Se non hai richiesto questa mail, ignorala.
    </div>
</div>
</body>
</html>