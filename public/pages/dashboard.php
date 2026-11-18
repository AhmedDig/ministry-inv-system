<?php
// pages/dashboard.php

use Ministry\Models\Asset;

// Set active tab for highlighting
$active_tab = 'dashboard';

// Fetch data
$assetModel = new Asset();
$stats = $assetModel->getStats();
$recentAssets = $assetModel->getWithDetails(null, 5);

$logged_in = isset($_SESSION['user_id']);
$user_data = $_SESSION['user_data'] ?? null;
// Inside dashboard.php, after $user_data = $_SESSION['user_data'];
$is_approved = $user_data['is_active'] ?? 0;
// Include layout (opens HTML, head, header, sidebar, and main content)
require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<!-- Dashboard specific content -->
<div class="dashboard-content gap-4 m-4">
    <h1 class="page-title">لوحة التحكم</h1>
    <?php if (!$is_approved): ?>
        <div class="pending-approval-card">
            <div class="flex items-start">
                <div class="warning-icon text-amber-500 text-xl mr-3 mt-1">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <h3 class="warning-title font-bold mb-2">حسابك قيد المراجعة</h3>
                    <p class="warning-text">حسابك لا يزال في انتظار موافقة المسؤول. سيتم تفعيل جميع الخدمات بعد الموافقة.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Show normal dashboard content -->


        <!-- Stats Cards -->
        <div class="grid grid-cols-2 gap-2">
            <div class="stat-card">
                <div class="stat-icon text-4xl text-blue-600 bg-blue-100 p-4"><i class="fa-solid fa-bars-progress"></i>
                </div>
                <div class="stat-info flex justify-between items-center gap-1">
                    <span class="stat-label">إجمالي الأصول</span>
                    <span class="stat-value"><?php echo $stats['total'] ?? 0; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-4xl text-green-600 bg-green-100 p-4"><i class="fa-solid fa-check"></i></div>
                <div class="stat-info flex justify-between items-center gap-1">
                    <span class="stat-label">متاحة</span>
                    <span class="stat-value text-green"><?php echo $stats['available'] ?? 0; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-4xl text-blue-600 bg-blue-100 p-4"><i class="fa-solid fa-user"></i></div>
                <div class="stat-info flex justify-between items-center gap-1">
                    <span class="stat-label">مسندة</span>
                    <span class="stat-value text-blue"><?php echo $stats['assigned'] ?? 0; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-4xl text-red-600 bg-red-100 p-4"><i class="fa-solid fa-tools"></i></div>
                <div class="stat-info flex justify-between items-center gap-1">
                    <span class="stat-label">تحت الصيانة</span>
                    <span class="stat-value text-red"><?php echo $stats['under_repair'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../components/layout/footer.php'; ?>