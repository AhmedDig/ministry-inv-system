<?php
// pages/tickets_list.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--clr-text);">إدارة التذاكر</h1>
        <button onclick="openTicketModal()" class="btn-primary w-full sm:w-auto text-center">
            <i class="fas fa-plus ml-2"></i> تذكرة جديدة
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        <div class="stat-card"
            style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; text-align: center; box-shadow: var(--clr-card-shadow);">
            <div class="stat-value" id="stat-total" style="font-size: 2rem; font-weight: bold; color: var(--clr-text);">
                0</div>
            <div class="stat-label" style="color: var(--clr-text-secondary); font-size: 0.85rem;">الإجمالي</div>
        </div>
        <div class="stat-card"
            style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; text-align: center; box-shadow: var(--clr-card-shadow);">
            <div class="stat-value" id="stat-pending"
                style="font-size: 2rem; font-weight: bold; color: var(--clr-warning);">0</div>
            <div class="stat-label" style="color: var(--clr-text-secondary); font-size: 0.85rem;">في انتظار الموافقة
            </div>
        </div>
        <div class="stat-card"
            style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; text-align: center; box-shadow: var(--clr-card-shadow);">
            <div class="stat-value" id="stat-active" style="font-size: 2rem; font-weight: bold; color: #8b5cf6;">0</div>
            <div class="stat-label" style="color: var(--clr-text-secondary); font-size: 0.85rem;">قيد العمل</div>
        </div>
        <div class="stat-card"
            style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; text-align: center; box-shadow: var(--clr-card-shadow);">
            <div class="stat-value" id="stat-parts" style="font-size: 2rem; font-weight: bold; color: #f97316;">0</div>
            <div class="stat-label" style="color: var(--clr-text-secondary); font-size: 0.85rem;">بانتظار قطع</div>
        </div>
        <div class="stat-card"
            style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; text-align: center; box-shadow: var(--clr-card-shadow);">
            <div class="stat-value" id="stat-closed"
                style="font-size: 2rem; font-weight: bold; color: var(--clr-success);">0</div>
            <div class="stat-label" style="color: var(--clr-text-secondary); font-size: 0.85rem;">مغلقة</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-2 mb-6">
        <button class="filter-tab active" data-filter="all" onclick="loadTickets('all')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">الجميع</button>
        <button class="filter-tab" data-filter="pending_approval" onclick="loadTickets('pending_approval')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">في
            انتظار الموافقة</button>
        <button class="filter-tab" data-filter="open" onclick="loadTickets('open')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">مفتوحة</button>
        <button class="filter-tab" data-filter="in_progress" onclick="loadTickets('in_progress')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">قيد
            المعالجة</button>
        <button class="filter-tab" data-filter="awaiting_parts" onclick="loadTickets('awaiting_parts')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">بانتظار
            قطع</button>
        <button class="filter-tab" data-filter="frozen" onclick="loadTickets('frozen')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">مجمّدة</button>
        <button class="filter-tab" data-filter="closed" onclick="loadTickets('closed')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">مغلقة</button>
    </div>

    <!-- Tickets Grid -->
    <div id="tickets-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">جاري التحميل...</div>
    </div>
</div>

<!-- Include Modals -->
<?php require_once __DIR__ . '/../components/tickets/ticket_submission_modal.php'; ?>
<?php require_once __DIR__ . '/../components/tickets/ticket_details_modal.php'; ?>

<script>
    let currentFilter = 'all';

    function loadTickets(filter) {
        currentFilter = filter;
        const grid = document.getElementById('tickets-grid');
        grid.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">جاري التحميل...</div>';

        fetch(`index.php?action=api_get_tickets&filter=${filter}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.userLevel = data.user_level || 0;
                    window.userEntityId = data.user_entity_id || 0;
                    renderTickets(data.tickets);
                    updateStats(data.stats);
                    document.querySelectorAll('.filter-tab').forEach(tab => {
                        const active = tab.dataset.filter === filter;
                        tab.classList.toggle('active', active);
                        tab.style.color = active ? 'var(--clr-primary)' : 'var(--clr-text-secondary)';
                        tab.style.borderBottomColor = active ? 'var(--clr-primary)' : 'transparent';
                        tab.style.fontWeight = active ? '600' : 'normal';
                    });
                } else {
                    grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">${data.message || 'حدث خطأ في التحميل'}</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                grid.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">خطأ في الاتصال</div>';
            });
    }

    function renderTickets(tickets) {
        const grid = document.getElementById('tickets-grid');
        if (!tickets.length) {
            grid.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">لا توجد تذاكر</div>';
            return;
        }

        const userLevel = window.userLevel || 0;

        grid.innerHTML = tickets.map(ticket => {
            const statusColor = TicketHelpers.statusColors[ticket.status] || 'var(--clr-text-secondary)';
            const priorityColor = TicketHelpers.priorityColors[ticket.priority] || 'var(--clr-text-secondary)';
            const statusLabel = TicketHelpers.statusLabels[ticket.status] || ticket.status;
            const priorityLabel = TicketHelpers.priorityLabels[ticket.priority] || ticket.priority;

            // L5 quick actions
            const canApprove = userLevel >= 5 && ticket.status === 'pending_approval';
            const canFreeze = userLevel >= 5 && !['frozen', 'closed'].includes(ticket.status);
            const canUnfreeze = userLevel >= 5 && ticket.status === 'frozen';

            return `
        <div class="ticket-card" style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; overflow: hidden; transition: all 0.2s ease; box-shadow: var(--clr-card-shadow); ${ticket.status === 'frozen' ? 'opacity: 0.8;' : ''}">
            <div class="p-4">
                <div class="flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="text-xs font-mono" style="color: var(--clr-text-secondary);">#${ticket.ticket_id}</span>
                            <h3 class="font-bold text-lg truncate" style="color: var(--clr-text);">${escapeHtml(ticket.title)}</h3>
                            <span class="text-xs px-2 py-0.5 rounded" style="background: ${priorityColor}30; color: ${priorityColor};">
                                ${priorityLabel}
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded" style="background: ${statusColor}30; color: ${statusColor}; font-weight: 600;">
                                ${statusLabel}
                            </span>
                        </div>
                        <div class="text-sm truncate" style="color: var(--clr-text-secondary);">
                            <i class="fas fa-user ml-1" style="opacity: 0.6;"></i>${escapeHtml(ticket.submitted_by_name || 'غير معروف')}
                            ${ticket.submitted_by_phone ? `<span dir="ltr" style="font-family: monospace;"> — ${escapeHtml(ticket.submitted_by_phone)}</span>` : ''}
                        </div>
                        <div class="text-sm" style="color: var(--clr-text-secondary);">
                            <i class="fas fa-laptop ml-1" style="opacity: 0.6;"></i>${escapeHtml(ticket.device_name || 'بدون جهاز')}
                        </div>
                        <div class="text-xs mt-1" style="color: var(--clr-text-secondary);">
                            <i class="fas fa-clock ml-1" style="opacity: 0.6;"></i>${new Date(ticket.created_at).toLocaleString('ar-EG')}
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                    <button onclick="viewTicketDetails(${ticket.ticket_id})" class="btn-secondary text-xs" style="padding: 0.25rem 0.75rem;">
                        <i class="fas fa-eye ml-1"></i> تفاصيل
                    </button>
                    ${canApprove ? `<button onclick="quickApprove(${ticket.ticket_id})" class="btn-primary text-xs" style="padding: 0.25rem 0.75rem; background: var(--clr-success);"><i class="fas fa-check ml-1"></i> اعتماد</button>` : ''}
                    ${canFreeze ? `<button onclick="quickFreeze(${ticket.ticket_id})" class="btn-warning text-xs" style="padding: 0.25rem 0.75rem;"><i class="fas fa-snowflake ml-1"></i> تجميد</button>` : ''}
                    ${canUnfreeze ? `<button onclick="quickUnfreeze(${ticket.ticket_id})" class="btn-primary text-xs" style="padding: 0.25rem 0.75rem;"><i class="fas fa-sun ml-1"></i> إلغاء التجميد</button>` : ''}
                </div>
            </div>
        </div>`;
        }).join('');

        if (!document.getElementById('ticket-list-styles')) {
            const style = document.createElement('style');
            style.id = 'ticket-list-styles';
            style.textContent = `
            .ticket-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
            .dark-mode .ticket-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.4); }
        `;
            document.head.appendChild(style);
        }
    }

    function updateStats(stats) {
        document.getElementById('stat-total').textContent = stats.total || 0;
        document.getElementById('stat-pending').textContent = stats.pending_approval || 0;
        document.getElementById('stat-active').textContent = (stats.open || 0) + (stats.in_progress || 0);
        document.getElementById('stat-parts').textContent = stats.awaiting_parts || 0;
        document.getElementById('stat-closed').textContent = stats.closed || 0;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function quickApprove(id) {
        if (!confirm('اعتماد هذه التذكرة؟')) return;
        fetch('index.php?action=api_approve_ticket', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('✅ تم الاعتماد', 'success'); loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function quickFreeze(id) {
        if (!confirm('تجميد هذه التذكرة؟')) return;
        fetch('index.php?action=api_freeze_ticket', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('❄️ تم التجميد', 'success'); loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function quickUnfreeze(id) {
        if (!confirm('إلغاء تجميد هذه التذكرة؟')) return;
        fetch('index.php?action=api_unfreeze_ticket', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}&target_status=open`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('☀️ تم إلغاء التجميد', 'success'); loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function viewTicketDetails(id) {
        if (typeof openTicketDetails === 'function') openTicketDetails(id);
    }

    function openTicketModal() {
        if (typeof showTicketSubmissionModal === 'function') showTicketSubmissionModal();
    }

    document.addEventListener('DOMContentLoaded', () => loadTickets('all'));
</script>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>