<?php
// pages/asset_edit.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

$assetId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $assetId > 0;

$assetModel = new \Ministry\Models\Asset();
$asset = null;
$existingAttributes = [];
if ($isEdit) {
    $asset = $assetModel->findById($assetId);
    if (!$asset) {
        header('Location: index.php?action=assets');
        exit;
    }
    $existingAttributes = $assetModel->getAttributes($assetId);
}

$error = null;

$entityModel = new \Ministry\Models\Entity();
$entities = $entityModel->getAssignable();
$supplierModel = new \Ministry\Models\Supplier();
$suppliers = $supplierModel->findAll();
$locationModel = new \Ministry\Models\Location();
$locations = $locationModel->findAll();

function fv($field, $default = '', $asset = null)
{
    if (isset($_POST[$field]))
        return $_POST[$field];
    if ($asset !== null && isset($asset[$field]))
        return $asset[$field];
    return $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_type = $_POST['asset_type'] ?? 'other';
    $supplier_id = $_POST['supplier_id'] ?: null;
    $warranty_provider_id = $_POST['warranty_provider_id'] ?: null;
    $location_id = $_POST['location_id'] ?: null;
    $assigned_to = $_POST['assigned_to'] ?: null;
    $serial_number = trim($_POST['serial_number'] ?? '');
    $asset_tag = trim($_POST['asset_tag'] ?? '');
    $status = $_POST['status'] ?? 'available';
    $purchase_date = $_POST['purchase_date'] ?: null;
    $warranty_expiry = $_POST['warranty_expiry'] ?: null;
    $cost = $_POST['cost'] ?: null;
    $notes = trim($_POST['notes'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $department_id = $_POST['department_id'] ?: null;
    $attrsJson = $_POST['attributes_json'] ?? '[]';
    $attrsData = json_decode($attrsJson, true) ?: [];
    $attributePairs = [];
    foreach ($attrsData as $k => $v) {
        if ($v !== '' && $v !== null)
            $attributePairs[] = ['key' => $k, 'value' => $v];
    }

    if (empty($serial_number)) {
        $error = 'الرقم التسلسلي مطلوب.';
    } else {
        $writer = new \Ministry\Services\AssetAttributeWriter($pdo);
        $validation = $writer->validate($attributePairs, $asset_type);

        if (!$validation['success']) {
            $error = implode(' — ', $validation['errors']);
        } else {
            $data = [
                'asset_type' => $asset_type,
                'supplier_id' => $supplier_id,
                'warranty_provider_id' => $warranty_provider_id,
                'location_id' => $location_id,
                'assigned_to' => $assigned_to,
                'serial_number' => $serial_number,
                'asset_tag' => $asset_tag,
                'status' => $status,
                'purchase_date' => $purchase_date,
                'warranty_expiry' => $warranty_expiry,
                'purchase_cost' => $cost,
                'notes' => $notes,
                'manufacturer' => $manufacturer ?: null,
                'model' => $model ?: null,
                'department_id' => $department_id,
            ];

            if ($isEdit) {
                $result = $assetModel->update($assetId, $data);
                $savedId = $assetId;
            } else {
                $result = $assetModel->create($data);
                $savedId = (int) $result;
            }

            if ($result) {
                $writer->save($savedId, $validation['normalized']);
                header('Location: index.php?action=assets');
                exit;
            } else {
                $error = 'حدث خطأ أثناء الحفظ. تأكد من أن الرقم التسلسلي غير مكرر.';
            }
        }
    }
}

// Fix: pass existing attributes to the hidden field on GET
$initialAttrsJson = $_POST['attributes_json']
    ?? (!empty($existingAttributes)
        ? json_encode($existingAttributes, JSON_UNESCAPED_UNICODE)
        : '{}');

$currentType = $_POST['asset_type'] ?? $asset['asset_type'] ?? 'laptop';
$currentStatus = $_POST['status'] ?? $asset['status'] ?? 'available';
$currentAssigned = $_POST['assigned_to'] ?? $asset['assigned_to'] ?? '';
$currentSupplier = $_POST['supplier_id'] ?? $asset['supplier_id'] ?? '';
$currentWarranty = $_POST['warranty_provider_id'] ?? $asset['warranty_provider_id'] ?? '';
$currentLocation = $_POST['location_id'] ?? $asset['location_id'] ?? '';

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <h1 class="text-2xl font-bold mb-6" style="color: var(--clr-text);">
        <?php echo $isEdit ? 'تعديل أصل' : 'إضافة أصل جديد'; ?>
    </h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"
            style="background: var(--clr-danger); color: white; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="asset-edit-layout">

        <!-- RIGHT column (desktop): form + attributes -->
        <div class="form-column">
            <form method="POST" action="" id="asset-form">

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-laptop ml-2" style="color: var(--clr-primary);"></i> بيانات الأصل
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">الرقم التسلسلي *</label>
                            <input type="text" name="serial_number" class="form-input"
                                value="<?php echo htmlspecialchars(fv('serial_number', '', $asset)); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">علامة الأصل</label>
                            <input type="text" name="asset_tag" class="form-input"
                                value="<?php echo htmlspecialchars(fv('asset_tag', '', $asset)); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">النوع *</label>
                            <select id="asset_type_select" name="asset_type" class="form-input" required>
                                <?php
                                $types = ['laptop' => 'لابتوب', 'desktop' => 'كمبيوتر مكتبي', 'server' => 'خادم', 'switch' => 'سويتش', 'router' => 'راوتر', 'access_point' => 'نقطة وصول', 'printer' => 'طابعة', 'monitor' => 'شاشة', 'phone' => 'هاتف', 'ipad' => 'جهاز لوحي', 'scanner' => 'ماسح ضوئي', 'fax' => 'فاكس', 'ups' => 'UPS', 'projector' => 'بروجكتر', 'camera' => 'كاميرا', 'other' => 'أخرى'];
                                foreach ($types as $val => $label):
                                    echo '<option value="' . $val . '"' . ($currentType === $val ? ' selected' : '') . '>' . $label . '</option>';
                                endforeach;
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-input">
                                <option value="available" <?php echo $currentStatus === 'available' ? 'selected' : ''; ?>>
                                    متاحة</option>
                                <option value="assigned" <?php echo $currentStatus === 'assigned' ? 'selected' : ''; ?>>
                                    مسندة
                                </option>
                                <option value="under_repair" <?php echo $currentStatus === 'under_repair' ? 'selected' : ''; ?>>تحت الصيانة</option>
                                <option value="retired" <?php echo $currentStatus === 'retired' ? 'selected' : ''; ?>>
                                    متقاعد
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">مسند إلى</label>
                            <select name="assigned_to" id="assigned_to_select" class="form-input">
                                <option value="">غير مسند</option>
                                <?php foreach ($entities as $entity): ?>
                                    <option value="<?php echo $entity['entity_id']; ?>" <?php echo ($currentAssigned == $entity['entity_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($entity['full_name']); ?>
                                        (<?php echo $entity['type']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"
                                style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الشركة
                                المصنعة</label>
                            <input type="text" name="manufacturer" class="form-input"
                                value="<?php echo htmlspecialchars($asset['manufacturer'] ?? ($_POST['manufacturer'] ?? '')); ?>"
                                style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);"
                                placeholder="مثال: HP, Lenovo, Acer">
                        </div>

                        <div class="form-group">
                            <label class="form-label"
                                style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الموديل</label>
                            <input type="text" name="model" class="form-input"
                                value="<?php echo htmlspecialchars($asset['model'] ?? ($_POST['model'] ?? '')); ?>"
                                style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);"
                                placeholder="مثال: ProDesk 490 G3">
                        </div>

                        <div class="form-group">
                            <label class="form-label"
                                style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">القسم</label>
                            <select name="department_id" class="form-input"
                                style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                                <option value="">اختر القسم</option>
                                <?php
                                $deptModel = new \Ministry\Models\Department();
                                $allDepts = $deptModel->findAll();
                                $currentDept = $asset['department_id'] ?? ($_POST['department_id'] ?? '');
                                foreach ($allDepts as $d): ?>
                                    <option value="<?php echo $d['department_id']; ?>" <?php echo ($currentDept == $d['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['department_name_ar']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الموقع</label>
                            <select name="location_id" class="form-input">
                                <option value="">اختر الموقع</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo $loc['location_id']; ?>" <?php echo ($currentLocation == $loc['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المورد</label>
                            <select name="supplier_id" class="form-input">
                                <option value="">اختر المورد</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['supplier_id']; ?>" <?php echo ($currentSupplier == $sup['supplier_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sup['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">مقدم الضمان</label>
                            <select name="warranty_provider_id" class="form-input">
                                <option value="">اختر مقدم الضمان</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['supplier_id']; ?>" <?php echo ($currentWarranty == $sup['supplier_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sup['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">تاريخ الشراء</label>
                            <input type="date" name="purchase_date" class="form-input"
                                value="<?php echo htmlspecialchars(fv('purchase_date', '', $asset)); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">انتهاء الضمان</label>
                            <input type="date" name="warranty_expiry" class="form-input"
                                value="<?php echo htmlspecialchars(fv('warranty_expiry', '', $asset)); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">التكلفة</label>
                            <input type="number" step="0.01" name="cost" class="form-input"
                                value="<?php echo htmlspecialchars(fv('cost', '', $asset)); ?>">
                        </div>
                        <div class="form-group md:col-span-2">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" rows="2"
                                class="form-input"><?php echo htmlspecialchars(fv('notes', '', $asset)); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="attributes-section">
                    <h3 class="attributes-title">
                        <i class="fas fa-microchip ml-2" style="color: var(--clr-primary);"></i>
                        المواصفات التفصيلية
                    </h3>
                    <p class="attributes-hint">
                        أضف الخصائص المناسبة لهذا النوع من الأصول. الحقول المتاحة تعتمد على النوع المختار.
                    </p>
                    <div id="attributes-container" class="attributes-list"></div>
                    <div class="attributes-picker-row">
                        <select id="attr-picker" class="form-input attributes-picker">
                            <option value="">+ إضافة خاصية...</option>
                        </select>
                        <span id="attr-picker-hint" class="attributes-picker-hint"></span>
                    </div>
                    <input type="hidden" name="attributes_json" id="attributes_json"
                        value="<?php echo htmlspecialchars($initialAttrsJson); ?>">
                </div>

                <div class="form-actions">
                    <a href="index.php?action=assets" class="btn-secondary">إلغاء</a>
                    <button type="submit" class="btn-primary">حفظ</button>
                </div>
            </form>
        </div>

        <!-- LEFT column (desktop): related assets -->
        <aside class="related-column">
            <div id="related-assets-container"></div>
        </aside>
    </div>
</div>

<style>
    .asset-edit-layout {
        display: grid;
        grid-template-columns: 1fr 0.35fr;
        gap: 1.5rem;
        align-items: start;
    }

    .related-column {
        position: sticky;
        top: 1rem;
    }

    .form-section {
        background: var(--clr-card-bg);
        border: 1px solid var(--clr-card-border);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--clr-text);
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--clr-card-border);
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--clr-text);
    }

    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--clr-input-border);
        border-radius: 6px;
        background: var(--clr-input-bg);
        color: var(--clr-text);
    }

    .attributes-section {
        background: var(--clr-card-bg);
        border: 2px solid var(--clr-primary);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .attributes-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--clr-text);
        margin-bottom: 0.25rem;
    }

    .attributes-hint {
        color: var(--clr-text-secondary);
        font-size: 0.85rem;
        margin-bottom: 1rem;
    }

    .attributes-list {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }

    .attributes-picker-row {
        margin-top: 0.75rem;
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .attributes-picker {
        width: auto;
        min-width: 220px;
    }

    .attributes-picker-hint {
        color: var(--clr-text-secondary);
        font-size: 0.8rem;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1rem;
    }

    .related-asset-link:hover {
        border-color: var(--clr-primary) !important;
        transform: translateY(-1px);
    }

    @media (max-width: 1024px) {
        .asset-edit-layout {
            grid-template-columns: 1fr;
        }

        .related-column {
            order: 2;
            position: static;
        }

        .form-column {
            order: 1;
        }
    }
</style>

<script>
    (function () {
        const CURRENT_ASSET_ID = <?php echo (int) $assetId; ?>;
        const assignedToSelect = document.getElementById('assigned_to_select');
        const typeSelect = document.getElementById('asset_type_select');
        const picker = document.getElementById('attr-picker');
        const pickerHint = document.getElementById('attr-picker-hint');
        const container = document.getElementById('attributes-container');
        const hidden = document.getElementById('attributes_json');

        let attrDefs = [];
        let selectedAttrs = {};
        let lastAssetType = typeSelect ? typeSelect.value : '';

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = String(s ?? '');
            return d.innerHTML;
        }

        // ---------------- RELATED ASSETS ----------------
        function loadRelatedAssets(entityId) {
            const rc = document.getElementById('related-assets-container');
            if (!rc) return;

            if (!entityId) {
                rc.innerHTML = `<div style="padding: 1.5rem 1rem; text-align: center; color: var(--clr-text-secondary); background: var(--clr-card-bg); border: 1px dashed var(--clr-card-border); border-radius: 12px;">
                <i class="fas fa-user-slash" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>
                <div style="font-size: 0.85rem;">لم يتم إسناد هذا الأصل لأي كيان</div>
            </div>`;
                return;
            }

            rc.innerHTML = '<div style="padding: 1.5rem; text-align: center; color: var(--clr-text-secondary);"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</div>';

            fetch(`index.php?action=api_get_related_assets&entity_id=${encodeURIComponent(entityId)}&exclude_asset_id=${CURRENT_ASSET_ID}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) renderRelated(data, rc);
                    else rc.innerHTML = `<div style="padding: 1rem; color: var(--clr-danger); text-align: center; font-size: 0.85rem;">${escapeHtml(data.message || 'خطأ')}</div>`;
                })
                .catch(err => {
                    console.error('Related fetch error', err);
                    rc.innerHTML = '<div style="padding: 1rem; color: var(--clr-danger); text-align: center; font-size: 0.85rem;">خطأ في الاتصال</div>';
                });
        }

        function renderRelated(data, rc) {
            const typeLabel = { employee: 'موظف', external_tech: 'فني خارجي', repair_shop: 'مركز صيانة' }[data.entity_type] || '';
            const assets = data.assets || [];
            const total = data.total_assigned || 0;
            const heading = CURRENT_ASSET_ID > 0 ? 'أصول أخرى مسندة إلى' : 'أصول مسندة إلى';
            const typeColors = { laptop: '#3b82f6', desktop: '#10b981', server: '#8b5cf6', switch: '#f59e0b', router: '#ef4444', access_point: '#06b6d4', printer: '#ec4899', monitor: '#f97316', other: '#6b7280' };

            let html = `<div style="background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 12px; padding: 1rem;">
            <div style="margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--clr-card-border);">
                <div style="font-weight: 600; color: var(--clr-text); font-size: 0.95rem;">
                    <i class="fas fa-link ml-2" style="color: var(--clr-primary);"></i>${heading}
                </div>
                <div style="color: var(--clr-text); font-weight: 500; margin-top: 0.35rem; font-size: 0.9rem;">${escapeHtml(data.entity_name)}</div>
                <div style="color: var(--clr-text-secondary); font-size: 0.75rem; margin-top: 0.15rem;">
                    ${typeLabel}${typeLabel ? ' — ' : ''}إجمالي ${total} ${total === 1 ? 'أصل' : 'أصول'}
                </div>
            </div>`;

            if (assets.length === 0) {
                html += `<div style="padding: 1.25rem 1rem; text-align: center; background: var(--clr-bg); border: 1px dashed var(--clr-card-border); border-radius: 8px;">
                <i class="fas fa-inbox" style="font-size: 1.5rem; color: var(--clr-text-secondary); opacity: 0.6; margin-bottom: 0.5rem; display: block;"></i>
                <div style="color: var(--clr-text-secondary); font-size: 0.8rem;">${CURRENT_ASSET_ID > 0 ? 'لا توجد أصول أخرى مسندة لهذا الكيان' : 'سيظهر هنا الأصول المسندة بعد حفظ هذا الأصل'}</div>
            </div>`;
            } else {
                html += '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
                assets.forEach(a => {
                    const color = typeColors[a.asset_type] || '#6b7280';
                    const label = a.asset_tag || a.serial_number || '---';
                    const initial = label.charAt(0).toUpperCase();
                    html += `<a href="index.php?action=asset_edit&id=${a.asset_id}" class="related-asset-link" style="display: flex; gap: 0.6rem; align-items: center; padding: 0.6rem; background: var(--clr-bg); border: 1px solid var(--clr-card-border); border-radius: 8px; text-decoration: none; color: inherit; transition: border-color 0.2s, transform 0.1s;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: ${color}; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; flex-shrink: 0;">${escapeHtml(initial)}</div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: var(--clr-text); font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(label)}</div>
                        <div style="font-size: 0.7rem; color: var(--clr-text-secondary);">${escapeHtml(a.asset_type)}${a.serial_number && a.asset_tag ? ' — ' + escapeHtml(a.serial_number) : ''}</div>
                    </div>
                    <i class="fas fa-chevron-left" style="color: var(--clr-text-secondary); font-size: 0.7rem;"></i>
                </a>`;
                });
                html += '</div>';
            }

            html += '</div>';
            rc.innerHTML = html;
        }

        // ---------------- ATTRIBUTES ----------------
        function restoreInitialAttributes() {
            if (!hidden) return;
            try {
                const raw = hidden.value;
                if (!raw) return;
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') {
                    Object.entries(parsed).forEach(([k, v]) => {
                        if (v !== '' && v !== null && v !== undefined) selectedAttrs[k] = v;
                    });
                }
            } catch (e) { console.error('Bad attributes_json', e); }
        }

        function loadDefinitions(type) {
            return fetch(`index.php?action=api_get_attribute_definitions&asset_type=${encodeURIComponent(type)}`)
                .then(r => r.json())
                .then(data => {
                    attrDefs = (data.success && data.definitions) ? data.definitions : [];
                    return attrDefs;
                })
                .catch(() => { attrDefs = []; return []; });
        }

        function getDef(key) { return attrDefs.find(d => d.attr_key === key); }

        function isVisible(def) {
            const rule = def.visible_when;
            if (!rule) return true;
            const prereq = selectedAttrs[rule.attr_key];
            if (prereq === undefined || prereq === '') return false;
            const actual = String(prereq);
            const expected = rule.value;
            switch (rule.operator || '=') {
                case '=': return actual === String(expected);
                case 'in': return Array.isArray(expected) && expected.includes(actual);
                case 'starts_with': return actual.startsWith(String(expected));
                case 'contains': return actual.includes(String(expected));
                default: return true;
            }
        }

        function refreshPicker() {
            if (!picker) return;
            const used = new Set(Object.keys(selectedAttrs));
            const available = attrDefs.filter(d => !used.has(d.attr_key) && isVisible(d));

            picker.innerHTML = '<option value="">+ إضافة خاصية...</option>';
            available.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.attr_key;
                opt.textContent = d.display_label_ar;
                picker.appendChild(opt);
            });

            if (available.length === 0) {
                picker.disabled = true;
                if (pickerHint) pickerHint.textContent = attrDefs.length === 0 ? 'لا توجد خصائص لهذا النوع' : 'تمت إضافة جميع الخصائص المتاحة';
            } else {
                picker.disabled = false;
                if (pickerHint) pickerHint.textContent = '';
            }
        }

        function renderAttributes() {
            if (!container) return;
            container.innerHTML = '';
            Object.keys(selectedAttrs).forEach(key => {
                const def = getDef(key);
                if (def) renderRow(container, def, selectedAttrs[key]);
            });
            updateHiddenJson();
            refreshPicker();
        }

        function renderRow(parent, def, value) {
            const row = document.createElement('div');
            row.style.cssText = 'display: flex; gap: 0.5rem; align-items: center; background: var(--clr-bg); padding: 0.5rem 0.75rem; border: 1px solid var(--clr-card-border); border-radius: 8px;';

            const label = document.createElement('div');
            label.style.cssText = 'flex: 1; min-width: 140px; color: var(--clr-text); font-weight: 500; font-size: 0.9rem;';
            label.textContent = def.display_label_ar;
            row.appendChild(label);

            const valueWrap = document.createElement('div');
            valueWrap.style.cssText = 'flex: 2; min-width: 180px;';
            renderValueInput(def, value, valueWrap);
            row.appendChild(valueWrap);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.style.cssText = 'background: transparent; border: 1px solid var(--clr-danger); color: var(--clr-danger); padding: 0.4rem 0.6rem; border-radius: 6px; cursor: pointer;';
            removeBtn.onclick = () => {
                delete selectedAttrs[def.attr_key];
                cascadeRemoveDependents();
                renderAttributes();
            };
            row.appendChild(removeBtn);

            parent.appendChild(row);
        }

        function renderValueInput(def, currentValue, parent) {
            parent.innerHTML = '';
            if (def.value_type === 'enum') {
                const sel = document.createElement('select');
                sel.style.cssText = 'width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);';
                sel.innerHTML = '<option value="">اختر...</option>';
                (def.allowed_values || []).forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    if (String(currentValue) === String(v)) opt.selected = true;
                    sel.appendChild(opt);
                });
                sel.onchange = () => {
                    selectedAttrs[def.attr_key] = sel.value;
                    cascadeRemoveDependents();
                    renderAttributes();
                };
                parent.appendChild(sel);
            } else if (def.value_type === 'number') {
                const wrap = document.createElement('div');
                wrap.style.cssText = 'display: flex; align-items: center; gap: 0.5rem;';
                const inp = document.createElement('input');
                inp.type = 'number'; inp.step = 'any'; inp.min = '0';
                inp.value = currentValue ?? '';
                inp.style.cssText = 'flex: 1; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);';
                inp.oninput = () => { selectedAttrs[def.attr_key] = inp.value; updateHiddenJson(); };
                wrap.appendChild(inp);
                if (def.unit) {
                    const u = document.createElement('span');
                    u.textContent = def.unit;
                    u.style.cssText = 'color: var(--clr-text-secondary); font-size: 0.85rem;';
                    wrap.appendChild(u);
                }
                parent.appendChild(wrap);
            } else {
                const inp = document.createElement('input');
                inp.type = 'text'; inp.maxLength = 255;
                inp.value = currentValue ?? '';
                inp.style.cssText = 'width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);';
                inp.oninput = () => { selectedAttrs[def.attr_key] = inp.value; updateHiddenJson(); };
                parent.appendChild(inp);
            }
        }

        function cascadeRemoveDependents() {
            let changed = true;
            while (changed) {
                changed = false;
                for (const key of Object.keys(selectedAttrs)) {
                    const def = getDef(key);
                    if (!def || !def.visible_when) continue;
                    if (!isVisible(def)) { delete selectedAttrs[key]; changed = true; }
                }
            }
        }

        function updateHiddenJson() {
            if (hidden) hidden.value = JSON.stringify(selectedAttrs);
        }

        // ---------------- INIT ----------------
        if (assignedToSelect) {
            loadRelatedAssets(assignedToSelect.value);
            assignedToSelect.addEventListener('change', function () {
                loadRelatedAssets(this.value);
            });
        }

        if (typeSelect && picker && container) {
            restoreInitialAttributes();
            lastAssetType = typeSelect.value;

            loadDefinitions(lastAssetType).then(() => {
                Object.keys(selectedAttrs).forEach(k => {
                    if (!getDef(k)) delete selectedAttrs[k];
                });
                renderAttributes();
            });

            picker.addEventListener('change', () => {
                const key = picker.value;
                if (!key) return;
                const def = getDef(key);
                if (!def) return;
                selectedAttrs[key] = '';
                renderAttributes();
                setTimeout(() => {
                    const rows = container.querySelectorAll(':scope > div');
                    const last = rows[rows.length - 1];
                    if (last) {
                        const inp = last.querySelector('input, select');
                        if (inp) inp.focus();
                    }
                }, 50);
            });

            typeSelect.addEventListener('change', () => {
                const newType = typeSelect.value;
                if (newType === lastAssetType) return;

                const lostAttrs = [];
                Object.keys(selectedAttrs).forEach(k => {
                    const def = getDef(k);
                    if (def && def.applies_to_types && !def.applies_to_types.includes(newType)) {
                        lostAttrs.push(def.display_label_ar);
                    }
                });

                if (lostAttrs.length && !confirm(`تغيير النوع سيزيل الخصائص التالية لأنها لا تنطبق: ${lostAttrs.join('، ')}. هل تريد المتابعة؟`)) {
                    typeSelect.value = lastAssetType;
                    return;
                }

                Object.keys(selectedAttrs).forEach(k => {
                    const def = getDef(k);
                    if (!def || !def.applies_to_types || !def.applies_to_types.includes(newType)) {
                        delete selectedAttrs[k];
                    }
                });
                lastAssetType = newType;
                loadDefinitions(newType).then(() => renderAttributes());
            });
        }
    })();
</script>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>