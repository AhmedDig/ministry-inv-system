<?php
// pages/register.php

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?action=dashboard');
    exit;
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? null;

    // Basic validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'جميع الحقول مطلوبة.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح.';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
    } else {
        $userModel = new \Ministry\Models\User();
        $result = $userModel->createWithEntity([
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'department_id' => $department_id,
            'clearance_level' => 1,      // default user level
            'is_active' => 0,            // pending approval
            'requires_password_reset' => 0
        ]);

        if ($result) {
            $success = true;
        } else {
            $error = 'اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل.';
        }
    }
}

// Set variables for header
$logged_in = false;
$user_data = null;

// Include header (contains <head>)
require_once __DIR__ . '/../components/layout/header.php';
?>

<body class="light-mode"
    style="background: var(--clr-bg); display: flex; justify-content: center; align-items: center; min-height: 100vh;">
    <div class="login-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <img src="assets/img/logo3-h.jpeg" alt="logo" style="height: 60px; margin-bottom: 1rem;">
            <h2 style="font-size: 1.5rem; font-weight: bold; color: var(--clr-text-primary);">إنشاء حساب جديد</h2>
            <p style="color: var(--clr-text-secondary); font-size: 0.9rem;">سيتم مراجعة طلبك من قبل المسؤول</p>
        </div>

        <?php if ($success): ?>
            <div
                style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; text-align: center;">
                <i class="fas fa-check-circle ml-2"></i> تم إنشاء الحساب بنجاح. سيتم إعلامك بعد الموافقة.
                <br><a href="index.php?action=login" style="color: #3b82f6; text-decoration: underline;">تسجيل الدخول</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; text-align: center;">
                    <i class="fas fa-exclamation-circle ml-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="full_name" class="form-label">الاسم الكامل</label>
                    <input type="text" id="full_name" name="full_name" class="form-input" placeholder="أدخل الاسم الكامل"
                        required>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">اسم المستخدم</label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="اختر اسم مستخدم"
                        required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="أدخل بريدك الإلكتروني"
                        required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">كلمة المرور</label>
                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="اختر كلمة مرور (6 أحرف على الأقل)" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="department_id" class="form-label">القسم</label>
                    <select id="department_id" name="department_id" class="form-input" required>
                        <option value="">اختر القسم</option>
                        <?php
                        $departments = (new \Ministry\Models\Department())->findAll();
                        foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['department_name_ar']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary w-full" style="width: 100%; padding: 0.75rem; font-size: 1rem;">
                    <i class="fas fa-user-plus ml-2"></i> إنشاء حساب
                </button>
            </form>

            <div style="margin-top: 1.5rem; text-align: center; font-size: 0.875rem; color: #64748b;">
                لديك حساب؟ <a href="index.php?action=login" class="text-link">تسجيل الدخول</a>
            </div>
        <?php endif; ?>
    </div>

    <?php
    require_once __DIR__ . '/../components/layout/footer.php';
    ?>