<?php
// public/pages/login.php

use Ministry\Models\User;
use Ministry\Services\ActivityLogger; // <-- ADD THIS

$error = null;
$success = false;

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if ($user && $userModel->verifyPassword($password, $user['password_hash'])) {
            if ($user['is_active'] == 0) {
                $error = 'حسابك في انتظار موافقة المسؤول. سيتم إعلامك عند التفعيل.';
            } else {
                // proceed with login
                $logger = ActivityLogger::getInstance();
                $logger->login($user['user_id'], 'success', ['ip' => $_SERVER['REMOTE_ADDR']]);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_data'] = $user;
                $_SESSION['user_display_name'] = $user['full_name'] ?? $user['username'] ?? 'مستخدم';
                $userModel->updateLastLogin($user['user_id']);
                header('Location: index.php?action=dashboard');
                exit;
            }
        } else {
            // --- LOG FAILED LOGIN ---
            $logger = ActivityLogger::getInstance();
            $userId = $user['user_id'] ?? 0;
            $logger->login($userId, 'failed', [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'username' => $_POST['username'] ?? 'unknown'
            ]);
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        }
    }
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?action=dashboard');
    exit;
}

// Include header (contains <head> + <header>)
require_once __DIR__ . '/../components/layout/header.php';
?>

<body class="light-mode bg-gray-50 flex flex-col md:flex-row items-center justify-center h-screen">
    <div class="login-container">
        <div class="flex flex-row items-center justify-center gap-6 mb-6 text-center">
            <img src="assets/img/logo3-h.jpeg" alt="logo" style="height: 120px;">
            <div class="flex flex-col items-center justify-center">
                <h1 class="text-2xl font-bold">وزارة السياحة و الآثار</h1>
                <h3 class="text-lg font-bold">وحدة الاتصالات و تكنولوجيا
                    المعلومات</h3>
                <h6 class="text-md font-bold">نظام إدارة الأصول</h6>
            </div>
        </div>

        <?php if ($error): ?>
            <div
                style="background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; text-align: center;">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">اسم المستخدم</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="أدخل اسم المستخدم"
                    required autofocus>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="أدخل كلمة المرور"
                    required>
            </div>

            <button type="submit" class="btn-primary w-full" style="width: 100%; padding: 0.75rem; font-size: 1rem;">
                <i class="fas fa-sign-in-alt ml-2"></i> تسجيل الدخول
            </button>
        </form>
        <div class="text-center flex flex-col gap-4">
            <a href="#" class="text-link">نسيت كلمة المرور؟</a>
            <a href="index.php?action=register" class="text-link">تسجيل حساب جديد</a>
        </div>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.875rem; color: #64748b;">
            <p>الحساب التجريبي: <strong>test</strong> / <strong>123456</strong></p>
        </div>
    </div>
    <?php
    // Include footer
    require_once __DIR__ . '/../components/layout/footer.php';
    ?>