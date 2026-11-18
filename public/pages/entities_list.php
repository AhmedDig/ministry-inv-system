<?php
// pages/entities_list.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <div class="flex flex-row md:flex-col justify-between items-center sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--clr-text);">الكيانات (الأفراد والجهات)</h1>
        <a href="index.php?action=entity_add" class="btn-primary text-center text-lg md:text-xs">
            <i class="fas fa-plus ml-2"></i> إضافة كيان
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="flex mb-6 gap-2 flex-wrap">
        <button class="filter-tab active" data-filter="all" onclick="loadEntities('all')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">الجميع</button>
        <button class="filter-tab" data-filter="employee" onclick="loadEntities('employee')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">موظفين</button>
        <button class="filter-tab" data-filter="external_tech" onclick="loadEntities('external_tech')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">فنيين
            خارجيين</button>
        <button class="filter-tab" data-filter="repair_shop" onclick="loadEntities('repair_shop')"
            style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">مراكز
            صيانة</button>
    </div>

    <!-- Entities Grid -->
    <div id="entities-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">جاري التحميل...</div>
    </div>
</div>

<script>
    // Fixed hex color palette
    const hexColors = [
        '#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444',
        '#06b6d4', '#ec4899', '#f97316', '#6366f1', '#14b8a6'
    ];

    function getAvatarColor(id) {
        return hexColors[id % hexColors.length];
    }

    function loadEntities(filter) {
        const grid = document.getElementById('entities-grid');
        grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">جاري التحميل...</div>`;

        fetch(`index.php?action=api_get_entities&filter=${filter}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderEntities(data.entities);
                    document.querySelectorAll('.filter-tab').forEach(tab => {
                        tab.classList.toggle('active', tab.dataset.filter === filter);
                        if (tab.dataset.filter === filter) {
                            tab.style.color = 'var(--clr-primary)';
                            tab.style.borderBottomColor = 'var(--clr-primary)';
                            tab.style.fontWeight = '600';
                        } else {
                            tab.style.color = 'var(--clr-text-secondary)';
                            tab.style.borderBottomColor = 'transparent';
                            tab.style.fontWeight = 'normal';
                        }
                    });
                } else {
                    grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">حدث خطأ في التحميل</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">خطأ في الاتصال</div>`;
            });
    }

    function renderEntities(entities) {
        const grid = document.getElementById('entities-grid');
        if (!entities.length) {
            grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">لا توجد كيانات</div>`;
            return;
        }

        const typeLabels = {
            'employee': 'موظف',
            'external_tech': 'فني خارجي',
            'repair_shop': 'مركز صيانة'
        };

        grid.innerHTML = entities.map(entity => {
            const initial = entity.full_name ? entity.full_name.charAt(0) : '?';
            const avatarColor = getAvatarColor(entity.entity_id);
            const badgeBg = avatarColor + '40'; // ~25% opacity
            const hasUser = entity.user_id ? 'نعم' : 'لا';

            return `
        <div class="entity-card" style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; overflow: hidden; transition: all 0.2s ease; box-shadow: var(--clr-card-shadow);">
            <a href="index.php?action=entity_edit&id=${entity.entity_id}" class="block p-4" style="text-decoration: none; color: inherit; transition: background 0.2s ease;">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0" style="background: ${avatarColor};">
                        ${initial}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-lg truncate" style="color: var(--clr-text);">${entity.full_name}</h3>
                            <span class="text-xs px-2 py-0.5 rounded" style="background: ${badgeBg}; color: ${avatarColor};">
                                ${typeLabels[entity.type] || entity.type}
                            </span>
                        </div>
                        <div class="text-sm truncate" style="color: var(--clr-text-secondary);">${entity.email || 'لا يوجد بريد'}</div>
                        <div class="text-sm" style="color: var(--clr-text-secondary);">${entity.phone || 'لا يوجد هاتف'}</div>
                        <div class="text-sm mt-1" style="color: var(--clr-text-secondary);">${entity.department_name_ar || 'لا يوجد قسم'}</div>
                        <div class="text-sm" style="color: var(--clr-text-secondary);">
                            <span style="font-weight: 500;">مستخدم:</span> ${hasUser}
                        </div>
                    </div>
                </div>
            </a>
            <div class="border-t px-4 py-3 flex justify-end" style="border-color: var(--clr-card-border); background: var(--clr-bg);">
                <button onclick="deleteEntity(${entity.entity_id})" style="background: var(--clr-danger); color: white; border: none; border-radius: 6px; cursor: pointer; transition: background 0.2s ease; padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                    <i class="fas fa-trash ml-1"></i> حذف
                </button>
            </div>
        </div>
    `}).join('');

        // Add hover styles via style tag
        const style = document.createElement('style');
        style.textContent = `
        .entity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .dark-mode .entity-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }
        .entity-card a:hover {
            background: var(--clr-bg);
        }
        @media (max-width: 480px) {
            .entity-card .flex.items-start.gap-4 {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .entity-card .w-12 {
                margin-bottom: 0.5rem;
            }
        }
    `;
        document.head.appendChild(style);
    }

    function deleteEntity(id) {
        if (!confirm('هل أنت متأكد من حذف هذا الكيان؟')) return;
        fetch('index.php?action=api_delete_entity', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `entity_id=${id}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadEntities(document.querySelector('.filter-tab.active').dataset.filter);
                    showToast('🗑️ تم الحذف', 'success');
                } else {
                    showToast('❌ فشل', 'error');
                }
            });
    }

    function showToast(msg, type) {
        const toast = document.createElement('div');
        toast.textContent = msg;
        const bgColor = type === 'success' ? 'var(--clr-success)' : 'var(--clr-danger)';
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

    document.addEventListener('DOMContentLoaded', function () {
        loadEntities('all');
    });

    // Add animation keyframes once
    if (!document.getElementById('entity-toast-styles')) {
        const style = document.createElement('style');
        style.id = 'entity-toast-styles';
        style.textContent = `
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateX(-50%) translateY(20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
    `;
        document.head.appendChild(style);
    }
</script>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>