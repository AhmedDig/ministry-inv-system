<?php
// pages/user_edit.php

// Restrict to admins (clearance_level >= 5)
if (!isset($_SESSION['user_data']) || $_SESSION['user_data']['clearance_level'] < 5) {
    header('Location: index.php?action=dashboard');
    exit;
}

$logged_in = true;
$user_data = $_SESSION['user_data'];
$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $userId > 0;

$userModel = new \Ministry\Models\User();
$user = null;
if ($isEdit) {
    $user = $userModel->getWithEntity($userId);
    if (!$user) {
        header('Location: index.php?action=users');
        exit;
    }
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? null;
    $clearance_level = (int) ($_POST['clearance_level'] ?? 1);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $requires_password_reset = isset($_POST['requires_password_reset']) ? 1 : 0;

    if (empty($full_name) || empty($username) || empty($email)) {
        $error = 'الاسم، اسم المستخدم، والبريد الإلكتروني مطلوبة.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح.';
    } else {
        $data = [
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'department_id' => $department_id,
            'clearance_level' => $clearance_level,
            'is_active' => $is_active,
            'requires_password_reset' => $requires_password_reset
        ];
        if (!empty($password)) {
            $data['password'] = $password;
        }

        if ($isEdit) {
            $result = $userModel->updateWithEntity($userId, $data);
        } else {
            if (empty($password)) {
                $error = 'كلمة المرور مطلوبة للمستخدم الجديد.';
            } else {
                $data['password'] = $password;
                $result = $userModel->createWithEntity($data);
                if ($result) {
                    $userId = $result;
                }
            }
        }

        if (isset($result) && $result) {
            $success = true;
            // Reload user data if edit
            if ($isEdit) {
                $user = $userModel->getWithEntity($userId);
            }
        } else {
            $error = 'حدث خطأ. قد يكون اسم المستخدم أو البريد الإلكتروني مستخدم مسبقاً.';
        }
    }
}

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <h1 class="text-2xl font-bold mb-6"><?php echo $isEdit ? 'تعديل مستخدم' : 'إضافة مستخدم جديد'; ?></h1>

    <?php if ($success): ?>
        <div class="alert alert-success">تم الحفظ بنجاح.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="max-w-2xl">
        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label for="full_name" class="form-label">الاسم الكامل *</label>
                <input type="text" id="full_name" name="full_name" class="form-input"
                    value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">اسم المستخدم *</label>
                <input type="text" id="username" name="username" class="form-input"
                    value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">البريد الإلكتروني *</label>
                <input type="email" id="email" name="email" class="form-input"
                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور
                    <?php echo $isEdit ? '(اتركه فارغاً لعدم التغيير)' : '*'; ?></label>
                <input type="password" id="password" name="password" class="form-input" <?php echo $isEdit ? '' : 'required'; ?>>
            </div>

            <div class="form-group">
                <label for="department_id" class="form-label">القسم</label>
                <select id="department_id" name="department_id" class="form-input">
                    <option value="">اختر القسم</option>
                    <?php
                    $departments = (new \Ministry\Models\Department())->findAll();
                    foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($user['department_id']) && $user['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name_ar']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="clearance_level" class="form-label">مستوى الصلاحية</label>
                <select id="clearance_level" name="clearance_level" class="form-input">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo (isset($user['clearance_level']) && $user['clearance_level'] == $i) ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group flex items-center gap-4">
                <label class="form-label" style="display: inline;">مفعل</label>
                <input type="checkbox" name="is_active" value="1" <?php echo (isset($user['is_active']) && $user['is_active']) ? 'checked' : ''; ?>>
            </div>

            <div class="form-group flex items-center gap-4">
                <label class="form-label" style="display: inline;">إجبار على تغيير كلمة المرور</label>
                <input type="checkbox" name="requires_password_reset" value="1" <?php echo (isset($user['requires_password_reset']) && $user['requires_password_reset']) ? 'checked' : ''; ?>>
            </div>
        </div>

        <div class="flex justify-end gap-4 mt-6">
            <a href="index.php?action=users" class="btn-secondary">إلغاء</a>
            <button type="submit" class="btn-primary">حفظ</button>
        </div>
    </form>
    <!-- Inside pages/user_edit.php, after the form -->
    <div class="mt-8">
        <h3 class="text-lg font-bold"
            style="color: var(--clr-text); border-bottom: 2px solid var(--clr-primary); padding-bottom: 0.5rem;">سجل
            نشاطات المستخدم</h3>
            <?php
            $entityType = 'user';
            $entityId = $isEdit ? $userId : 0;
            $title = 'نشاطات المستخدم';
            if ($entityId > 0) {
                require_once __DIR__ . '/../components/activity/activity_timeline.php';
            } else {
                echo '<div style="color: var(--clr-text-secondary);">سيتم عرض النشاطات بعد حفظ المستخدم.</div>';
            }
            ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>