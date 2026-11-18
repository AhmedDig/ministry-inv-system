<?php
// pages/profile.php

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

$logged_in = true;
$user_data = $_SESSION['user_data'];
$userId = $_SESSION['user_id'];

$error = null;
$success = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userModel = new \Ministry\Models\User();

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($full_name) || empty($email)) {
            $error = 'الاسم والبريد الإلكتروني مطلوبان.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'البريد الإلكتروني غير صحيح.';
        } else {
            $result = $userModel->updateProfile($userId, [
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone
            ]);

            if ($result === true) {
                // Refresh session data
                $updatedUser = $userModel->getWithEntity($userId);
                $_SESSION['user_data'] = $updatedUser;
                $_SESSION['user_display_name'] = $updatedUser['full_name'] ?? $updatedUser['username'] ?? 'مستخدم';
                $user_data = $_SESSION['user_data'];
                $success = 'تم تحديث الملف الشخصي بنجاح.';
            } elseif ($result === false) {
                $error = 'البريد الإلكتروني مستخدم من قبل حساب آخر.';
            } else {
                $error = 'حدث خطأ أثناء التحديث.';
            }
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'جميع حقول كلمة المرور مطلوبة.';
        } elseif ($new !== $confirm) {
            $error = 'كلمتا المرور غير متطابقتين.';
        } else {
            $result = $userModel->changePassword($userId, $current, $new);
            if ($result === true) {
                $success = 'تم تغيير كلمة المرور بنجاح.';
            } else {
                $error = $result; // error message from method
            }
        }
    }
}

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <h1 class="text-2xl font-bold mb-6">الملف الشخصي والإعدادات</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
            </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
    <?php endif; ?>

    <!-- Two-column layout on desktop, stacked on mobile -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left: Profile Info -->
        <div class="dashboard-card">
            <h2 class="text-lg font-bold mb-4">المعلومات الشخصية</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label for="full_name" class="form-label">الاسم الكامل</label>
                    <input type="text" id="full_name" name="full_name" class="form-input"
                        value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email" class="form-input"
                        value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone" class="form-label">رقم الهاتف</label>
                    <input type="tel" id="phone" name="phone" class="form-input"
                        value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">

                                            <label class="form-label">القسم</label>
                    <p class="text-gray-600 dark:text-gray-400">
                        <?php echo htmlspecialchars($user_data['department_name_ar'] ?? 'غير محدد'); ?>
                    </p>
                </div>
                <div class="form-group">
                    <label class="form-label">مستوى الصلاحية</label>
                    <p class="text-gray-600 dark:text-gray-400">مستوى
                        <?php echo $user_data['clearance_level']; ?>
                    </p>
                </div>
                <button type="submit" class="btn-primary w-full">تحديث الملف الشخصي</button>
            </form>
        </div>

        <!-- Right: Change Password -->
        <div class="dashboard-card">
            <h2 class="text-lg font-bold mb-4">تغيير كلمة المرور</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                    <input type="password" id="current_password" name="current_password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" required
                        minlength="6">
                    <small class="text-gray-500">يجب أن تكون 6 أحرف على الأقل.</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                </div>
                <button type="submit" class="btn-primary w-full">تغيير كلمة المرور</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../components/layout/footer.php';
?>