<?php
// components/layout/sidebar.php

$current_action = $_GET['action'] ?? 'dashboard';
$isAdmin = isset($_SESSION['user_data']) && $_SESSION['user_data']['clearance_level'] >= 5;
// $menu_items is defined in config.php and available globally
?>
<aside class="sidebar collapsed" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <span class="sidebar-brand">القائمة</span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu_items as $action => $item): ?>
            <a href="index.php?action=<?php echo $action; ?>"
                class="sidebar-link <?php echo ($current_action === $action) ? 'active' : ''; ?>">
                <i class="fas <?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($isAdmin): ?>
            <a href="index.php?action=users"
                class="sidebar-link <?php echo ($current_action === 'users') ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>المستخدمين</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>