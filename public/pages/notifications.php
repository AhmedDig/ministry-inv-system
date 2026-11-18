<?php
// pages/notifications.php

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

$logged_in = true;
$user_data = $_SESSION['user_data'];

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <h1 class="text-2xl font-bold" style="color: var(--clr-text);">
            <i class="fas fa-bell ml-2" style="color: var(--clr-primary);"></i> الإشعارات
        </h1>
        <div class="flex gap-2">
            <button id="mark-all-btn" class="btn-secondary" style="padding: 0.5rem 1rem;">
                <i class="fas fa-check-double ml-1"></i> تحديد الكل كمقروء
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-2 mb-4">
        <button class="filter-tab active" data-filter="all" onclick="setFilter('all')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent;">
            الكل
        </button>
        <button class="filter-tab" data-filter="unread" onclick="setFilter('unread')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent;">
            غير مقروءة
        </button>
        <button class="filter-tab" data-filter="requires_action" onclick="setFilter('requires_action')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent;">
            تحتاج إجراء
        </button>
        <button class="filter-tab" data-filter="security" onclick="setFilter('security')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent;">
            أمني
        </button>
    </div>

    <div id="notifications-container"
        style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; overflow: hidden;">
        <div style="padding: 3rem; text-align: center; color: var(--clr-text-secondary);">
            <i class="fas fa-spinner fa-spin ml-2"></i> جاري التحميل...
        </div>
    </div>

    <div id="pagination-container" style="margin-top: 1rem; text-align: center;"></div>
</div>

<style>
    .notif-row {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-bottom: 1px solid var(--clr-card-border);
        cursor: pointer;
        transition: background 0.15s;
        align-items: flex-start;
    }

    .notif-row:hover {
        background: var(--clr-bg);
    }

    .notif-row.unread {
        background: rgba(59, 130, 246, 0.04);
    }

    .notif-row.urgent {
        border-right: 4px solid var(--clr-danger);
    }

    .notif-row .notif-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }

    .notif-row.severity-info .notif-icon {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
    }

    .notif-row.severity-success .notif-icon {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
    }

    .notif-row.severity-warning .notif-icon {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
    }

    .notif-row.severity-danger .notif-icon {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }

    .notif-body {
        flex: 1;
        min-width: 0;
    }

    .notif-title {
        color: var(--clr-text);
        font-weight: 600;
        margin-bottom: 0.25rem;
        font-size: 0.95rem;
    }

    .notif-text {
        color: var(--clr-text-secondary);
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
        white-space: pre-line;
    }

    .notif-meta {
        color: var(--clr-text-secondary);
        font-size: 0.75rem;
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .notif-meta .pill {
        background: var(--clr-bg);
        padding: 0.1rem 0.5rem;
        border-radius: 6px;
    }

    .notif-meta .pill-danger {
        background: var(--clr-danger);
        color: white;
    }

    .notif-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.75rem;
        flex-wrap: wrap;
    }
</style>

<script>
    const pageState = {
        filter: 'all',
        offset: 0,
        limit: 20,
        loading: false,
    };

    function categoryIcon(cat) {
        const icons = {
            ticket: 'fa-ticket-alt', consumable: 'fa-cubes', user: 'fa-user',
            asset: 'fa-box', entity: 'fa-users', system: 'fa-cog',
            security: 'fa-shield-alt', broadcast: 'fa-bullhorn',
        };
        return `<i class="fas ${icons[cat] || 'fa-bell'}"></i>`;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function setFilter(f) {
        pageState.filter = f;
        pageState.offset = 0;
        document.querySelectorAll('.filter-tab').forEach(tab => {
            const active = tab.dataset.filter === f;
            tab.classList.toggle('active', active);
            tab.style.color = active ? 'var(--clr-primary)' : 'var(--clr-text-secondary)';
            tab.style.borderBottomColor = active ? 'var(--clr-primary)' : 'transparent';
            tab.style.fontWeight = active ? '600' : 'normal';
        });
        load();
    }

    function buildQuery() {
        const params = new URLSearchParams();
        params.append('limit', pageState.limit);
        params.append('offset', pageState.offset);
        if (pageState.filter === 'unread') params.append('unread_only', '1');
        else if (pageState.filter === 'requires_action') params.append('requires_action', '1');
        else if (pageState.filter === 'security') params.append('category', 'security');
        return params.toString();
    }

    function load() {
        if (pageState.loading) return;
        pageState.loading = true;

        const container = document.getElementById('notifications-container');
        if (pageState.offset === 0) {
            container.innerHTML = '<div style="padding:3rem;text-align:center;color:var(--clr-text-secondary);"><i class="fas fa-spinner fa-spin ml-2"></i> جاري التحميل...</div>';
        }

        fetch('index.php?action=api_get_notifications&' + buildQuery(), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                pageState.loading = false;
                if (!data.success) {
                    container.innerHTML = '<div style="padding:3rem;text-align:center;color:var(--clr-danger);">خطأ في التحميل</div>';
                    return;
                }
                renderList(data.notifications, data.has_more);
            })
            .catch(() => {
                pageState.loading = false;
                container.innerHTML = '<div style="padding:3rem;text-align:center;color:var(--clr-danger);">خطأ في الاتصال</div>';
            });
    }

    function renderList(items, hasMore) {
        const container = document.getElementById('notifications-container');

        if (!items.length && pageState.offset === 0) {
            container.innerHTML = '<div style="padding:3rem;text-align:center;color:var(--clr-text-secondary);">لا توجد إشعارات</div>';
            document.getElementById('pagination-container').innerHTML = '';
            return;
        }

        const html = items.map(n => {
            const dateStr = new Date(n.created_at).toLocaleString('ar-EG');
            const classes = ['notif-row', 'severity-' + n.severity];
            if (n.is_read == 0) classes.push('unread');
            if (n.reminder_count > 0) classes.push('urgent');

            return `
            <div class="${classes.join(' ')}"
                 data-delivery="${n.delivery_id}"
                 data-url="${escapeHtml(n.action_url || '')}">
                <div class="notif-icon">${categoryIcon(n.category)}</div>
                <div class="notif-body">
                    <div class="notif-title">${escapeHtml(n.title)}</div>
                    ${n.body ? `<div class="notif-text">${escapeHtml(n.body)}</div>` : ''}
                    <div class="notif-meta">
                        <span>${dateStr}</span>
                        <span class="pill">${escapeHtml(n.category)}</span>
                        ${n.reminder_count > 0 ? `<span class="pill pill-danger">🔔 ${n.reminder_count} تذكير</span>` : ''}
                        ${n.requires_action == 1 && !n.resolved_at ? `<span class="pill" style="background: var(--clr-warning); color: white;">تحتاج إجراء</span>` : ''}
                    </div>
                </div>
            </div>
        `;
        }).join('');

        if (pageState.offset === 0) {
            container.innerHTML = html;
        } else {
            container.insertAdjacentHTML('beforeend', html);
        }

        container.querySelectorAll('.notif-row').forEach(el => {
            el.addEventListener('click', () => {
                const deliveryId = el.dataset.delivery;
                const url = el.dataset.url;
                fetch('index.php?action=api_mark_notification_read', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'delivery_id=' + encodeURIComponent(deliveryId)
                }).then(() => {
                    if (url) window.location.href = url;
                    else { el.classList.remove('unread'); }
                });
            });
        });

        const pag = document.getElementById('pagination-container');
        if (hasMore) {
            pag.innerHTML = `
            <button onclick="loadMore()" style="background:var(--clr-primary);color:white;border:none;padding:0.6rem 1.5rem;border-radius:6px;cursor:pointer;">
                تحميل المزيد
            </button>`;
        } else {
            pag.innerHTML = pageState.offset === 0 ? '' : '<span style="color:var(--clr-text-secondary);font-size:0.85rem;">انتهت القائمة</span>';
        }
    }

    function loadMore() {
        pageState.offset += pageState.limit;
        load();
    }

    document.getElementById('mark-all-btn').addEventListener('click', () => {
        if (!confirm('تحديد كل الإشعارات كمقروءة؟')) return;
        fetch('index.php?action=api_mark_all_notifications_read', {
            method: 'POST', credentials: 'same-origin'
        }).then(() => { pageState.offset = 0; load(); });
    });

    document.addEventListener('DOMContentLoaded', () => load());
</script>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>