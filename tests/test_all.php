<?php
// public/test_all.php – Complete system test

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set a timeout for the script
set_time_limit(30);

// Start HTML output
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار النظام – وزارة السياحة</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f4f6f9; direction: rtl; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1e293b; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
        h2 { color: #334155; margin-top: 25px; }
        .test-section { background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px; border-right: 4px solid #3b82f6; }
        .pass { color: #16a34a; font-weight: bold; }
        .fail { color: #dc2626; font-weight: bold; }
        .warn { color: #f59e0b; font-weight: bold; }
        .info { color: #2563eb; }
        pre { background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.9rem; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .badge-pass { background: #dcfce7; color: #166534; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .badge-warn { background: #fef9c3; color: #854d0e; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px 12px; text-align: right; border: 1px solid #d1d5db; }
        th { background: #e5e7eb; }
        .summary { background: #e0f2fe; padding: 15px; border-radius: 6px; margin-top: 20px; }
        .summary h3 { margin: 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧪 اختبار النظام الشامل</h1>
    <p>تاريخ الاختبار: <?php echo date('Y-m-d H:i:s'); ?></p>

    <?php
    // ============================================================
    // 1. TEST: Load configuration and secrets file
    // ============================================================
    echo '<div class="test-section">';
    echo '<h2>1. تحميل ملف الإعدادات</h2>';

    $config_path = __DIR__ . '/../includes/config.php';
    if (!file_exists($config_path)) {
        echo '<p class="fail">❌ ملف الإعدادات غير موجود: ' . htmlspecialchars($config_path) . '</p>';
        echo '</div></div></body></html>';
        exit;
    }

    // Try to include the config – it should define $pdo and register autoloader
    try {
        require_once $config_path;
        echo '<p class="pass">✅ تم تحميل ملف الإعدادات بنجاح.</p>';
    } catch (Throwable $e) {
        echo '<p class="fail">❌ فشل تحميل ملف الإعدادات: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div></div></body></html>';
        exit;
    }

    // Check if $pdo is defined and is a PDO object
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        echo '<p class="fail">❌ متغير $pdo غير معرف أو ليس كائن PDO صحيح.</p>';
        echo '<p>تحقق من ملف الإعدادات: ' . htmlspecialchars($config_path) . '</p>';
        echo '</div></div></body></html>';
        exit;
    } else {
        echo '<p class="pass">✅ تم إنشاء اتصال PDO بنجاح.</p>';
    }
    echo '</div>';

    // ============================================================
    // 2. TEST: Database connection (run a simple query)
    // ============================================================
    echo '<div class="test-section">';
    echo '<h2>2. اختبار اتصال قاعدة البيانات</h2>';

    try {
        $stmt = $pdo->query("SELECT 1 AS test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['test'] == 1) {
            echo '<p class="pass">✅ الاتصال بقاعدة البيانات يعمل بشكل صحيح.</p>';
        } else {
            echo '<p class="fail">❌ الاستعلام الأساسي لم يعد النتيجة المتوقعة.</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="fail">❌ فشل تنفيذ الاستعلام: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div></div></body></html>';
        exit;
    }
    echo '</div>';

    // ============================================================
    // 3. TEST: List tables (verify schema exists)
    // ============================================================
    echo '<div class="test-section">';
    echo '<h2>3. قائمة الجداول في قاعدة البيانات</h2>';
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo '<p><span class="info">عدد الجداول: ' . count($tables) . '</span></p>';
        echo '<ul style="columns: 2; list-style: none; padding: 0;">';
        foreach ($tables as $table) {
            echo '<li style="padding: 2px 0;">📄 ' . htmlspecialchars($table) . '</li>';
        }
        echo '</ul>';
        // Check for required tables
        $required = ['assets', 'consumables', 'tickets', 'users', 'entities', 'suppliers', 'locations', 'activity_log'];
        $missing = array_diff($required, $tables);
        if (empty($missing)) {
            echo '<p class="pass">✅ جميع الجداول الأساسية موجودة.</p>';
        } else {
            echo '<p class="warn">⚠️ الجداول التالية غير موجودة: ' . implode(', ', $missing) . '</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="fail">❌ فشل جلب قائمة الجداول: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';

    // ============================================================
    // 4. TEST: Autoloader (check if model classes can be loaded)
    // ============================================================
    echo '<div class="test-section">';
    echo '<h2>4. اختبار Autoloader (تحميل النماذج)</h2>';

    $model_classes = [
        'Ministry\Models\Asset',
        'Ministry\Models\Entity',
        'Ministry\Models\Supplier',
        'Ministry\Models\Location',
        'Ministry\Models\Consumable',
        'Ministry\Models\Ticket',
        'Ministry\Models\User'
    ];

    $loaded = 0;
    foreach ($model_classes as $class) {
        try {
            if (class_exists($class)) {
                echo '<p class="pass">✅ تم تحميل الفئة: ' . htmlspecialchars($class) . '</p>';
                $loaded++;
            } else {
                echo '<p class="fail">❌ الفئة غير موجودة: ' . htmlspecialchars($class) . '</p>';
            }
        } catch (Throwable $e) {
            echo '<p class="fail">❌ خطأ أثناء محاولة تحميل ' . htmlspecialchars($class) . ': ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }

    if ($loaded == count($model_classes)) {
        echo '<p class="pass">✅ جميع النماذج تم تحميلها بنجاح.</p>';
    } else {
        echo '<p class="warn">⚠️ تم تحميل ' . $loaded . ' من ' . count($model_classes) . ' نماذج.</p>';
    }
    echo '</div>';

    // ============================================================
    // 5. TEST: Model methods (fetch data)
    // ============================================================
    echo '<div class="test-section">';
    echo '<h2>5. اختبار دوال النماذج الأساسية</h2>';

    try {
        // Use Asset model if available
        if (class_exists('Ministry\Models\Asset')) {
            $asset = new \Ministry\Models\Asset();
            echo '<p><span class="info">▶️ اختبار Asset::findAll()...</span></p>';
            $assets = $asset->findAll([], 'created_at DESC', 3);
            if (is_array($assets)) {
                echo '<p class="pass">✅ تم جلب ' . count($assets) . ' أصول.</p>';
                if (!empty($assets)) {
                    echo '<table>
                            <thead><tr><th>المعرف</th><th>النوع</th><th>الحالة</th><th>المسلسل</th></tr></thead>
                            <tbody>';
                    foreach ($assets as $row) {
                        echo '<tr>
                                <td>' . htmlspecialchars($row['asset_id']) . '</td>
                                <td>' . htmlspecialchars($row['asset_type'] ?? '---') . '</td>
                                <td>' . htmlspecialchars($row['status'] ?? '---') . '</td>
                                <td>' . htmlspecialchars($row['serial_number'] ?? '---') . '</td>
                              </tr>';
                    }
                    echo '</tbody></table>';
                }
            } else {
                echo '<p class="fail">❌ Asset::findAll() لم تعد مصفوفة.</p>';
            }

            // Test getStats
            echo '<p><span class="info">▶️ اختبار Asset::getStats()...</span></p>';
            $stats = $asset->getStats();
            if ($stats && is_array($stats)) {
                echo '<p class="pass">✅ تم جلب الإحصائيات: ';
                echo 'إجمالي ' . ($stats['total'] ?? 0) . ', متاح ' . ($stats['available'] ?? 0) . ', مسند ' . ($stats['assigned'] ?? 0) . ', تحت الإصلاح ' . ($stats['under_repair'] ?? 0);
                echo '</p>';
            } else {
                echo '<p class="fail">❌ فشل جلب الإحصائيات.</p>';
            }
        } else {
            echo '<p class="warn">⚠️ فئة Asset غير متاحة، تخطي اختبارات النماذج.</p>';
        }
    } catch (Throwable $e) {
        echo '<p class="fail">❌ خطأ أثناء اختبار النماذج: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
    echo '</div>';

    // ============================================================
    // 6. TEST: Count sample data
    // ============================================================
    echo '<div class="test-section">';
    echo '<h2>6. بيانات العينة</h2>';
    try {
        $tables_to_count = ['users', 'entities', 'assets', 'suppliers', 'locations', 'tickets'];
        echo '<ul>';
        foreach ($tables_to_count as $tbl) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $tbl");
            $count = $stmt->fetchColumn();
            echo '<li>' . htmlspecialchars($tbl) . ': <strong>' . $count . '</strong> سجل(ات)</li>';
        }
        echo '</ul>';
    } catch (PDOException $e) {
        echo '<p class="warn">⚠️ لا يمكن جلب عدد السجلات: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';

    // ============================================================
    // 7. SUMMARY
    // ============================================================
    echo '<div class="summary">';
    echo '<h3>✅ تم الانتهاء من جميع الاختبارات</h3>';
    echo '<p>يرجى مراجعة النتائج أعلاه. إذا كانت جميعها خضراء، فالنظام جاهز للعمل.</p>';
    echo '<p>أي مشاكل تظهر باللون الأحمر تحتاج إلى إصلاح قبل المتابعة.</p>';
    echo '</div>';

    ?>
</div>
</body>
</html>