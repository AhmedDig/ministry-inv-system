// notifications.js — Polling client for bell + dropdown
(function () {
    const POLL_INTERVAL = 30000;
    let pollTimer = null;
    let isOpen = false;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function categoryIcon(cat) {
        const icons = {
            ticket: 'fa-ticket-alt',
            consumable: 'fa-cubes',
            user: 'fa-user',
            asset: 'fa-box',
            entity: 'fa-users',
            system: 'fa-cog',
            security: 'fa-shield-alt',
            broadcast: 'fa-bullhorn',
        };
        return `<i class="fas ${icons[cat] || 'fa-bell'}"></i>`;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const bell = document.getElementById('notifications-btn');
        const badge = document.getElementById('notifications-badge');
        const dropdown = document.getElementById('notifications-dropdown');
        if (!bell || !badge || !dropdown) return;

        const list = dropdown.querySelector('.notifications-list');
        const markAll = dropdown.querySelector('.notifications-mark-all');

        function fetchCounts() {
            fetch('index.php?action=api_get_notification_counts', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const total = data.counts.total || 0;
                    const urgent = data.counts.urgent || 0;
                    if (total > 0) {
                        badge.textContent = total > 99 ? '99+' : total;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                    bell.classList.toggle('has-urgent', urgent > 0);
                })
                .catch(() => { });
        }

        function loadDropdown() {
            list.innerHTML = '<div class="notifications-loading">جاري التحميل...</div>';
            fetch('index.php?action=api_get_notifications&limit=10', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        list.innerHTML = '<div class="notifications-empty">خطأ في التحميل</div>';
                        return;
                    }
                    renderItems(data.notifications);
                })
                .catch(() => {
                    list.innerHTML = '<div class="notifications-empty">خطأ في الاتصال</div>';
                });
        }

        function renderItems(items) {
            if (!items.length) {
                list.innerHTML = '<div class="notifications-empty">لا توجد إشعارات</div>';
                return;
            }
            list.innerHTML = items.map(n => {
                const dateStr = new Date(n.created_at).toLocaleString('ar-EG', {
                    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                });
                const classes = [
                    'notification-item',
                    'severity-' + n.severity,
                    n.is_read == 0 ? 'unread' : 'read',
                    n.reminder_count > 0 ? 'urgent' : '',
                ].filter(Boolean).join(' ');

                return `
                    <div class="${classes}"
                         data-delivery="${n.delivery_id}"
                         data-url="${escapeHtml(n.action_url || '')}">
                        <div class="notification-icon">${categoryIcon(n.category)}</div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(n.title)}</div>
                            ${n.body ? `<div class="notification-body">${escapeHtml(String(n.body).substring(0, 80))}</div>` : ''}
                            <div class="notification-date">
                                ${dateStr}
                                ${n.reminder_count > 0 ? `<span class="reminder-pill">🔔 ${n.reminder_count}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            list.querySelectorAll('.notification-item').forEach(el => {
                el.addEventListener('click', () => {
                    const deliveryId = el.dataset.delivery;
                    const url = el.dataset.url;
                    markRead(deliveryId).then(() => {
                        if (url) window.location.href = url;
                        else closeDropdown();
                    });
                });
            });
        }

        function markRead(deliveryId) {
            return fetch('index.php?action=api_mark_notification_read', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'delivery_id=' + encodeURIComponent(deliveryId)
            }).then(r => r.json()).then(() => fetchCounts()).catch(() => { });
        }

        function openDropdown() {
            dropdown.classList.add('show');
            isOpen = true;
            loadDropdown();
        }

        function closeDropdown() {
            dropdown.classList.remove('show');
            isOpen = false;
        }

        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            if (isOpen) closeDropdown();
            else openDropdown();
        });

        document.addEventListener('click', function (e) {
            if (isOpen && !dropdown.contains(e.target) && !bell.contains(e.target)) {
                closeDropdown();
            }
        });

        if (markAll) {
            markAll.addEventListener('click', function (e) {
                e.preventDefault();
                fetch('index.php?action=api_mark_all_notifications_read', {
                    method: 'POST',
                    credentials: 'same-origin'
                }).then(() => { fetchCounts(); loadDropdown(); });
            });
        }

        fetchCounts();
        pollTimer = setInterval(fetchCounts, POLL_INTERVAL);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                clearInterval(pollTimer);
            } else {
                fetchCounts();
                pollTimer = setInterval(fetchCounts, POLL_INTERVAL);
            }
        });
    });
})();