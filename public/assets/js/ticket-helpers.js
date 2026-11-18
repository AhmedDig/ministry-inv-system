// assets/js/ticket-helpers.js
// Shared helpers for ticket views

if (typeof window.TicketHelpers === 'undefined') {

    window.TicketHelpers = {
        statusColors: {
            'pending_approval': 'var(--clr-warning)',
            'open': 'var(--clr-primary)',
            'in_progress': '#8b5cf6',
            'awaiting_parts': '#f97316',
            'frozen': '#6b7280',
            'closed': 'var(--clr-success)',
            // legacy fallbacks (do not remove)
            'resolved': 'var(--clr-success)',
            'rejected': 'var(--clr-danger)'
        },
        priorityColors: {
            'low': 'var(--clr-success)',
            'medium': 'var(--clr-warning)',
            'high': 'var(--clr-danger)',
            'critical': '#7c2d12'
        },
        statusLabels: {
            'pending_approval': 'في انتظار الموافقة',
            'open': 'مفتوحة',
            'in_progress': 'قيد المعالجة',
            'awaiting_parts': 'بانتظار قطع',
            'frozen': 'مجمّدة',
            'closed': 'مغلقة',
            'resolved': 'مكتملة',
            'rejected': 'مرفوضة'
        },
        priorityLabels: {
            'low': 'منخفض',
            'medium': 'متوسط',
            'high': 'مرتفع',
            'critical': 'حرج'
        },
        getAvatarColor: function (id) {
            const colors = [
                '#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444',
                '#06b6d4', '#ec4899', '#f97316', '#6366f1', '#14b8a6'
            ];
            return colors[(id || 0) % colors.length];
        },
        showToast: function (msg, type) {
            const toast = document.createElement('div');
            toast.textContent = msg;
            const bgColor = type === 'success' ? 'var(--clr-success)'
                : type === 'warning' ? 'var(--clr-warning)'
                    : 'var(--clr-danger)';
            toast.style.cssText = `
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: ${bgColor}; color: white;
            padding: 12px 24px; border-radius: 8px; z-index: 9999;
            font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: fadeInUp 0.3s ease; direction: rtl;
        `;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    };

    if (!document.getElementById('ticket-helpers-styles')) {
        const style = document.createElement('style');
        style.id = 'ticket-helpers-styles';
        style.textContent = `
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateX(-50%) translateY(20px); }
            to   { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
    `;
        document.head.appendChild(style);
    }

}