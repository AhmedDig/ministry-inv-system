<?php
// pages/import_assets.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

if (!isset($_SESSION['user_id']) || (int) ($user_data['clearance_level'] ?? 0) < 4) {
    header('Location: index.php?action=assets');
    exit;
}

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--clr-text);">
            <i class="fas fa-file-import ml-2" style="color: var(--clr-primary);"></i> استيراد أصول
        </h1>
        <a href="index.php?action=assets" class="btn-secondary">إلغاء</a>
    </div>

    <div id="upload-zone" style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 2rem;">
        <div style="text-align: center;">
            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--clr-primary); opacity: 0.7; margin-bottom: 1rem; display: block;"></i>
            <h3 style="color: var(--clr-text); font-weight: 600; margin-bottom: 0.5rem;">ارفع ملف CSV</h3>
            <p style="color: var(--clr-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">
                من Excel: ملف → حفظ باسم → اختر "CSV UTF-8" (.csv)
            </p>
            <input type="file" id="csv-file" accept=".csv" style="display: none;">
            <button onclick="document.getElementById('csv-file').click()"
                    style="background: var(--clr-primary); color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; cursor: pointer; font-size: 1rem;">
                <i class="fas fa-folder-open ml-2"></i> اختر ملف
            </button>
            <div id="upload-status" style="margin-top: 1rem; color: var(--clr-text-secondary); font-size: 0.85rem;"></div>
        </div>
    </div>

    <div id="workspace" style="display: none;">
        <div class="import-layout">
            <aside class="import-mapping-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <h3 style="color: var(--clr-text); font-size: 1rem; font-weight: 600; margin: 0;">
                        <i class="fas fa-columns ml-1" style="color: var(--clr-primary);"></i> اربط الأعمدة
                    </h3>
                    <div style="display: flex; gap: 0.35rem;">
                        <select id="load-mapping-select" onchange="loadSavedMapping(this.value)"
                                style="padding: 0.35rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.75rem;">
                            <option value="">قالب محفوظ...</option>
                        </select>
                        <button onclick="saveMapping()" title="حفظ كقالب"
                                style="background: transparent; border: 1px solid var(--clr-primary); color: var(--clr-primary); padding: 0.35rem 0.5rem; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>
                <p style="color: var(--clr-text-secondary); font-size: 0.75rem; margin-bottom: 0.75rem;">
                    اختر لكل عمود ما يقابله في النظام. المعاينة تتحدث تلقائياً.
                </p>
                <div id="mapping-list" style="display: flex; flex-direction: column; gap: 0.5rem; overflow-y: auto; flex: 1; margin-bottom: 0.75rem;"></div>
                <div style="border-top: 1px solid var(--clr-card-border); padding-top: 0.75rem;">
                    <div id="validation-summary" style="margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--clr-text-secondary);"></div>
                    <button onclick="location.reload()" class="btn-secondary" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-redo ml-1"></i> البدء من جديد
                    </button>
                    <button id="import-btn" onclick="executeImport()"
                            style="width: 100%; padding: 0.75rem; background: var(--clr-success); border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-check ml-1"></i> تأكيد الاستيراد
                    </button>
                </div>
            </aside>

            <section class="import-preview-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <h3 style="color: var(--clr-text); font-size: 1rem; font-weight: 600; margin: 0;">
                        <i class="fas fa-table ml-1" style="color: var(--clr-success);"></i> المعاينة الحية
                    </h3>
                    <span id="preview-meta" style="color: var(--clr-text-secondary); font-size: 0.75rem;"></span>
                </div>
                <p style="color: var(--clr-text-secondary); font-size: 0.75rem; margin-bottom: 0.75rem;">
                    هكذا ستظهر بياناتك. الصفوف ذات الخلفية الحمراء لن يتم استيرادها.
                </p>
                <div style="overflow: auto; max-height: 70vh; border: 1px solid var(--clr-card-border); border-radius: 8px; background: var(--clr-card-bg);">
                    <div id="preview-table-inner">
                        <div style="padding: 2rem; text-align: center; color: var(--clr-text-secondary); font-size: 0.85rem;">جاري التحميل...</div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div id="report-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1002; align-items: center; justify-content: center;">
        <div style="background: var(--clr-card-bg); border-radius: 12px; padding: 2rem; max-width: 520px; width: 90%; max-height: 80vh; overflow: auto;">
            <h3 style="color: var(--clr-text); font-weight: 600; margin-bottom: 1rem;">نتيجة الاستيراد</h3>
            <div id="report-summary"></div>
            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem; justify-content: flex-end;">
                <a href="index.php?action=assets" class="btn-primary" style="padding: 0.5rem 1.25rem; text-decoration: none;">عرض الأصول</a>
                <button onclick="location.reload()" class="btn-secondary" style="padding: 0.5rem 1.25rem;">استيراد ملف آخر</button>
            </div>
        </div>
    </div>
</div>

<style>
.import-layout { display: grid; grid-template-columns: 380px 1fr; gap: 1rem; margin-top: 1rem; }
.import-mapping-panel { background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; position: sticky; top: 1rem; max-height: calc(100vh - 3rem); display: flex; flex-direction: column; }
.import-preview-panel { background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem; }
.mapping-row { background: var(--clr-bg); border: 1px solid var(--clr-card-border); border-radius: 8px; padding: 0.5rem 0.6rem; transition: border-color 0.2s; }
.mapping-row.mapped { border-color: var(--clr-success); background: rgba(34, 197, 94, 0.05); }
.mapping-row .col-name { color: var(--clr-text); font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem; word-break: break-word; }
.mapping-row .col-sample { color: var(--clr-text-secondary); font-size: 0.72rem; margin-bottom: 0.4rem; font-style: italic; max-height: 2.4em; overflow: hidden; text-overflow: ellipsis; }
.mapping-row select { width: 100%; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 0.8rem; }
.preview-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.preview-table thead { position: sticky; top: 0; background: var(--clr-bg); z-index: 1; }
.preview-table th { padding: 0.55rem 0.7rem; text-align: right; color: var(--clr-text); font-weight: 600; border-bottom: 2px solid var(--clr-card-border); white-space: nowrap; }
.preview-table th.unmapped { color: var(--clr-text-secondary); opacity: 0.55; font-style: italic; }
.preview-table td { padding: 0.5rem 0.7rem; color: var(--clr-text); border-bottom: 1px solid var(--clr-card-border); white-space: nowrap; max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
.preview-table td.empty { color: var(--clr-text-secondary); opacity: 0.4; font-style: italic; }
.preview-table tr.row-error { background: rgba(239, 68, 68, 0.08); }
.preview-table tr.row-error td { color: var(--clr-danger); }
.preview-table tr.row-error td.err-msg { font-size: 0.7rem; font-style: italic; }
.preview-table tbody tr:hover { background: var(--clr-bg); }
@media (max-width: 1024px) {
    .import-layout { grid-template-columns: 1fr; }
    .import-mapping-panel { position: static; max-height: none; }
}
</style>

<script>
const state = {
    rawRows: [],
    columns: [],
    attrDefs: [],
    existingTags: new Set(),
    existingSerials: new Set(),
    mappings: { asset_fields: {}, attributes: {} },
    validRows: [],
    invalidRows: [],
    previewDebounce: null,
    serialCheckDebounce: null,
    savedMappings: JSON.parse(localStorage.getItem('import_mappings') || '[]'),
};

const ASSET_FIELDS = [
    { key: 'asset_tag',       label: 'رقم الأصل (UID)', required: true },
    { key: 'asset_type',      label: 'نوع الأصل',        required: true },
    { key: 'serial_number',   label: 'الرقم التسلسلي (للمصنع)' },
    { key: 'manufacturer',    label: 'الشركة المصنعة' },
    { key: 'model',           label: 'الموديل' },
    { key: 'status',          label: 'الحالة' },
    { key: 'assigned_to',     label: 'مسند إلى' },
    { key: 'department_name', label: 'القسم' },
    { key: 'location',        label: 'الموقع' },
    { key: 'site',            label: 'الفرع' },
    { key: 'supplier_name',   label: 'المورد' },
    { key: 'purchase_date',   label: 'تاريخ الشراء' },
    { key: 'warranty_expiry', label: 'انتهاء الضمان' },
    { key: 'cost',            label: 'التكلفة' },
    { key: 'notes',           label: 'ملاحظات' },
];

const AUTO_MAP = {
    // Core identity — asset_tag is now the UID
    'asset no': 'asset_tag', 'asset no.': 'asset_tag', 'asset number': 'asset_tag',
    'asset id': 'asset_tag', 'رقم الأصل': 'asset_tag',
    'serial number': 'serial_number', 'serial': 'serial_number', 'serial no': 'serial_number',
    'serial no.': 'serial_number', 's/n': 'serial_number', 'الرقم التسلسلي': 'serial_number',
    // Type / state
    'product type': 'asset_type', 'type': 'asset_type', 'device type': 'asset_type',
    'asset type': 'asset_type', 'نوع الأصل': 'asset_type',
    'asset state': 'status', 'state': 'status', 'status': 'status', 'الحالة': 'status',
    // Manufacturer / model as CORE columns now
    'manufacturer': 'manufacturer', 'الشركة المصنعة': 'manufacturer',
    'product': 'model', 'model': 'model', 'الموديل': 'model',
    // Owner
    'assign to user': 'assigned_to', 'assigned to': 'assigned_to',
    'employee id': 'assigned_to', 'user': 'assigned_to', 'الموظف': 'assigned_to',
    'assign to department': 'department_name', 'department': 'department_name', 'القسم': 'department_name',
    // Location
    'site': 'site', 'الفرع': 'site', 'location': 'location', 'الموقع': 'location',
    // Commercial
    'vendor': 'supplier_name', 'supplier': 'supplier_name', 'المورد': 'supplier_name',
    'acquisition date': 'purchase_date', 'purchase date': 'purchase_date', 'تاريخ الشراء': 'purchase_date',
    'warranty expiry': 'warranty_expiry', 'warranty expiry date': 'warranty_expiry', 'انتهاء الضمان': 'warranty_expiry',
    'purchase cost': 'cost', 'cost': 'cost', 'price': 'cost', 'التكلفة': 'cost',
    'notes': 'notes', 'description': 'notes', 'ملاحظات': 'notes',
    // Attributes (only truly variable fields remain here)
    'processor': { attr: 'processor_model' }, 'processor info': { attr: 'processor_model' },
    'cpu': { attr: 'processor_model' }, 'المعالج': { attr: 'processor_model' },
    'ram': { attr: 'ram_gb' }, 'memory': { attr: 'ram_gb' }, 'physical memory': { attr: 'ram_gb' },
    'الذاكرة العشوائية': { attr: 'ram_gb' },
    'storage': { attr: 'storage_gb' }, 'hdd capacity': { attr: 'storage_gb' }, 'disk': { attr: 'storage_gb' },
    'التخزين': { attr: 'storage_gb' },
    'os': { attr: 'os' }, 'operating system': { attr: 'os' }, 'نظام التشغيل': { attr: 'os' },
    'mac': { attr: 'mac_address' }, 'mac address': { attr: 'mac_address' },
    'ip': { attr: 'ip_address' }, 'ip address': { attr: 'ip_address' },
};

const TYPE_MAP = {
    'computer':'desktop','حاسوب':'desktop','كمبيوتر':'desktop','pc':'desktop','desktop':'desktop',
    'كمبيوتر مكتبي':'desktop','كمبيوتر و ملحقاته':'desktop','حاسوب و ملحقاته':'desktop',
    'laptop':'laptop','لابتوب':'laptop','لاب توب':'laptop',
    'monitor':'monitor','شاشة':'monitor','شاشه':'monitor','شاشة للكاميرات':'monitor',
    'printer':'printer','طابعة':'printer','طابعه':'printer','office printer':'office_printer','طابعة مركزية':'office_printer',
    'scanner':'scanner','ماسح':'scanner','ماسح ضوئي':'scanner','سكنر':'scanner','سكانر':'scanner',
    'phone':'phone','هاتف':'phone','تلفون':'phone','land phone':'land_phone','هاتف ارضي':'land_phone','هاتف مكتبي':'land_phone',
    'fax':'fax','فاكس':'fax',
    'switch':'switch','سويتش':'switch','سويتش شبكة':'switch',
    'router':'router','راوتر':'router',
    'ups':'ups',
    'projector':'projector','بروجكتر':'projector','بروجيكتور':'projector',
    'camera':'camera','كاميرا':'camera','كاميره':'camera',
    'ipad':'ipad','جهاز لوحي':'ipad','tablet':'tablet',
    'server':'server','خادم':'server',
    'access_point':'access_point','نقطة وصول':'access_point',
};
const TYPE_LABELS = {
    laptop:'لابتوب', desktop:'كمبيوتر مكتبي', server:'خادم', switch:'سويتش',
    router:'راوتر', access_point:'نقطة وصول', printer:'طابعة', monitor:'شاشة',
    phone:'هاتف', land_phone:'هاتف أرضي', ipad:'جهاز لوحي', tablet:'جهاز لوحي',
    scanner:'ماسح ضوئي', fax:'فاكس', office_printer:'طابعة مكتبية',
    ups:'UPS', projector:'بروجكتر', camera:'كاميرا', other:'أخرى'
};
const STATUS_MAP = {
    'in use':'assigned','in_use':'assigned','inuse':'assigned','قيد الاستخدام':'assigned','مستخدم':'assigned',
    'available':'available','متاح':'available','متاحة':'available',
    'not in use':'available','not_in_use':'available','not inuse':'available',
    'in stock':'in_stock','in_stock':'in_stock','in stock':'in_stock','جديد':'in_stock',
    'in storage':'in_storage','in_storage':'in_storage','في المخزن':'in_storage','مخزن':'in_storage',
    'under_repair':'under_repair','under repair':'under_repair','repair':'under_repair','تحت الصيانة':'under_repair',
    'retired':'retired','متقاعد':'retired',
};
const STATUS_LABELS = {
    in_stock:'في المخزون (جديد)', available:'متاحة', assigned:'مسندة',
    under_repair:'تحت الصيانة', in_storage:'في المخزن', retired:'متقاعد'
};

function escapeHtml(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
function getDef(key) { return state.attrDefs.find(d => d.attr_key === key); }
function normalizeType(raw) { return TYPE_MAP[String(raw || '').toLowerCase().trim()] || 'other'; }
function normalizeStatus(raw) { return STATUS_MAP[String(raw || '').toLowerCase().trim()] || null; }

function normalizeEnumValue(def, raw) {
    const allowed = def.allowed_values || [];
    const s = String(raw || '').trim();
    const lower = s.toLowerCase();

    for (const opt of allowed) {
        if (String(opt).toLowerCase() === lower) return { value: opt, num: null };
    }

    // Fuzzy for OS
    if (def.attr_key === 'os') {
        if (/windows\s*10.*pro/i.test(s)) return { value: 'Windows 10 Pro', num: null };
        if (/windows\s*11.*pro/i.test(s)) return { value: 'Windows 11 Pro', num: null };
        if (/windows\s*10/i.test(s)) return { value: 'Windows 10', num: null };
        if (/windows\s*11/i.test(s)) return { value: 'Windows 11', num: null };
        if (/windows\s*7/i.test(s))  return { value: 'Windows 7',  num: null };
        if (/windows\s*server/i.test(s)) return { value: 'Windows Server', num: null };
        if (/linux|ubuntu|debian|fedora|centos|rhel/i.test(s)) return { value: 'Linux', num: null };
        if (/mac\s*os|macos|os\s*x/i.test(s)) return { value: 'macOS', num: null };
    }

    return null;
}

function normalizeAttrValue(def, raw) {
    const s = String(raw || '').trim();
    if (!s) return null;

    if (def.value_type === 'number') {
        const m = s.replace(/,/g, '.').match(/\d+(\.\d+)?/);
        if (!m) return null;
        return { value: m[0], num: parseFloat(m[0]) };
    }
    if (def.value_type === 'enum') {
        return normalizeEnumValue(def, s);
    }
    return { value: s.replace(/\s+/g, ' '), num: null };
}

function parseDate(raw) {
    const s = String(raw || '').trim();
    if (!s) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    const m = s.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/);
    if (m) {
        const a = parseInt(m[1]), b = parseInt(m[2]), y = m[3];
        let day = a, month = b;
        if (a <= 12 && b > 12) { day = b; month = a; }
        if (month < 1 || month > 12 || day < 1 || day > 31) return null;
        return `${y}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    }
    return null;
}

function parseCost(raw) {
    const s = String(raw || '').replace(/[^\d\.\-]/g, '');
    if (!s || s === '-') return null;
    return isNaN(parseFloat(s)) ? null : parseFloat(s);
}

function parseCSV(text) {
    if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
    const rows = [];
    let row = [], field = '', inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i], next = text[i + 1];
        if (inQuotes) {
            if (c === '"' && next === '"') { field += '"'; i++; }
            else if (c === '"') inQuotes = false;
            else field += c;
        } else {
            if (c === '"') inQuotes = true;
            else if (c === ',') { row.push(field); field = ''; }
            else if (c === '\r' && next === '\n') { row.push(field); rows.push(row); row = []; field = ''; i++; }
            else if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
            else if (c === '\r') { /* skip */ }
            else field += c;
        }
    }
    if (field !== '' || row.length > 0) { row.push(field); rows.push(row); }
    return rows.filter(r => r.some(f => String(f).trim() !== ''));
}

document.getElementById('csv-file').addEventListener('change', function () {
    if (!this.files[0]) return;
    const status = document.getElementById('upload-status');
    status.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري القراءة...';
    const reader = new FileReader();
    reader.onload = e => {
        try {
            const rows = parseCSV(e.target.result);
            if (rows.length < 2) throw new Error('الملف فارغ أو يحتوي على صف عنوان فقط');
            const header = rows[0].map(h => String(h).trim().replace(/\s+/g, ' '));
            const dataRows = rows.slice(1).map(r => {
                const obj = {};
                header.forEach((h, i) => obj[h] = r[i] ?? '');
                return obj;
            });
            state.columns = header;
            state.rawRows = dataRows;
            status.innerHTML = `<span style="color: var(--clr-success);">✓ ${dataRows.length} صف — ${header.length} عمود</span>`;
            document.getElementById('upload-zone').style.display = 'none';
            document.getElementById('workspace').style.display = 'block';
            initWorkflow();
        } catch (err) {
            status.innerHTML = `<span style="color: var(--clr-danger);">${escapeHtml(err.message)}</span>`;
        }
    };
    reader.readAsText(this.files[0], 'UTF-8');
});

function initWorkflow() {
    autoMap();
    fetch('index.php?action=api_get_attribute_definitions')
        .then(r => r.json())
        .then(d => {
            state.attrDefs = d.success ? d.definitions : [];
            populateSavedDropdown();
            renderMappingPanel();
            runValidationAndPreview();
        })
        .catch(err => {
            console.error(err);
            renderMappingPanel();
            runValidationAndPreview();
        });
}

function autoMap() {
    state.mappings = { asset_fields: {}, attributes: {} };
    state.columns.forEach(col => {
        // Skip columns that are entirely empty in this file
        const hasData = state.rawRows.some(r => String(r[col] || '').trim() !== '');
        if (!hasData) return;

        const key = col.toLowerCase().trim().replace(/\s+/g, ' ');
        const sug = AUTO_MAP[key];
        if (!sug) return;

        if (typeof sug === 'string') {
            if (!state.mappings.asset_fields[sug]) state.mappings.asset_fields[sug] = col;
        } else if (sug.attr) {
            if (!state.mappings.attributes[sug.attr]) state.mappings.attributes[sug.attr] = col;
        }
    });
}

function populateSavedDropdown() {
    const sel = document.getElementById('load-mapping-select');
    sel.innerHTML = '<option value="">قالب محفوظ...</option>';
    state.savedMappings.forEach((m, i) => {
        const o = document.createElement('option');
        o.value = i;
        o.textContent = m.name;
        sel.appendChild(o);
    });
}

function renderMappingPanel() {
    const list = document.getElementById('mapping-list');
    list.innerHTML = '';
    state.columns.forEach(col => {
        const target = findTarget(col);
        const sample = String((state.rawRows[0] || {})[col] || '').substring(0, 40);
        const row = document.createElement('div');
        row.className = 'mapping-row' + (target ? ' mapped' : '');

        const name = document.createElement('div');
        name.className = 'col-name';
        name.innerHTML = `<i class="fas fa-columns ml-1" style="opacity:0.5;font-size:0.7rem;"></i>${escapeHtml(col)}`;
        row.appendChild(name);

        if (sample) {
            const s = document.createElement('div');
            s.className = 'col-sample';
            s.textContent = 'مثال: ' + sample;
            row.appendChild(s);
        }

        const sel = document.createElement('select');
        sel.innerHTML = '<option value="">— تجاهل —</option>';

        const og1 = document.createElement('optgroup');
        og1.label = 'حقول الأصل';
        ASSET_FIELDS.forEach(f => {
            const o = document.createElement('option');
            o.value = 'asset:' + f.key;
            o.textContent = f.label + (f.required ? ' *' : '');
            if (target === o.value) o.selected = true;
            og1.appendChild(o);
        });
        sel.appendChild(og1);

        if (state.attrDefs.length) {
            const og2 = document.createElement('optgroup');
            og2.label = 'الخصائص';
            state.attrDefs.forEach(d => {
                const o = document.createElement('option');
                o.value = 'attr:' + d.attr_key;
                o.textContent = d.display_label_ar;
                if (target === o.value) o.selected = true;
                og2.appendChild(o);
            });
            sel.appendChild(og2);
        }

        sel.addEventListener('change', () => onMappingChange(col, sel.value));
        row.appendChild(sel);
        list.appendChild(row);
    });
}

function findTarget(col) {
    for (const [k, v] of Object.entries(state.mappings.asset_fields)) if (v === col) return 'asset:' + k;
    for (const [k, v] of Object.entries(state.mappings.attributes))   if (v === col) return 'attr:' + k;
    return '';
}

function onMappingChange(col, target) {
    Object.keys(state.mappings.asset_fields).forEach(k => { if (state.mappings.asset_fields[k] === col) delete state.mappings.asset_fields[k]; });
    Object.keys(state.mappings.attributes).forEach(k => { if (state.mappings.attributes[k] === col) delete state.mappings.attributes[k]; });
    if (target.startsWith('asset:')) {
        const k = target.substring(6);
        delete state.mappings.asset_fields[k];
        state.mappings.asset_fields[k] = col;
    } else if (target.startsWith('attr:')) {
        const k = target.substring(5);
        delete state.mappings.attributes[k];
        state.mappings.attributes[k] = col;
    }
    renderMappingPanel();
    scheduleValidation();
}

function scheduleValidation() {
    clearTimeout(state.previewDebounce);
    state.previewDebounce = setTimeout(runValidationAndPreview, 200);
}

function runValidationAndPreview() {
    const tagCol = state.mappings.asset_fields.asset_tag;
    if (!tagCol) {
        state.existingTags = new Set();
        state.existingSerials = new Set();
        validateAll();
        renderPreview();
        return;
    }
    clearTimeout(state.serialCheckDebounce);
    state.serialCheckDebounce = setTimeout(() => {
        const tags = state.rawRows.map(r => String(r[tagCol] || '').trim()).filter(Boolean);
        const serialCol = state.mappings.asset_fields.serial_number;
        const serials = serialCol ? state.rawRows.map(r => String(r[serialCol] || '').trim()).filter(Boolean) : [];

        const fd = new URLSearchParams();
        fd.append('tags', JSON.stringify(tags));
        fd.append('serials', JSON.stringify(serials));

        fetch('index.php?action=api_import_check_duplicates', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: fd.toString()
        })
        .then(r => r.json())
        .then(d => {
            state.existingTags = new Set(d.tags || []);
            state.existingSerials = new Set(d.serials || []);
            validateAll();
            renderPreview();
        })
        .catch(() => {
            state.existingTags = new Set();
            state.existingSerials = new Set();
            validateAll();
            renderPreview();
        });
    }, 500);
}

function validateAll() {
    state.validRows = [];
    state.invalidRows = [];
    state.rawRows.forEach((row, idx) => {
        const result = validateRow(row, idx);
        if (result.errors.length === 0) {
            state.validRows.push({ index: idx, data: result.data });
        } else {
            state.invalidRows.push({ index: idx, errors: result.errors });
        }
    });
    updateValidationSummary();
}

function validateRow(row, idx) {
    const errors = [];
    const data = { asset: {}, attributes: {} };
    const get = key => {
        const col = state.mappings.asset_fields[key];
        return col ? String(row[col] || '').trim() : '';
    };

    // Asset tag — REQUIRED now
    const tag = get('asset_tag');
    if (!tag) errors.push('رقم الأصل مطلوب');
    else if (state.existingTags.has(tag)) errors.push('رقم الأصل مكرر: ' + tag);
    else data.asset.asset_tag = tag;

    // Serial — optional
    const serial = get('serial_number');
    if (serial) {
        if (state.existingSerials.has(serial)) errors.push('الرقم التسلسلي مكرر: ' + serial);
        else data.asset.serial_number = serial;
    }

    // Type — required
    const typeRaw = get('asset_type');
    if (!typeRaw) errors.push('نوع الأصل مطلوب');
    else data.asset.asset_type = normalizeType(typeRaw);

    // Manufacturer / model
    const mfr = get('manufacturer');
    if (mfr) data.asset.manufacturer = mfr;
    const mdl = get('model');
    if (mdl) data.asset.model = mdl;

    // Status
    const statusRaw = get('status');
    const status = normalizeStatus(statusRaw) || (get('assigned_to') ? 'assigned' : 'available');
    data.asset.status = status;

    // Owner / dept
    const assignedName = get('assigned_to');
    if (assignedName) data.asset.assigned_to_name = assignedName;
    const deptName = get('department_name');
    if (deptName) data.asset.department_name = deptName;

    // Location / supplier
    const locName = get('location') || get('site');
    if (locName) data.asset.location_name = locName;
    const supName = get('supplier_name');
    if (supName) data.asset.supplier_name = supName;

    // Dates
    const pd = get('purchase_date');
    if (pd) {
        const parsed = parseDate(pd);
        if (!parsed) errors.push('تاريخ الشراء غير صالح');
        else data.asset.purchase_date = parsed;
    }
    const we = get('warranty_expiry');
    if (we) {
        const parsed = parseDate(we);
        if (!parsed) errors.push('تاريخ انتهاء الضمان غير صالح');
        else data.asset.warranty_expiry = parsed;
    }

    // Cost
    const costRaw = get('cost');
    if (costRaw) {
        const c = parseCost(costRaw);
        if (c === null) errors.push('التكلفة غير صالحة');
        else data.asset.cost = c;
    }

    // Notes
    const notes = get('notes');
    if (notes) data.asset.notes = notes;

    // Attributes — treat failure as warning (skip attribute, keep row)
    Object.entries(state.mappings.attributes).forEach(([key, col]) => {
        const raw = String(row[col] || '').trim();
        if (!raw) return;
        const def = getDef(key);
        if (!def) return;
        if (def.applies_to_types && !def.applies_to_types.includes(data.asset.asset_type)) return;
        const norm = normalizeAttrValue(def, raw);
        if (norm) data.attributes[key] = norm;
        // else: silently skip invalid attribute — row is still valid
    });

    return { errors, data };
}

function updateValidationSummary() {
    const wrap = document.getElementById('validation-summary');
    const total = state.rawRows.length;
    const valid = state.validRows.length;
    const invalid = state.invalidRows.length;
    const ready = !!state.mappings.asset_fields.asset_tag && !!state.mappings.asset_fields.asset_type;

    let html = `<div>إجمالي: <strong>${total}</strong></div>`;
    html += `<div style="color: var(--clr-success);">✓ صالح: <strong>${valid}</strong></div>`;
    if (invalid) html += `<div style="color: var(--clr-danger);">✗ أخطاء: <strong>${invalid}</strong></div>`;
    if (!ready) html += `<div style="color: var(--clr-warning); font-size: 0.75rem; margin-top: 0.25rem;">يجب ربط رقم الأصل ونوع الأصل</div>`;
    wrap.innerHTML = html;

    const btn = document.getElementById('import-btn');
    btn.disabled = !ready || valid === 0;
    btn.style.opacity = btn.disabled ? '0.5' : '1';
    btn.style.cursor = btn.disabled ? 'not-allowed' : 'pointer';
}

function renderPreview() {
    const inner = document.getElementById('preview-table-inner');
    const meta  = document.getElementById('preview-meta');
    const cols = [];
    ['asset_tag','asset_type','manufacturer','model','serial_number','status','assigned_to','location'].forEach(k => {
        const f = ASSET_FIELDS.find(x => x.key === k);
        cols.push({ key: k, label: f.label, mapped: !!state.mappings.asset_fields[k] });
    });
    Object.keys(state.mappings.asset_fields).forEach(k => {
        if (['asset_tag','asset_type','manufacturer','model','serial_number','status','assigned_to','location'].includes(k)) return;
        const f = ASSET_FIELDS.find(x => x.key === k);
        if (f) cols.push({ key: k, label: f.label, mapped: true });
    });
    Object.keys(state.mappings.attributes).forEach(k => {
        const d = getDef(k);
        if (d) cols.push({ key: 'attr:' + k, label: d.display_label_ar, mapped: true });
    });

    const statusByIndex = {};
    state.invalidRows.forEach(r => statusByIndex[r.index] = { type: 'error', msgs: r.errors });
    state.validRows.forEach(r => statusByIndex[r.index] = { type: 'valid', data: r.data });

    let html = '<table class="preview-table"><thead><tr>';
    cols.forEach(c => { html += `<th class="${c.mapped ? '' : 'unmapped'}">${escapeHtml(c.label)}</th>`; });
    html += '<th>الحالة</th></tr></thead><tbody>';

    const maxShow = Math.min(state.rawRows.length, 50);
    for (let i = 0; i < maxShow; i++) {
        const s = statusByIndex[i] || { type: 'unknown' };
        const isErr = s.type === 'error';
        html += `<tr class="${isErr ? 'row-error' : ''}">`;
        cols.forEach(c => {
            let v = '';
            if (s.type === 'valid') {
                v = s.data.asset[c.key] ?? s.data.attributes[c.key.replace('attr:','')]?.value ?? '';
            } else {
                const col = state.mappings.asset_fields[c.key] || state.mappings.attributes[c.key.replace('attr:','')];
                v = col ? (state.rawRows[i]?.[col] || '') : '';
            }
            const empty = (v === undefined || v === null || v === '');
            html += empty ? '<td class="empty">—</td>' : `<td title="${escapeHtml(v)}">${escapeHtml(v)}</td>`;
        });
        if (isErr) html += `<td class="err-msg">${escapeHtml(s.msgs.join('؛ '))}</td>`;
        else if (s.type === 'valid') html += `<td style="color: var(--clr-success);">✓</td>`;
        else html += `<td class="empty">—</td>`;
        html += '</tr>';
    }
    if (state.rawRows.length > maxShow) {
        html += `<tr><td colspan="${cols.length+1}" style="text-align:center;padding:1rem;color:var(--clr-text-secondary);font-style:italic;">عرض أول ${maxShow} من ${state.rawRows.length} صف</td></tr>`;
    }
    html += '</tbody></table>';
    inner.innerHTML = html;
    meta.textContent = `${state.validRows.length} صالح / ${state.invalidRows.length} خطأ من ${state.rawRows.length}`;
}

function saveMapping() {
    const name = prompt('اسم القالب:');
    if (!name || !name.trim()) return;
    state.savedMappings.push({ name: name.trim(), mapping: state.mappings });
    localStorage.setItem('import_mappings', JSON.stringify(state.savedMappings));
    populateSavedDropdown();
    alert('✅ تم حفظ القالب');
}

function loadSavedMapping(idx) {
    if (idx === '') return;
    const m = state.savedMappings[parseInt(idx)];
    if (!m) return;
    const csvCols = new Set(state.columns);
    const applied = { asset_fields: {}, attributes: {} };
    Object.entries(m.mapping.asset_fields || {}).forEach(([k, c]) => { if (csvCols.has(c)) applied.asset_fields[k] = c; });
    Object.entries(m.mapping.attributes || {}).forEach(([k, c]) => { if (csvCols.has(c)) applied.attributes[k] = c; });
    state.mappings = applied;
    renderMappingPanel();
    runValidationAndPreview();
}

function executeImport() {
    if (state.validRows.length === 0) return alert('لا توجد صفوف صالحة للاستيراد');
    if (!confirm(`استيراد ${state.validRows.length} صف؟\n${state.invalidRows.length} صف بها أخطاء سيتم تجاهلها.`)) return;

    const btn = document.getElementById('import-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري الاستيراد...';

    const payload = state.validRows.map(r => r.data);
    const fd = new URLSearchParams();
    fd.append('rows', JSON.stringify(payload));

    fetch('index.php?action=api_import_bulk_insert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd.toString()
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check ml-1"></i> تأكيد الاستيراد';
        if (!d.success) return alert(d.message || 'فشل');
        showReport(d.report);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check ml-1"></i> تأكيد الاستيراد';
        alert('خطأ في الاتصال');
    });
}

function showReport(r) {
    const summary = document.getElementById('report-summary');
    summary.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 1rem;">
            <div style="background: var(--clr-bg); padding: 0.75rem; border-radius: 8px; text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--clr-text);">${r.total}</div>
                <div style="color: var(--clr-text-secondary); font-size: 0.7rem;">إجمالي</div>
            </div>
            <div style="background: var(--clr-success); color: white; padding: 0.75rem; border-radius: 8px; text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 700;">${r.imported}</div>
                <div style="font-size: 0.7rem;">مستورد</div>
            </div>
            <div style="background: var(--clr-warning); color: white; padding: 0.75rem; border-radius: 8px; text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 700;">${r.skipped}</div>
                <div style="font-size: 0.7rem;">فاشل</div>
            </div>
        </div>
        ${r.errors && r.errors.length ? `
            <details style="background: var(--clr-bg); padding: 0.75rem; border-radius: 8px; font-size: 0.8rem; max-height: 200px; overflow: auto;">
                <summary style="cursor: pointer; font-weight: 600; color: var(--clr-text);">الأخطاء</summary>
                <ul style="margin: 0.5rem 0 0 1rem; color: var(--clr-danger);">
                    ${r.errors.slice(0, 50).map(e => `<li>صف ${e.row}: ${escapeHtml(e.message)}</li>`).join('')}
                </ul>
            </details>
        ` : ''}
    `;
    document.getElementById('report-modal').style.display = 'flex';
}
</script>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>