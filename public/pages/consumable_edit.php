<?php
// pages/consumable_edit.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

$consumableId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $consumableId > 0;

$consumableModel = new \Ministry\Models\Consumable();
$consumable = null;
if ($isEdit) {
    $consumable = $consumableModel->findById($consumableId);
    if (!$consumable) {
        header('Location: index.php?action=consumables');
        exit;
    }
}

$error = null;

// Get suppliers and locations for dropdowns
$supplierModel = new \Ministry\Models\Supplier();
$suppliers = $supplierModel->findAll();

$locationModel = new \Ministry\Models\Location();
$locations = $locationModel->findAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $consumable_type = $_POST['consumable_type'] ?? 'other';
    $sku = trim($_POST['sku'] ?? '');
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $min_quantity = (int) ($_POST['min_quantity'] ?? 5);
    $unit = trim($_POST['unit'] ?? 'pcs');
    $supplier_id = $_POST['supplier_id'] ?? null;
    if ($supplier_id === '' || $supplier_id === null) {
        $supplier_id = null;
    }
    $location_id = $_POST['location_id'] ?? null;
    if ($location_id === '' || $location_id === null) {
        $location_id = null;
    }
    $specs_json = $_POST['specs_json'] ?? null;
    if ($specs_json === '' || $specs_json === null) {
        $specs_json = null;
    }
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name)) {
        $error = 'الاسم مطلوب.';
    } elseif ($quantity < 0) {
        $error = 'الكمية لا يمكن أن تكون سالبة.';
    } else {
        $data = [
            'name' => $name,
            'consumable_type' => $consumable_type,
            'sku' => $sku,
            'quantity' => $quantity,
            'min_quantity' => $min_quantity,
            'unit' => $unit,
            'supplier_id' => $supplier_id,
            'location_id' => $location_id,
            'specs_json' => $specs_json,
            'notes' => $notes
        ];

        if ($isEdit) {
            $result = $consumableModel->update($consumableId, $data);
        } else {
            $result = $consumableModel->create($data);
        }

        if ($result) {
            header('Location: index.php?action=consumables');
            exit;
        } else {
            $error = 'حدث خطأ أثناء الحفظ. تأكد من أن SKU غير مكرر.';
        }
    }
}

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <h1 class="text-2xl font-bold mb-6" style="color: var(--clr-text);">
        <?php echo $isEdit ? 'تعديل مستهلك' : 'إضافة مستهلك جديد'; ?>
    </h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"
            style="background: var(--clr-danger); color: white; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="max-w-2xl">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group">
                <label for="name" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الاسم
                    *</label>
                <input type="text" id="name" name="name" class="form-input"
                    value="<?php echo htmlspecialchars($consumable['name'] ?? ''); ?>" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>

            <div class="form-group">
                <label for="consumable_type" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">النوع</label>
                <select id="consumable_type" name="consumable_type" class="form-input"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
                    <option value="cable" <?php echo (isset($consumable['consumable_type']) && $consumable['consumable_type'] === 'cable') ? 'selected' : ''; ?>>كابل</option>
                    <option value="ink_cartridge" <?php echo (isset($consumable['consumable_type']) && $consumable['consumable_type'] === 'ink_cartridge') ? 'selected' : ''; ?>>خرطوشة حبر</option>
                    <option value="toner" <?php echo (isset($consumable['consumable_type']) && $consumable['consumable_type'] === 'toner') ? 'selected' : ''; ?>>تونر</option>
                    <option value="paper" <?php echo (isset($consumable['consumable_type']) && $consumable['consumable_type'] === 'paper') ? 'selected' : ''; ?>>ورق</option>
                    <option value="battery" <?php echo (isset($consumable['consumable_type']) && $consumable['consumable_type'] === 'battery') ? 'selected' : ''; ?>>بطارية</option>
                    <option value="adapter" <?php echo (isset($consumable['consumable_type']) && $consumable['consumable_type'] === 'adapter') ? 'selected' : ''; ?>>محول</option>
                    <option value="other" <?php echo (isset($consumable['consumable_type']) && $consumable['consumable_type'] === 'other') ? 'selected' : ''; ?>>أخرى</option>
                </select>
            </div>

            <div class="form-group">
                <label for="sku" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">SKU</label>
                <input type="text" id="sku" name="sku" class="form-input"
                    value="<?php echo htmlspecialchars($consumable['sku'] ?? ''); ?>"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>

            <div class="form-group">
                <label for="unit" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الوحدة</label>
                <input type="text" id="unit" name="unit" class="form-input"
                    value="<?php echo htmlspecialchars($consumable['unit'] ?? 'pcs'); ?>"
                    placeholder="مثل: قطعة, متر, لتر"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>

            <div class="form-group">
                <label for="quantity" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الكمية</label>
                <input type="number" id="quantity" name="quantity" class="form-input"
                    value="<?php echo $consumable['quantity'] ?? 0; ?>" min="0"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>

            <div class="form-group">
                <label for="min_quantity" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الحد الأدنى
                    للكمية</label>
                <input type="number" id="min_quantity" name="min_quantity" class="form-input"
                    value="<?php echo $consumable['min_quantity'] ?? 5; ?>" min="0"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>

            <div class="form-group">
                <label for="supplier_id" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">المورد</label>
                <select id="supplier_id" name="supplier_id" class="form-input"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
                    <option value="">اختر المورد</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo (isset($consumable['supplier_id']) && $consumable['supplier_id'] == $supplier['supplier_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="location_id" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الموقع</label>
                <select id="location_id" name="location_id" class="form-input"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
                    <option value="">اختر الموقع</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo $location['location_id']; ?>" <?php echo (isset($consumable['location_id']) && $consumable['location_id'] == $location['location_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group md:col-span-2">
                <label for="specs_json" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">المواصفات
                    (JSON)</label>
                <textarea id="specs_json" name="specs_json" class="form-input" rows="3"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;"><?php echo htmlspecialchars($consumable['specs_json'] ?? ''); ?></textarea>
                <small style="color: var(--clr-text-secondary);">مثال: {"color": "أزرق", "length": "5m"}</small>
            </div>

            <div class="form-group md:col-span-2">
                <label for="notes" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">ملاحظات</label>
                <textarea id="notes" name="notes" class="form-input" rows="2"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;"><?php echo htmlspecialchars($consumable['notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-4 mt-6">
            <a href="index.php?action=consumables" class="btn-secondary"
                style="background: var(--clr-card-bg); color: var(--clr-primary); border: 1px solid var(--clr-primary); padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 1rem; text-decoration: none; transition: all 0.2s ease;">إلغاء</a>
            <button type="submit" class="btn-primary"
                style="background: var(--clr-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 1rem; transition: background 0.2s ease;">حفظ</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>