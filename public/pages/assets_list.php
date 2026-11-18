<?php
// pages/assets_list.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="flex flex-row md:flex-col justify-between items-center sm:items-center gap-4 mb-6">
    <h1 class="text-2xl font-bold" style="color: var(--clr-text);">إدارة الأصول</h1>
    <?php if ((int) $user_data['clearance_level'] >= 4): ?>
        <a href="index.php?action=import_assets" class="btn-secondary"
            style="display: inline-block; padding: 0.5rem 1rem; border: 1px solid var(--clr-primary); border-radius: 6px; color: var(--clr-primary); text-decoration: none;">
            <i class="fas fa-file-import ml-2"></i> استيراد
        </a>

        <a href="index.php?action=asset_add" class="btn-primary text-center text-lg md:text-xs">
            <i class="fas fa-plus ml-2"></i> إضافة أصل
        </a>
    <?php endif; ?>
</div>

<!-- Quick filter tabs -->
<div class="flex flex-wrap gap-2 mb-4">
    <button class="filter-tab active" data-filter="all" onclick="setQuickFilter('all')"
        style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">الجميع</button>
    <button class="filter-tab" data-filter="available" onclick="setQuickFilter('available')"
        style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">متاحة</button>
    <button class="filter-tab" data-filter="assigned" onclick="setQuickFilter('assigned')"
        style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">مسندة</button>
    <button class="filter-tab" data-filter="under_repair" onclick="setQuickFilter('under_repair')"
        style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">تحت
        الصيانة</button>
    <button class="filter-tab" data-filter="retired" onclick="setQuickFilter('retired')"
        style="background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; color: var(--clr-text-secondary); border-bottom: 2px solid transparent; transition: all 0.2s ease;">متقاعد</button>
</div>

<!-- Advanced filter toggle -->
<div class="flex flex-wrap gap-2 items-center mb-4">
    <button id="adv-toggle-btn" onclick="toggleAdvancedFilter()"
        style="background: var(--clr-card-bg); color: var(--clr-primary); border: 1px solid var(--clr-primary); padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-filter"></i>
        <span>تصفية متقدمة</span>
        <span id="active-conditions-badge"
            style="display: none; background: var(--clr-primary); color: white; padding: 0.05rem 0.5rem; border-radius: 10px; font-size: 0.75rem; margin-right: 0.25rem;">0</span>
    </button>
    <button id="clear-adv-btn" onclick="clearAdvanced()"
        style="display: none; background: transparent; color: var(--clr-danger); border: 1px solid var(--clr-danger); padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
        <i class="fas fa-times ml-1"></i> مسح التصفية
    </button>
</div>

<!-- Advanced filter panel -->
<div id="advanced-filter-panel"
    style="display: none; background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
        <label style="color: var(--clr-text); font-weight: 500; font-size: 0.9rem;">ربط الشروط:</label>
        <select id="filter-logic" onchange="onLogicChange(this.value)"
            style="padding: 0.35rem 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">
            <option value="AND">و — تحقق كل الشروط</option>
            <option value="OR">أو — تحقق أي شرط</option>
        </select>
    </div>
    <div id="conditions-container" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
    <button onclick="addCondition()"
        style="margin-top: 0.75rem; background: transparent; color: var(--clr-primary); border: 1px dashed var(--clr-primary); padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; width: 100%;">
        <i class="fas fa-plus ml-1"></i> إضافة شرط
    </button>
</div>

<!-- Result count -->
<div id="result-count" style="color: var(--clr-text-secondary); font-size: 0.9rem; margin-bottom: 0.75rem;"></div>

<!-- Assets grid -->
<div id="assets-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
    <div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">جاري التحميل...</div>
</div>
</div>

<script>
    // ============================================================
    // STATE
    // ============================================================
    let currentQuickFilter = 'all';
    let filterLogic = 'AND';
    let conditions = [];        // [{ type:'asset'|'attr', key, operator, value }]
    let attrDefs = [];          // loaded once from API
    let entitiesCache = [];     // loaded once when assigned_to field used
    let typesList = [
        { v: 'laptop', l: 'لابتوب' },
        { v: 'desktop', l: 'كمبيوتر مكتبي' },
        { v: 'server', l: 'خادم' },
        { v: 'switch', l: 'سويتش' },
        { v: 'router', l: 'راوتر' },
        { v: 'access_point', l: 'نقطة وصول' },
        { v: 'printer', l: 'طابعة' },
        { v: 'office_printer', l: 'طابعة مكتبية' },
        { v: 'monitor', l: 'شاشة' },
        { v: 'scanner', l: 'ماسح ضوئي' },
        { v: 'fax', l: 'فاكس' },
        { v: 'phone', l: 'هاتف' },
        { v: 'land_phone', l: 'هاتف أرضي' },
        { v: 'ipad', l: 'جهاز لوحي' },
        { v: 'tablet', l: 'تابلت' },
        { v: 'ups', l: 'UPS' },
        { v: 'projector', l: 'بروجكتر' },
        { v: 'camera', l: 'كاميرا' },
        { v: 'kiosk', l: 'كشك' },
        { v: 'sensor', l: 'مستشعر' },
        { v: 'nas', l: 'NAS' },
        { v: 'firewall', l: 'جدار حماية' },
        { v: 'docking_station', l: 'محطة إرساء' },
        { v: 'external_drive', l: 'قرص خارجي' },
        { v: 'smartboard', l: 'سبورة ذكية' },
        { v: 'gps_device', l: 'جهاز GPS' },
        { v: 'other', l: 'أخرى' }
    ];
    let statusList = [
        { v: 'in_stock', l: 'في المخزون (جديد)' },
        { v: 'available', l: 'متاحة' },
        { v: 'assigned', l: 'مسندة' },
        { v: 'under_repair', l: 'تحت الصيانة' },
        { v: 'in_storage', l: 'في المخزن' },
        { v: 'retired', l: 'متقاعد' }
    ];

    // Lookup helpers used by the card
    const TYPE_LABELS = Object.fromEntries(typesList.map(t => [t.v, t.l]));
    const STATUS_LABELS = Object.fromEntries(statusList.map(s => [s.v, s.l]));
    const TYPE_COLORS = {
        laptop: '#3b82f6', desktop: '#10b981', server: '#8b5cf6',
        switch: '#f59e0b', router: '#ef4444', access_point: '#06b6d4',
        printer: '#ec4899', office_printer: '#db2777', monitor: '#f97316',
        scanner: '#0ea5e9', fax: '#64748b', phone: '#8b5cf6',
        land_phone: '#7c3aed', ipad: '#a855f7', tablet: '#c026d3',
        ups: '#dc2626', projector: '#0891b2', camera: '#14b8a6',
        kiosk: '#6366f1', sensor: '#22c55e', nas: '#0f766e',
        firewall: '#b91c1c', docking_station: '#6b7280', external_drive: '#475569',
        smartboard: '#2563eb', gps_device: '#16a34a', other: '#6b7280'
    };
    const STATUS_COLORS = {
        in_stock: 'var(--clr-success)', available: 'var(--clr-success)',
        assigned: 'var(--clr-primary)', under_repair: 'var(--clr-warning)',
        in_storage: 'var(--clr-text-secondary)', retired: 'var(--clr-danger)'
    };

    // ============================================================
    // QUICK FILTER
    // ============================================================
    function setQuickFilter(filter) {
        currentQuickFilter = filter;
        document.querySelectorAll('.filter-tab').forEach(tab => {
            const active = tab.dataset.filter === filter;
            tab.classList.toggle('active', active);
            tab.style.color = active ? 'var(--clr-primary)' : 'var(--clr-text-secondary)';
            tab.style.borderBottomColor = active ? 'var(--clr-primary)' : 'transparent';
            tab.style.fontWeight = active ? '600' : 'normal';
        });
        loadAssets();
    }

    // ============================================================
    // ADVANCED FILTER
    // ============================================================
    function toggleAdvancedFilter() {
        const panel = document.getElementById('advanced-filter-panel');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        if (panel.style.display === 'block' && attrDefs.length === 0) {
            loadAttrDefs();
        }
    }

    function loadAttrDefs() {
        fetch('index.php?action=api_get_attribute_definitions')
            .then(r => r.json())
            .then(data => {
                attrDefs = data.success ? (data.definitions || []) : [];
                renderConditions();
            })
            .catch(() => { attrDefs = []; renderConditions(); });
    }

    function loadEntitiesIfNeeded() {
        if (entitiesCache.length > 0) return Promise.resolve();
        return fetch('index.php?action=api_get_entities&filter=all')
            .then(r => r.json())
            .then(data => { entitiesCache = data.success ? data.entities : []; })
            .catch(() => { });
    }

    function addCondition() {
        conditions.push({ type: 'asset', key: 'asset_type', operator: '=', value: '' });
        renderConditions();
        applyFiltersDebounced();
    }

    function removeCondition(index) {
        conditions.splice(index, 1);
        renderConditions();
        applyFiltersDebounced();
    }

    function updateCondition(index, patch) {
        Object.assign(conditions[index], patch);
        renderConditions();
        applyFiltersDebounced();
    }

    function clearAdvanced() {
        conditions = [];
        filterLogic = 'AND';
        document.getElementById('filter-logic').value = 'AND';
        renderConditions();
        applyFiltersDebounced();
    }

    function onLogicChange(v) {
        filterLogic = v;
        applyFiltersDebounced();
    }

    let applyTimer = null;
    function applyFiltersDebounced() {
        clearTimeout(applyTimer);
        applyTimer = setTimeout(loadAssets, 350);
    }

    // ============================================================
    // CONDITION ROW RENDERER
    // ============================================================
    function getAssetFieldOptions() {
        return [
            { value: 'asset_tag', label: 'رقم الأصل', type: 'text' },
            { value: 'serial_number', label: 'الرقم التسلسلي', type: 'text' },
            { value: 'manufacturer', label: 'الشركة المصنعة', type: 'text' },
            { value: 'model', label: 'الموديل', type: 'text' },
            { value: 'asset_type', label: 'نوع الأصل', type: 'enum', options: typesList },
            { value: 'status', label: 'الحالة', type: 'enum', options: statusList },
            { value: 'assigned_to', label: 'مسند إلى', type: 'entity' }
        ];
    }

    function getAttrDefOptions() {
        return attrDefs.map(d => ({
            value: d.attr_key,
            label: d.display_label_ar,
            type: d.value_type,
            options: (d.allowed_values || []).map(v => ({ v, l: v })),
            unit: d.unit
        }));
    }

    function findFieldInfo(cond) {
        if (cond.type === 'asset') {
            return getAssetFieldOptions().find(f => f.value === cond.key);
        }
        if (cond.type === 'attr') {
            return getAttrDefOptions().find(f => f.value === cond.key);
        }
        return null;
    }

    function renderConditions() {
        const container = document.getElementById('conditions-container');
        const badge = document.getElementById('active-conditions-badge');
        const clearBtn = document.getElementById('clear-adv-btn');

        if (conditions.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: var(--clr-text-secondary); font-size: 0.85rem; padding: 0.75rem;">لم تُضف أي شرط بعد. اضغط "إضافة شرط" للبدء.</div>';
            if (badge) badge.style.display = 'none';
            if (clearBtn) clearBtn.style.display = 'none';
            return;
        }
        if (badge) {
            badge.style.display = 'inline-block';
            badge.textContent = conditions.length;
        }
        if (clearBtn) clearBtn.style.display = 'inline-block';

        let html = '';
        conditions.forEach((cond, i) => {
            const info = findFieldInfo(cond);
            const operators = getOperators(cond);

            // Field picker
            let fieldOpts = '<optgroup label="حقول الأصل">';
            getAssetFieldOptions().forEach(f => {
                fieldOpts += `<option value="asset:${f.value}" ${cond.type === 'asset' && cond.key === f.value ? 'selected' : ''}>${f.label}</option>`;
            });
            fieldOpts += '</optgroup>';
            if (attrDefs.length) {
                fieldOpts += '<optgroup label="الخصائص">';
                getAttrDefOptions().forEach(f => {
                    fieldOpts += `<option value="attr:${f.value}" ${cond.type === 'attr' && cond.key === f.value ? 'selected' : ''}>${f.label}</option>`;
                });
                fieldOpts += '</optgroup>';
            }

            // Operator picker
            let opOpts = operators.map(o =>
                `<option value="${o.v}" ${cond.operator === o.v ? 'selected' : ''}>${o.l}</option>`
            ).join('');

            // Value input
            const valueHtml = renderValueInput(cond, info, i);

            html += `
            <div style="display: flex; gap: 0.5rem; align-items: center; background: var(--clr-bg); padding: 0.5rem 0.75rem; border: 1px solid var(--clr-card-border); border-radius: 8px; flex-wrap: wrap;">
                <span style="color: var(--clr-text-secondary); font-size: 0.8rem; min-width: 24px;">${i + 1}.</span>
                <select onchange="onFieldChange(${i}, this.value)" style="flex: 1; min-width: 160px; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">
                    ${fieldOpts}
                </select>
                <select onchange="onOperatorChange(${i}, this.value)" style="min-width: 120px; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">
                    ${opOpts}
                </select>
                <div style="flex: 2; min-width: 180px;">${valueHtml}</div>
                <button onclick="removeCondition(${i})" style="background: transparent; border: 1px solid var(--clr-danger); color: var(--clr-danger); padding: 0.3rem 0.6rem; border-radius: 6px; cursor: pointer;"><i class="fas fa-times"></i></button>
            </div>
        `;
        });

        container.innerHTML = html;

        // Auto-load entities if any condition uses assigned_to
        if (conditions.some(c => c.type === 'asset' && c.key === 'assigned_to')) {
            loadEntitiesIfNeeded().then(() => renderConditions());
        }
    }

    function getOperators(cond) {
        const info = findFieldInfo(cond);
        if (!info) return [{ v: '=', l: '=' }];

        if (cond.type === 'asset') {
            if (cond.key === 'assigned_to') {
                return [
                    { v: '=', l: 'يساوي' },
                    { v: '__not_null__', l: 'مسند (أي كيان)' }
                ];
            }
            if (info.type === 'text') {
                return [
                    { v: 'contains', l: 'يحتوي' },
                    { v: '=', l: 'يساوي' },
                    { v: 'starts_with', l: 'يبدأ بـ' }
                ];
            }
            if (info.type === 'enum') {
                return [
                    { v: '=', l: 'يساوي' },
                    { v: '!=', l: 'لا يساوي' }
                ];
            }
            return [{ v: '=', l: 'يساوي' }];
        }

        if (info.type === 'number') {
            return [
                { v: '=', l: '=' },
                { v: '>=', l: '≥' },
                { v: '<=', l: '≤' },
                { v: 'between', l: 'بين' }
            ];
        }
        if (info.type === 'enum') {
            return [
                { v: '=', l: 'يساوي' },
                { v: '!=', l: 'لا يساوي' }
            ];
        }
        // text
        return [
            { v: 'contains', l: 'يحتوي' },
            { v: '=', l: 'يساوي' },
            { v: 'starts_with', l: 'يبدأ بـ' }
        ];
    }

    function renderValueInput(cond, info, index) {
        if (!info) return '<span style="color: var(--clr-text-secondary); font-size: 0.8rem;">—</span>';

        // Entity dropdown
        if (info.type === 'entity') {
            let opts = `<option value="" ${cond.value === '' ? 'selected' : ''}>— غير مسند —</option>`;
            opts += `<option value="__not_null__" ${cond.value === '__not_null__' ? 'selected' : ''}>— مسند لأي كيان —</option>`;
            entitiesCache.forEach(e => {
                opts += `<option value="${e.entity_id}" ${String(cond.value) === String(e.entity_id) ? 'selected' : ''}>${escapeHtml(e.full_name)}</option>`;
            });
            return `<select onchange="updateCondition(${index}, {value: this.value})" style="width: 100%; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">${opts}</select>`;
        }

        // Enum dropdown
        if (info.type === 'enum') {
            let opts = '<option value="">اختر...</option>';
            (info.options || []).forEach(o => {
                const v = o.v || o.value || o;
                const l = o.l || o.label || o;
                opts += `<option value="${escapeHtml(v)}" ${cond.value === v ? 'selected' : ''}>${escapeHtml(l)}</option>`;
            });
            return `<select onchange="updateCondition(${index}, {value: this.value})" style="width: 100%; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">${opts}</select>`;
        }

        // Number / between
        if (info.type === 'number') {
            if (cond.operator === 'between') {
                const parts = String(cond.value || '').split(',');
                const v1 = parts[0] || '';
                const v2 = parts[1] || '';
                return `
                <div style="display: flex; gap: 0.4rem; align-items: center;">
                    <input type="number" step="any" value="${escapeHtml(v1)}" placeholder="من" oninput="updateBetween(${index}, 'from', this.value)" style="flex: 1; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">
                    <span style="color: var(--clr-text-secondary); font-size: 0.8rem;">إلى</span>
                    <input type="number" step="any" value="${escapeHtml(v2)}" placeholder="إلى" oninput="updateBetween(${index}, 'to', this.value)" style="flex: 1; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">
                    ${info.unit ? `<span style="color: var(--clr-text-secondary); font-size: 0.8rem;">${escapeHtml(info.unit)}</span>` : ''}
                </div>`;
            }
            return `
            <div style="display: flex; gap: 0.4rem; align-items: center;">
                <input type="number" step="any" value="${escapeHtml(cond.value ?? '')}" oninput="updateCondition(${index}, {value: this.value})" style="flex: 1; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">
                ${info.unit ? `<span style="color: var(--clr-text-secondary); font-size: 0.8rem;">${escapeHtml(info.unit)}</span>` : ''}
            </div>`;
        }

        // Text
        return `<input type="text" value="${escapeHtml(cond.value ?? '')}" oninput="updateCondition(${index}, {value: this.value})" style="width: 100%; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.85rem;">`;
    }

    function onFieldChange(index, fieldKey) {
        const [type, key] = fieldKey.split(':');
        const defaultOps = getOperators({ type, key, operator: '=' });
        conditions[index] = { type, key, operator: defaultOps[0].v, value: '' };
        renderConditions();
        applyFiltersDebounced();
    }

    function onOperatorChange(index, op) {
        conditions[index].operator = op;
        if (op === 'between') conditions[index].value = '';
        renderConditions();
        applyFiltersDebounced();
    }

    function updateBetween(index, which, value) {
        const parts = String(conditions[index].value || '').split(',');
        const from = which === 'from' ? value : (parts[0] || '');
        const to = which === 'to' ? value : (parts[1] || '');
        conditions[index].value = `${from},${to}`;
        applyFiltersDebounced();
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    // ============================================================
    // LOAD ASSETS
    // ============================================================
    function loadAssets() {
        const grid = document.getElementById('assets-grid');
        const resultCount = document.getElementById('result-count');
        grid.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);"><i class="fas fa-spinner fa-spin ml-2"></i> جاري التحميل...</div>';

        const params = new URLSearchParams({
            filter: currentQuickFilter,
            logic: filterLogic,
            conditions: JSON.stringify(conditions)
        });

        fetch(`index.php?action=api_filter_assets&${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderAssets(data.assets);
                    if (resultCount) {
                        resultCount.textContent = `عدد النتائج: ${data.count}`;
                    }
                } else {
                    grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">${escapeHtml(data.message || 'خطأ')}</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                grid.innerHTML = `<div class="col-span-full text-center py-8" style="color: var(--clr-danger);">خطأ في الاتصال</div>`;
            });
    }

    function renderAssets(assets) {
        const grid = document.getElementById('assets-grid');
        if (!assets.length) {
            grid.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--clr-text-secondary);">لا توجد أصول مطابقة</div>';
            return;
        }

        grid.innerHTML = assets.map(a => {
            const color = TYPE_COLORS[a.asset_type] || '#6b7280';
            const sColor = STATUS_COLORS[a.status] || 'var(--clr-text-secondary)';
            const sLabel = STATUS_LABELS[a.status] || a.status;
            const tLabel = TYPE_LABELS[a.asset_type] || a.asset_type;

            const label = a.asset_tag || a.serial_number || '---';
            const initial = label.charAt(0).toUpperCase();

            // Combined manufacturer + model line
            const mfrModel = [a.manufacturer, a.model].filter(Boolean).join(' ');

            return `
        <div class="asset-card" style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; overflow: hidden; transition: all 0.2s ease; box-shadow: var(--clr-card-shadow);">
            <a href="index.php?action=asset_edit&id=${a.asset_id}" style="display: block; padding: 1rem; text-decoration: none; color: inherit;">
                <div style="display: flex; gap: 0.75rem; align-items: flex-start;">

                    <div style="width: 44px; height: 44px; border-radius: 50%; background: ${color}; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; font-size: 1rem;">
                        ${escapeHtml(initial)}
                    </div>

                    <div style="flex: 1; min-width: 0;">

                        <!-- Row 1: asset_tag + type badge -->
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <div style="font-weight: 700; color: var(--clr-text); font-size: 0.95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${escapeHtml(label)}
                            </div>
                            <span style="font-size: 0.7rem; padding: 0.1rem 0.45rem; border-radius: 4px; background: ${color}20; color: ${color}; font-weight: 600;">
                                ${escapeHtml(tLabel)}
                            </span>
                        </div>

                        <!-- Row 2: manufacturer + model (the important identity line) -->
                        ${mfrModel ? `
                            <div style="font-size: 0.85rem; color: var(--clr-text); font-weight: 500; margin-top: 0.15rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${escapeHtml(mfrModel)}
                            </div>
                        ` : ''}

                        <!-- Row 3: serial + location -->
                        <div style="font-size: 0.75rem; color: var(--clr-text-secondary); margin-top: 0.25rem;">
                            ${a.serial_number ? `<span style="font-family: monospace;">S/N: ${escapeHtml(a.serial_number)}</span>` : ''}
                            ${a.serial_number && a.location_name ? ' · ' : ''}
                            ${a.location_name ? escapeHtml(a.location_name) : ''}
                        </div>

                        <!-- Row 4: status + owner -->
                        <div style="font-size: 0.8rem; margin-top: 0.4rem; display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                            <span style="color: ${sColor}; font-weight: 600;">${sLabel}</span>
                            ${a.assigned_to_name
                    ? `<span style="color: var(--clr-text-secondary);"> — ${escapeHtml(a.assigned_to_name)}</span>`
                    : (a.department_name
                        ? `<span style="color: var(--clr-text-secondary);"> — ${escapeHtml(a.department_name)}</span>`
                        : '')
                }
                        </div>

                    </div>
                </div>
            </a>
        </div>`;
        }).join('');

        if (!document.getElementById('asset-list-hover-styles')) {
            const style = document.createElement('style');
            style.id = 'asset-list-hover-styles';
            style.textContent = `.asset-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: var(--clr-primary); }`;
            document.head.appendChild(style);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadAssets();
    });
</script>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>