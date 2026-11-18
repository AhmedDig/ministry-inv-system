<?php
/**
 * Dashboard Layout
 * This file is included at the top of each dashboard page.
 * It includes header, sidebar, and opens the main content container.
 * The page content is output after this file, and footer.php closes everything.
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<?php require_once __DIR__ . '/header.php'; // contains <head> and <header> ?>

<body>
    <div class="app-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="main-content" id="mainContent">
            <!-- Page content will be inserted here -->
            <div class="page-content">