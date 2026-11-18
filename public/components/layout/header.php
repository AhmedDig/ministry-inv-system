<!-- Header Component -->
<?php
$current_action = $_GET['action'] ?? 'dashboard';
$isAuthPage = in_array($current_action, ['login', 'register']);
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وزارة السياحة والآثار | نظام إدارة الأصول</title>

    <!-- FOUC Prevention – must run before any CSS loads -->
    <script>
        (function () {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = saved === 'dark' || (!saved && prefersDark);
            document.documentElement.classList.add(isDark ? 'dark-mode' : 'light-mode');
            if (document.body) document.body?.classList.add(isDark ? 'dark-mode' : 'light-mode');
        })();
    </script>

    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- JavaScript (loaded at the end, but theme.js must be early) -->
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/ticket-helpers.js"></script>
    <script src="assets/js/notifications.js"></script>

    <!-- images -->
    <link rel="shortcut icon" href="/ministry/public/assets/img/logo3-h.jpeg" type="image/favicon">
</head>

<?php if (!$isAuthPage): ?>
    <header class="header">
        <div class="mx-auto px-4 py-4 w-full">
            <div class="flex md:flex-row flex-col justify-between items-center gap-6">
                <div class="flex items-center md:justify-center w-full gap-6">
                    <div class="p-3 rounded-lg mr-3">
                        <img class="image-logo" src="assets/img/logo3-h.jpeg" alt="logo">
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">وزارة السياحة و الآثار</h1>
                        <p class="text-gray-600 dark:text-gray-400">وحدة الاتصالات و تكنولوجيا المعلومات</p>
                    </div>
                </div>

                <div class="flex flex-row items-center gap-6 justify-end md:justify-center w-full">
                    <?php if (isset($logged_in) && $logged_in && isset($user_data) && !empty($user_data)): ?>
                        <div class="user-dropdown">
                            <button class="user-dropdown-btn flex justify-between w-fit ml-3 mr-3" id="userMenuBtn"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle text-2xl"></i>
                                <span class="user-name">
                                    <?php
                                    $userName = $_SESSION['user_display_name'] ??
                                        $user_data['full_name'] ??
                                        $user_data['full_name_ar'] ??
                                        $user_data['username'] ??
                                        'مستخدم';
                                    echo htmlspecialchars($userName);
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down text-sm"></i>
                            </button>

                            <div class="user-dropdown-menu" id="userMenu">
                                <div class="user-dropdown-header">
                                    <div class="user-avatar">
                                        <i class="fas fa-user-circle text-3xl"></i>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name-display">
                                            <?php echo htmlspecialchars($userName); ?>
                                        </div>
                                        <?php if (isset($user_data['clearance_level'])): ?>
                                            <span class="user-level-badge">
                                                مستوى <?php echo $user_data['clearance_level']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="user-dropdown-divider"></div>
                                <a href="index.php?action=profile" class="user-dropdown-item">
                                    <i class="fas fa-user-edit"></i>
                                    <span>الملف الشخصي</span>
                                </a>
                                <a href="index.php?action=settings" class="user-dropdown-item">
                                    <i class="fas fa-cog"></i>
                                    <span>الإعدادات</span>
                                </a>
                                <div class="user-dropdown-divider"></div>
                                <a href="index.php?action=logout" class="user-dropdown-item text-red-600">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>تسجيل الخروج</span>
                                </a>
                            </div>
                        </div>
                        <div class="notifications-wrapper">
                            <button id="notifications-btn" class="theme-toggle-btn notification-bell" title="الإشعارات">
                                <i class="fas fa-bell"></i>
                                <span id="notifications-badge" class="notification-badge" style="display: none;">0</span>
                            </button>
                            <div id="notifications-dropdown" class="notifications-dropdown">
                                <div class="notifications-header">
                                    <strong>الإشعارات</strong>
                                    <a href="#" class="notifications-mark-all">تحديد الكل كمقروء</a>
                                </div>
                                <div class="notifications-list"></div>
                                <div class="notifications-footer">
                                    <a href="index.php?action=notifications">عرض الكل</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <button id="theme-toggle" class="theme-toggle-btn flex justify-center items-center ml-3 mr-3">
                        <i class="fas fa-moon"></i>
                    </button>

                </div>

                <?php if (isset($logged_in) && $logged_in && isset($_SESSION['requires_password_reset']) && $_SESSION['requires_password_reset']): ?>
                    <div class="bg-red-600 text-white p-2 text-center w-full">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        يجب تغيير كلمة المرور للمتابعة
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <style>
        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .user-dropdown-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--clr-card-border);
            border-radius: 8px;
            background: var(--clr-card-bg);
            color: var(--clr-text);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .user-dropdown-btn:hover {
            border-color: var(--clr-primary);
            background: var(--clr-bg);
        }

        .user-dropdown-btn .user-name {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-dropdown-menu {
            display: none;
            position: absolute;
            left: 0;
            top: calc(100% + 8px);
            min-width: 220px;
            background: var(--clr-card-bg);
            border: 1px solid var(--clr-card-border);
            border-radius: 12px;
            box-shadow: var(--clr-card-shadow);
            z-index: 1000;
            overflow: hidden;
            padding: 0.5rem 0;
        }

        .user-dropdown-menu.show {
            display: block;
        }

        .user-dropdown-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--clr-primary);
            border-radius: 50%;
            color: white;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name-display {
            font-weight: 600;
            color: var(--clr-text);
        }

        .user-level-badge {
            font-size: 0.7rem;
            background: var(--clr-primary);
            color: white;
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
            display: inline-block;
            width: fit-content;
        }

        .user-dropdown-divider {
            border-top: 1px solid var(--clr-card-border);
            margin: 0.25rem 0;
        }

        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1rem;
            color: var(--clr-text);
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .user-dropdown-item:hover {
            background: var(--clr-bg);
        }

        .user-dropdown-item i {
            width: 1.25rem;
            color: var(--clr-text-secondary);
        }

        .user-dropdown-item.text-red-600 {
            color: var(--clr-danger);
        }

        .user-dropdown-item.text-red-600 i {
            color: var(--clr-danger);
        }

        /* Dark mode */
        .dark-mode .user-dropdown-btn {
            border-color: var(--clr-card-border);
        }

        .dark-mode .user-dropdown-menu {
            border-color: var(--clr-card-border);
        }

        /* ===== Notification Bell ===== */
        .notifications-wrapper {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            position: relative;
            padding: 0.5rem 0.75rem;
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -4px;
            background: var(--clr-danger);
            color: white;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            border: 2px solid var(--clr-card-bg);
        }

        .notification-bell.has-urgent {
            animation: bell-wiggle 2s ease-in-out infinite;
        }

        @keyframes bell-wiggle {

            0%,
            100% {
                transform: rotate(0deg);
            }

            10% {
                transform: rotate(-6deg);
            }

            20% {
                transform: rotate(6deg);
            }

            30% {
                transform: rotate(-4deg);
            }

            40% {
                transform: rotate(4deg);
            }

            50% {
                transform: rotate(0deg);
            }
        }

        .notifications-dropdown {
            display: none;
            position: absolute;
            left: 0;
            top: calc(100% + 8px);
            width: 340px;
            max-height: 480px;
            background: var(--clr-card-bg);
            border: 1px solid var(--clr-card-border);
            border-radius: 12px;
            box-shadow: var(--clr-card-shadow);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
        }

        .notifications-dropdown.show {
            display: flex;
        }

        .notifications-header {
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--clr-card-border);
            background: var(--clr-bg);
        }

        .notifications-header strong {
            color: var(--clr-text);
            font-size: 0.95rem;
        }

        .notifications-header a {
            color: var(--clr-primary);
            font-size: 0.75rem;
            text-decoration: none;
        }

        .notifications-list {
            overflow-y: auto;
            max-height: 360px;
        }

        .notifications-loading,
        .notifications-empty {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--clr-text-secondary);
            font-size: 0.85rem;
        }

        .notification-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--clr-card-border);
            cursor: pointer;
            transition: background 0.15s;
            align-items: flex-start;
        }

        .notification-item:hover {
            background: var(--clr-bg);
        }

        .notification-item.unread {
            background: rgba(59, 130, 246, 0.04);
        }

        .notification-item.urgent {
            border-right: 3px solid var(--clr-danger);
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.85rem;
        }

        .notification-item.severity-info .notification-icon {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .notification-item.severity-success .notification-icon {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
        }

        .notification-item.severity-warning .notification-icon {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .notification-item.severity-danger .notification-icon {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            color: var(--clr-text);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.15rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .notification-body {
            color: var(--clr-text-secondary);
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .notification-date {
            color: var(--clr-text-secondary);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .reminder-pill {
            background: var(--clr-danger);
            color: white;
            padding: 0.05rem 0.4rem;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 700;
        }

        .notifications-footer {
            padding: 0.65rem 1rem;
            text-align: center;
            border-top: 1px solid var(--clr-card-border);
            background: var(--clr-bg);
        }

        .notifications-footer a {
            color: var(--clr-primary);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userMenuBtn = document.getElementById('userMenuBtn');
            const userMenu = document.getElementById('userMenu');

            if (userMenuBtn && userMenu) {
                userMenuBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    userMenu.classList.toggle('show');
                    this.setAttribute('aria-expanded', userMenu.classList.contains('show'));
                });

                document.addEventListener('click', function (e) {
                    if (!userMenu.contains(e.target) && !userMenuBtn.contains(e.target)) {
                        userMenu.classList.remove('show');
                        userMenuBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
    </script>
<?php endif; ?>