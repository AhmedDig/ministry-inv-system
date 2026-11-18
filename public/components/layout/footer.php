<!-- Footer / Closing Layout -->
</div> <!-- /.page-content -->
</main> <!-- /.main-content -->
</div> <!-- /.app-container -->

<!-- Mobile Bottom Navigation -->
<?php if (isset($logged_in) && $logged_in && isset($user_data) && !empty($user_data)): ?>
    <?php require_once __DIR__ . '/mobile_bottom_nav.php'; ?>
<?php endif; ?>
<!-- Sidebar Toggle & Theme Script -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
            });
        }
    });
</script>
</body>

</html>