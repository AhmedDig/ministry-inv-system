<?php
// pages/users_list.php

// Restrict to admins (clearance_level >= 5)
if (!isset($_SESSION['user_data']) || $_SESSION['user_data']['clearance_level'] < 5) {
    header('Location: index.php?action=dashboard');
    exit;
}

$logged_in = true;
$user_data = $_SESSION['user_data'];
$page_title = 'إدارة المستخدمين';

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <div class="flex flex-row md:flex-col justify-between items-center sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold">إدارة المستخدمين</h1>
        <a href="index.php?action=user_add" class="btn-primary  text-center text-lg md:text-xs">
            <i class="fas fa-user-plus ml-2"></i> إضافة مستخدم
        </a>
    </div>

    <!-- Filter Tabs (scrollable on mobile) -->
    <div class="flex mb-6 gap-2 flex-wrap">
        <button class="filter-tab active" data-filter="all" onclick="loadUsers('all')">الجميع</button>
        <button class="filter-tab" data-filter="pending" onclick="loadUsers('pending')">في انتظار
            الموافقة</button>
        <button class="filter-tab" data-filter="active" onclick="loadUsers('active')">مفعلون</button>
    </div>

    <!-- Users Grid (Cards) -->
    <div id="users-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="col-span-full text-center py-8 text-gray-500">جاري التحميل...</div>
    </div>
</div>

<script>
    function loadUsers(filter) {
        const grid = document.getElementById('users-grid');
        grid.innerHTML = `<div class="col-span-full text-center py-8 text-gray-500">جاري التحميل...</div>`;

        fetch(`index.php?action=api_get_users&filter=${filter}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderUsers(data.users);
                    // Update filter tabs
                    document.querySelectorAll('.filter-tab').forEach(tab => {
                        tab.classList.toggle('active', tab.dataset.filter === filter);
                    });
                } else {
                    grid.innerHTML = `<div class="col-span-full text-center py-8 text-red-500">حدث خطأ في التحميل</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                grid.innerHTML = `<div class="col-span-full text-center py-8 text-red-500">خطأ في الاتصال</div>`;
            });
    }

    function renderUsers(users) {
        const grid = document.getElementById('users-grid');

        if (!users.length) {
            grid.innerHTML = `<div class="col-span-full text-center py-8 text-gray-500">لا توجد بيانات</div>`;
            return;
        }

        grid.innerHTML = users.map(user => {
            const isActive = user.is_active == 1;
            const statusClass = isActive ? 'status-approved' : 'status-pending';
            const statusText = isActive ? 'مفعل' : 'في انتظار الموافقة';
            const initial = user.full_name ? user.full_name.charAt(0) : '?';
            const avatarColor = getAvatarColor(user.user_id);

            return `
        <div class="user-card rounded-lg shadow-[var(--clr-card-shadow)] transition-all duration-200 border border-[var(--clr-card-border)] overflow-hidden">
            <!-- Clickable card body (goes to edit) -->
            <a href="index.php?action=user_edit&id=${user.user_id}" class="block p-4 hover:bg-[var(--clr-primary-hover)] hover:text-[var(--clr-text-secondary)] transition-colors">
                <div class="flex items-start gap-4">
                    <!-- Avatar -->
                    <div class="user-avatar w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0" style="background: ${avatarColor};">
                        ${initial}
                    </div>
                    
                    <!-- User Info -->
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-lg truncate">${user.full_name}</h3>
                        <div class="text-main flex items-center gap-2 flex-nowrap gap-2 mb-2 w-full items-center justify-between">
                            <span class="clearance_level text-xs px-2 py-0.5">مستوى ${user.clearance_level}</span>
                            <div class="w-fit">
                            <span class="status-badge ${statusClass} text-sm px-4 py-3 w-full">${statusText}</span>
                            ${user.requires_password_reset ? '<span class="text-xs bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-2 py-0.5 rounded">إعادة تعيين مطلوبة</span>' : ''}
                            </div>
                        </div>
                        <div class="text-sm text-secondary truncate">@${user.username}</div>
                        <div class="text-sm text-secondary truncate">${user.email}</div>
                        <div class="text-sm text-secondary mt-1">
                            ${user.department_name_ar || 'لا يوجد قسم'}
                        </div>
                    </div>
                </div>
            </a>

            <!-- Actions Bar -->
            <div class="border-t bg-[var(--clr-card-border)] gap-2 p-4 w-full">
                <div class="flex md:flex-row flex-col items-center gap-2 mb-2">
                    ${!isActive ? `
                        <button onclick="approveUser(${user.user_id})" class="lg:w-1/2 md:w-full btn-primary text-xs">
                            <i class="fas fa-check ml-1 text-xs"></i> موافقة
                        </button>
                    ` : ''}
                    ${isActive ? `
                        <button onclick="forceReset(${user.user_id})" class="lg:w-1/2 md:w-full btn-warning text-xs">
                            <i class="fas fa-key ml-1 text-xs"></i> إعادة تعيين
                        </button>
                    ` : ''}
                    <button onclick="deleteUser(${user.user_id})" class="lg:w-1/2 md:w-full btn-danger text-xs">
                        <i class="fas fa-trash ml-1 text-xs"></i> حذف
                    </button>
                </div>
            </div>
        </div>
    `}).join('');
    }

    function getAvatarColor(id) {
        const colors = [
            '#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444',
            '#06b6d4', '#ec4899', '#f97316', '#6366f1', '#14b8a6'
        ];
        return colors[id % colors.length];
    }

    function approveUser(id) {
        if (!confirm('هل أنت متأكد من الموافقة على هذا المستخدم؟')) return;
        fetch('index.php?action=api_approve_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${id}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadUsers(document.querySelector('.filter-tab.active').dataset.filter);
                    showToast('✅ تمت الموافقة', 'success');
                } else {
                    showToast('❌ فشل', 'error');
                }
            });
    }

    function deleteUser(id) {
        if (!confirm('هل أنت متأكد من حذف هذا المستخدم؟')) return;
        fetch('index.php?action=api_delete_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${id}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadUsers(document.querySelector('.filter-tab.active').dataset.filter);
                    showToast('🗑️ تم الحذف', 'success');
                } else {
                    showToast('❌ فشل', 'error');
                }
            });
    }

    function forceReset(id) {
        if (!confirm('سيطلب من هذا المستخدم تغيير كلمة المرور عند تسجيل الدخول التالي. متابعة؟')) return;
        fetch('index.php?action=api_force_reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${id}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('🔑 تم إعادة التعيين', 'success');
                } else {
                    showToast('❌ فشل', 'error');
                }
            });
    }

    function showToast(msg, type) {
        const bgColor = type === 'success' ? 'var(--clr-success)' : 'var(--clr-danger)';
        const toast = document.createElement('div');
        toast.textContent = msg;
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

    // Load on page load
    document.addEventListener('DOMContentLoaded', function () {
        loadUsers('all');
    });
</script>

<!-- Add small animation keyframes -->
<style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }

    /* Card hover effect */
    .user-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: var(--clr-card-bg);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-content: center;
        align-items: stretch;
        min-height: 250px;
        height: 100%;
        max-height: 350px;
        width: 100%;
    }

    .user-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--clr-card-shadow);
    }

    /* Make filter tabs scrollable on mobile */
    .filter-tabs::-webkit-scrollbar {
        height: 4px;
    }

    .filter-tabs::-webkit-scrollbar-thumb {
        background: var(--clr-primary);
        border-radius: 4px;
    }

    /* Status badge small variant */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        text-align: center;
    }

    /* Danger button style */
    .btn-danger {
        background: var(--clr-danger);
        color: var(--clr-alert-danger-text);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.2s ease;
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }

    .btn-danger:hover {
        background: var(--clr-alert-danger-bg);
    }


    .clearance_level {
        background: var(--clr-input-border);
        color: var(--clr-text);
        border-radius: 10px;
    }

    @media (max-width: 786px) {
        .user-card {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .border-t>div {
            width: 100%;
            justify-content: center;
        }

    }

    /* Responsive adjustments */
    @media (max-width: 480px) {
        .user-card .flex.items-start.gap-4 {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .user-card .user-avatar {
            margin-bottom: 0.5rem;
        }

        .user-card .flex-1 {
            text-align: center;
        }

        .user-card .flex.items-center.gap-2 {
            justify-content: center;
        }

        .user-card .border-t {
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .user-card .border-t>div {
            justify-content: center;
        }
    }
</style>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>