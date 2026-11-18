<?php
// pages/entity_edit.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

$entityId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $entityId > 0;

$entityModel = new \Ministry\Models\Entity();
$entity = null;
if ($isEdit) {
    $entity = $entityModel->findById($entityId);
    if (!$entity) {
        header('Location: index.php?action=entities');
        exit;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $type = $_POST['type'] ?? 'employee';
    $department_id = $_POST['department_id'] ?? null;
    // Convert empty string to NULL
    if ($department_id === '' || $department_id === null) {
        $department_id = null;
    }
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($full_name)) {
        $error = 'الاسم مطلوب.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح.';
    } else {
        $data = [
            'full_name' => $full_name,
            'type' => $type,
            'department_id' => $department_id,
            'phone' => $phone,
            'email' => $email
        ];

        if ($isEdit) {
            $result = $entityModel->update($entityId, $data);
        } else {
            $result = $entityModel->create($data);
        }

        if ($result) {
            // Redirect to entities list
            header('Location: index.php?action=entities');
            exit;
        } else {
            $error = 'حدث خطأ أثناء الحفظ.';
        }
    }
}

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <h1 class="text-2xl font-bold mb-6" style="color: var(--clr-text);">
        <?php echo $isEdit ? 'تعديل كيان' : 'إضافة كيان جديد'; ?>
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
                <label for="full_name" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الاسم
                    *</label>
                <input type="text" id="full_name" name="full_name" class="form-input"
                    value="<?php echo htmlspecialchars($entity['full_name'] ?? ''); ?>" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>

            <div class="form-group">
                <label for="type" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">النوع
                    *</label>
                <select id="type" name="type" class="form-input" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
                    <option value="employee" <?php echo (isset($entity['type']) && $entity['type'] === 'employee') ? 'selected' : ''; ?>>موظف</option>
                    <option value="external_tech" <?php echo (isset($entity['type']) && $entity['type'] === 'external_tech') ? 'selected' : ''; ?>>فني خارجي</option>
                    <option value="repair_shop" <?php echo (isset($entity['type']) && $entity['type'] === 'repair_shop') ? 'selected' : ''; ?>>مركز صيانة</option>
                </select>
            </div>

            <div class="form-group">
                <label for="department_id" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">القسم</label>
                <select id="department_id" name="department_id" class="form-input"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
                    <option value="">اختر القسم</option>
                    <?php
                    $departments = (new \Ministry\Models\Department())->findAll();
                    foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($entity['department_id']) && $entity['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name_ar']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">رقم
                    الهاتف</label>
                <input type="tel" id="phone" name="phone" class="form-input"
                    value="<?php echo htmlspecialchars($entity['phone'] ?? ''); ?>"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>

            <div class="form-group md:col-span-2">
                <label for="email" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">البريد
                    الإلكتروني</label>
                <input type="email" id="email" name="email" class="form-input"
                    value="<?php echo htmlspecialchars($entity['email'] ?? ''); ?>"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); font-size: 1rem;">
            </div>
        </div>

        <div class="flex justify-end gap-4 mt-6">
            <a href="index.php?action=entities" class="btn-secondary"
                style="background: var(--clr-card-bg); color: var(--clr-primary); border: 1px solid var(--clr-primary); padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 1rem; text-decoration: none; transition: all 0.2s ease;">إلغاء</a>
            <button type="submit" class="btn-primary"
                style="background: var(--clr-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 1rem; transition: background 0.2s ease;">حفظ</button>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <?php
        $assetsAssigned = $entityModel->findById($entityId)
            ? (new \Ministry\Models\Asset())->getRelated((int) $entityId, 0)
            : [];
        $typeColors = [
            'laptop' => '#3b82f6',
            'desktop' => '#10b981',
            'server' => '#8b5cf6',
            'switch' => '#f59e0b',
            'router' => '#ef4444',
            'access_point' => '#06b6d4',
            'printer' => '#ec4899',
            'monitor' => '#f97316',
            'phone' => '#8b5cf6',
            'ipad' => '#a855f7',
            'scanner' => '#10b981',
            'fax' => '#f59e0b',
            'ups' => '#ef4444',
            'projector' => '#06b6d4',
            'camera' => '#ec4899',
            'other' => '#6b7280',
        ];
        $statusLabels = ['available' => 'متاحة', 'assigned' => 'مسندة', 'under_repair' => 'تحت الصيانة', 'retired' => 'متقاعد'];
        $statusColors = ['available' => 'var(--clr-success)', 'assigned' => 'var(--clr-primary)', 'under_repair' => 'var(--clr-warning)', 'retired' => 'var(--clr-danger)'];
        ?>
        <div class="mt-8" style="border-top: 2px solid var(--clr-card-border); padding-top: 1.5rem;">
            <h3 style="color: var(--clr-text); font-size: 1.1rem; font-weight: 600; margin-bottom: 0.25rem;">
                <i class="fas fa-boxes ml-2" style="color: var(--clr-primary);"></i>
                الأصول المسندة إلى
                <?php echo htmlspecialchars($entity['full_name'] ?? ''); ?>
                <span
                    style="background: var(--clr-primary); color: white; padding: 0.1rem 0.6rem; border-radius: 12px; font-size: 0.8rem; margin-right: 0.5rem;">
                    <?php echo count($assetsAssigned); ?>
                </span>
            </h3>
            <p style="color: var(--clr-text-secondary); font-size: 0.85rem; margin-bottom: 1rem;">
                جميع الأصول المسندة حالياً لهذا الكيان
            </p>

            <?php if (empty($assetsAssigned)): ?>
                <div
                    style="background: var(--clr-bg); border: 1px dashed var(--clr-card-border); border-radius: 10px; padding: 2rem 1.5rem; text-align: center;">
                    <i class="fas fa-inbox"
                        style="font-size: 2rem; color: var(--clr-text-secondary); opacity: 0.6; margin-bottom: 0.75rem; display: block;"></i>
                    <p style="color: var(--clr-text-secondary); margin: 0;">لا توجد أصول مسندة إلى هذا الكيان حالياً</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                    <?php foreach ($assetsAssigned as $ra):
                        $color = $typeColors[$ra['asset_type']] ?? '#6b7280';
                        $sColor = $statusColors[$ra['status']] ?? 'var(--clr-text-secondary)';
                        $sLabel = $statusLabels[$ra['status']] ?? $ra['status'];
                        $label = $ra['asset_tag'] ?: ($ra['serial_number'] ?: '---');
                        $initial = strtoupper(mb_substr($label, 0, 1));
                        ?>
                        <a href="index.php?action=asset_edit&id=<?php echo (int) $ra['asset_id']; ?>"
                            style="display: block; background: var(--clr-card-bg); border: 1px solid var(--clr-card-border); border-radius: 10px; padding: 1rem; text-decoration: none; color: inherit; transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(0,0,0,0.1)'; this.style.borderColor='var(--clr-primary)';"
                            onmouseout="this.style.transform=''; this.style.boxShadow=''; this.style.borderColor='var(--clr-card-border)';">
                            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                                <div
                                    style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $color; ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">
                                    <?php echo htmlspecialchars($initial); ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div
                                        style="font-weight: 600; color: var(--clr-text); font-size: 0.95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($label); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--clr-text-secondary); margin-top: 0.2rem;">
                                        <?php echo htmlspecialchars($ra['asset_type']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; margin-top: 0.35rem;">
                                        <span style="color: <?php echo $sColor; ?>; font-weight: 600;">
                                            <?php echo htmlspecialchars($sLabel); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>