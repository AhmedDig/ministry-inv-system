<?php
// pages/assets_list.php

$logged_in = true;
$user_data = $_SESSION['user_data'];

require_once __DIR__ . '/../components/layout/dashboard_layout.php';
?>

<div class="page-content">
    <div class="flex flex-row md:flex-col justify-between items-center sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--clr-text);">404 - الصفحة غير موجودة</h1>
        <a href="index.php?action=dashboard" class="btn-primary w-1/4 sm:w-auto text-center">
            <i class="fas fa-arrow-left ml-2"></i> عودة
        </a>
    </div>
</div>