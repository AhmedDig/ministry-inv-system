<?php
// pages/consumables_list.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <div class="flex flex-row md:flex-col justify-between items-center sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--clr-text);">إدارة المستهلكات</h1>
        <a href="index.php?action=consumable_add" class="btn-primary text-center text-lg md:text-xs">
            <i class="fas fa-plus ml-2"></i> إضافة مستهلك
        </a>
    </div>

    <!-- Filter / Search -->
    <div class="flex flex-wrap gap-4 mb-6 items-center">
        <div class="flex-1 min-w-[200px]">
            <input type="text" id="searchConsumable" class="form-input" placeholder="ابحث بالاسم أو SKU..."
                oninput="loadConsumables()"
                style="width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
        </div>
        <button onclick="loadConsumables()" class="btn-secondary" style="padding: 0.5rem 1rem;">تحديث</button>
    </div>

    <!-- Consumables Grid -->
    <div id="consumables-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">جاري التحميل...</div>
    </div>
</div>

<script>
    // Type labels
    const typeLabels = {
        'cable': 'كابل',
        'ink_cartridge': 'خرطوشة حبر',
        'toner': 'تونر',
        'paper': 'ورق',
        'battery': 'بطارية',
        'adapter': 'محول',
        'other': 'أخرى'
    };

    function loadConsumables() {
        const grid = document.getElementById('consumables-grid');
        const search = document.getElementById('searchConsumable')?.value || '';
        grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">جاري التحميل...</div>`;

        fetch(`index.php?action=api_get_consumables&search=${encodeURIComponent(search)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderConsumables(data.consumables);
                } else {
                    grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">حدث خطأ في التحميل</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">خطأ في الاتصال</div>`;
            });
    }

    function renderConsumables(consumables) {
        const grid = document.getElementById('consumables-grid');
        if (!consumables.length) {
            grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">لا توجد مستهلكات</div>`;
            return;
        }

        grid.innerHTML = consumables.map(item => {
            const isLowStock = item.quantity <= item.min_quantity;
            const stockColor = isLowStock ? 'var(--clr-danger)' : 'var(--clr-success)';
            const typeLabel = typeLabels[item.consumable_type] || item.consumable_type;

            return `
        <div class="consumable-card" style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; overflow: hidden; transition: all 0.2s ease; box-shadow: var(--clr-card-shadow); ${isLowStock ? 'border-right: 4px solid var(--clr-danger);' : ''}">
            <a href="index.php?action=consumable_edit&id=${item.consumable_id}" class="block p-4" style="text-decoration: none; color: inherit; transition: background 0.2s ease;">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0" style="background: ${isLowStock ? 'var(--clr-danger)' : 'var(--clr-primary)'};">
                        ${item.name ? item.name.charAt(0).toUpperCase() : '?'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-lg truncate" style="color: var(--clr-text);">${item.name}</h3>
                            <span class="text-xs px-2 py-0.5 rounded" style="background: var(--clr-primary)40; color: var(--clr-primary);">${typeLabel}</span>
                            ${isLowStock ? `<span class="text-xs px-2 py-0.5 rounded" style="background: var(--clr-danger); color: white;">مخزون منخفض</span>` : ''}
                        </div>
                        <div class="text-sm truncate" style="color: var(--clr-text-secondary);">SKU: ${item.sku || '---'}</div>
                        <div class="text-sm mt-1" style="color: var(--clr-text);">
                            <span style="font-weight: 600;">الكمية:</span> 
                            <span style="color: ${stockColor};">${item.quantity}</span>
                            <span style="color: var(--clr-text-secondary);"> / الحد الأدنى: ${item.min_quantity}</span>
                        </div>
                        <div class="text-sm" style="color: var(--clr-text-secondary);">${item.location_name || 'لا يوجد موقع'}</div>
                        <div class="text-sm" style="color: var(--clr-text-secondary);">المورد: ${item.supplier_name || '---'}</div>
                    </div>
                </div>
            </a>
            <div class="border-t px-4 py-3 flex justify-between items-center gap-2 flex-wrap" style="border-color: var(--clr-card-border); background: var(--clr-bg);">
                <div class="flex gap-2">
                    <button onclick="adjustQuantity(${item.consumable_id}, 1)" style="background: var(--clr-success); color: white; border: none; border-radius: 6px; cursor: pointer; padding: 0.25rem 0.75rem; font-size: 0.75rem; transition: background 0.2s ease;">+ إضافة</button>
                    <button onclick="adjustQuantity(${item.consumable_id}, -1)" style="background: var(--clr-warning); color: white; border: none; border-radius: 6px; cursor: pointer; padding: 0.25rem 0.75rem; font-size: 0.75rem; transition: background 0.2s ease;">- خصم</button>
                </div>
                <button onclick="deleteConsumable(${item.consumable_id})" style="background: var(--clr-danger); color: white; border: none; border-radius: 6px; cursor: pointer; transition: background 0.2s ease; padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                    <i class="fas fa-trash ml-1"></i> حذف
                </button>
            </div>
        </div>
    `}).join('');

        // Add hover styles
        const style = document.createElement('style');
        style.textContent = `
        .consumable-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .dark-mode .consumable-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }
        .consumable-card a:hover {
            background: var(--clr-bg);
        }
        @media (max-width: 480px) {
            .consumable-card .flex.items-start.gap-4 {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .consumable-card .w-12 {
                margin-bottom: 0.5rem;
            }
        }
    `;
        document.head.appendChild(style);
    }

    // Adjust quantity modal
    function adjustQuantity(id, change) {
        const reason = prompt(`أدخل سبب ${change > 0 ? 'الإضافة' : 'الخصم'}:`);
        if (reason === null) return; // cancelled
        if (reason.trim() === '') {
            alert('السبب مطلوب');
            return;
        }

        fetch('index.php?action=api_adjust_quantity', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `consumable_id=${id}&change=${change}&reason=${encodeURIComponent(reason)}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadConsumables();
                    showToast(`✅ تم ${change > 0 ? 'إضافة' : 'خصم'} الكمية`, 'success');
                } else {
                    showToast('❌ فشل التعديل', 'error');
                }
            });
    }

    function deleteConsumable(id) {
        if (!confirm('هل أنت متأكد من حذف هذا المستهلك؟')) return;
        fetch('index.php?action=api_delete_consumable', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `consumable_id=${id}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadConsumables();
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

    // Load on page load
    document.addEventListener('DOMContentLoaded', function () {
        loadConsumables();
    });

    // Add animation keyframes once
    if (!document.getElementById('consumable-toast-styles')) {
        const style = document.createElement('style');
        style.id = 'consumable-toast-styles';
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