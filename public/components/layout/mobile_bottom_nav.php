<?php
// components/layout/mobile_bottom_nav.php

$current_action = $_GET['action'] ?? 'dashboard';
$isAdmin = isset($_SESSION['user_data']) && $_SESSION['user_data']['clearance_level'] >= 5;
// $menu_items is defined in config.php and available globally
?>
<nav class="bottom-nav" id="bottomNav">
    <?php foreach ($menu_items as $action => $item): ?>
        <a href="index.php?action=<?php echo $action; ?>"
            class="bottom-nav-item <?php echo ($current_action === $action) ? 'active' : ''; ?>">
            <i class="fas <?php echo $item['icon']; ?>"></i>
            <span><?php echo $item['label']; ?></span>
        </a>
    <?php endforeach; ?>
    <?php if ($isAdmin): ?>
        <a href="index.php?action=users"
            class="bottom-nav-item <?php echo ($current_action === 'users') ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <span>المستخدمين</span>
        </a>
    <?php endif; ?>
</nav>