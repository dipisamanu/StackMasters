document.addEventListener('DOMContentLoaded', function() {
    // Controlla subito
    checkNotifiche();
    // E poi ogni 30 secondi
    setInterval(checkNotifiche, 30000);
});

function checkNotifiche() {
    // Percorso assoluto dal web root
    fetch('/StackMasters/public/api/get_notifiche.php')
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                // Aggiorna il numerino sulla campanella
                const badge = document.getElementById('notification-badge');
                if (badge) {
                    badge.innerText = response.count;
                    badge.style.display = response.count > 0 ? 'block' : 'none';
                }
            }
        })
        .catch(err => console.error("Errore notifiche:", err));
}