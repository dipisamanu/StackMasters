/**
 * Gestione Notifiche (Versione Blindata per XAMPP)
 * File: public/assets/js/notification.js
 */

document.addEventListener('DOMContentLoaded', function() {
    const badge = document.getElementById('notification-badge');
    const list = document.getElementById('notification-list');

    // === CONFIGURAZIONE MANUALE PER XAMPP ===
    // Scriviamo qui il nome della cartella del progetto.
    // Così siamo sicuri al 100% che il link venga generato giusto.
    const PROJECT_FOLDER = '/StackMasters';
    const API_URL = '/StackMasters/public/api/get_notifiche.php';
    // ========================================

    if (!badge || !list) return;

    function checkNotifiche() {
        // Usiamo l'URL assoluto hardcoded
        fetch(API_URL)
            .then(res => res.json())
            .then(data => {
                if (data.success) {

                    // 1. Badge
                    if (data.unread > 0) {
                        badge.style.display = 'block';
                        badge.innerText = data.unread > 99 ? '99+' : data.unread;
                    } else {
                        badge.style.display = 'none';
                    }

                    // 2. Lista
                    let html = '<li><h6 class="dropdown-header text-uppercase text-muted small fw-bold">Centro Notifiche</h6></li><li><hr class="dropdown-divider my-0"></li>';

                    if (!data.notifications || data.notifications.length === 0) {
                        html += '<li class="text-center py-3 text-muted small">Nessuna notifica recente</li>';
                    } else {
                        data.notifications.forEach(notif => {
                            let iconClass = 'fa-info-circle text-primary';
                            if(notif.tipo === 'WARNING') iconClass = 'fa-exclamation-triangle text-warning';
                            if(notif.tipo === 'DANGER') iconClass = 'fa-times-circle text-danger';
                            if(notif.tipo === 'SUCCESS') iconClass = 'fa-check-circle text-success';
                            let bgClass = notif.letto == 0 ? 'bg-light' : '';

                            // === FIX LINK DEFINITIVO ===
                            let rawLink = notif.link_azione || '#';
                            let finalLink = rawLink;

                            // Se il link inizia con / e NON contiene già il nome del progetto
                            if (rawLink.startsWith('/') && !rawLink.includes(PROJECT_FOLDER)) {
                                finalLink = PROJECT_FOLDER + rawLink;
                            }
                            // ===========================

                            html += `
                            <li>
                                <a class="dropdown-item d-flex align-items-start p-2 ${bgClass}" href="${finalLink}" onclick="markAsRead(${notif.id_notifica})">
                                    <i class="fas ${iconClass} mt-1 me-2"></i>
                                    <div>
                                        <strong class="d-block small text-dark">${notif.titolo}</strong>
                                        <span class="small text-muted text-wrap" style="font-size: 0.75rem; line-height: 1.2;">${notif.messaggio}</span>
                                        <div class="text-end text-muted" style="font-size: 0.65rem; margin-top:2px;">${new Date(notif.data_creazione).toLocaleDateString()}</div>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider m-0"></li>
                            `;
                        });
                    }
                    list.innerHTML = html;
                }
            })
            .catch(err => console.error("Errore AJAX:", err));
    }

    checkNotifiche();
    setInterval(checkNotifiche, 30000);

    window.markAsRead = function(id) {
        fetch(API_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        });
    };
});