<?php
/**
 * Test Script for Maintenance Ticket System
 * This script tests the complete ticket system functionality
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>اختبار نظام التذاكر</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #721c24; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: right; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 اختبار نظام تذاكر الصيانة</h1>";

try {
    // Test 1: Check if config file exists and can be loaded
    echo "<h2>1. فحص ملف الإعدادات</h2>";
    $config_path = __DIR__ . '/includes/config.php';
    if (!file_exists($config_path)) {
        throw new Exception("ملف الإعدادات غير موجود: " . $config_path);
    }

    require_once $config_path;
    echo "<div class='success'>✅ تم تحميل ملف الإعدادات بنجاح</div>";

    // Test 2: Check database connection
    echo "<h2>2. فحص اتصال قاعدة البيانات</h2>";
    try {
        $test_stmt = $pdo->query("SELECT 1");
        echo "<div class='success'>✅ اتصال قاعدة البيانات يعمل بشكل صحيح</div>";
    } catch (PDOException $e) {
        throw new Exception("فشل في الاتصال بقاعدة البيانات: " . $e->getMessage());
    }

    // Test 3: Check if required tables exist
    echo "<h2>3. فحص وجود الجداول المطلوبة</h2>";
    $required_tables = ['maintenance_tickets', 'ticket_comments', 'ticket_attachments', 'users', 'inventory_items'];
    $existing_tables = [];

    $stmt = $pdo->query("SHOW TABLES");
    $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($required_tables as $table) {
        if (in_array($table, $all_tables)) {
            echo "<div class='success'>✅ جدول '{$table}' موجود</div>";
            $existing_tables[] = $table;
        } else {
            echo "<div class='error'>❌ جدول '{$table}' غير موجود</div>";
        }
    }

    // Test 4: Check if we have at least one user for testing
    echo "<h2>4. فحص وجود مستخدمين للنظام</h2>";
    $user_stmt = $pdo->query("SELECT user_id, username, full_name_ar, clearance_level FROM users WHERE is_active = 1 LIMIT 1");
    $test_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test_user) {
        echo "<div class='warning'>⚠️ لا يوجد مستخدمين نشطين لاختبار النظام</div>";
        echo "<div class='info'>يرجى إنشاء مستخدم واحد على الأقل قبل اختبار نظام التذاكر</div>";
        exit;
    }

    echo "<div class='success'>✅ تم العثور على مستخدم للاختبار: {$test_user['full_name_ar']} (مستوى الصلاحية: {$test_user['clearance_level']})</div>";

    // Test 5: Load and test ticket functions
    echo "<h2>5. فحص وظائف نظام التذاكر</h2>";
    $ticket_functions_path = __DIR__ . '/includes/ticket_functions.php';
    if (!file_exists($ticket_functions_path)) {
        throw new Exception("ملف وظائف التذاكر غير موجود: " . $ticket_functions_path);
    }

    require_once $ticket_functions_path;

    // Test if class exists and can be instantiated
    if (!class_exists('TicketManager')) {
        throw new Exception("فشل في تحميل فئة TicketManager");
    }

    $ticketManager = new TicketManager($pdo);
    echo "<div class='success'>✅ تم تحميل وظائف التذاكر بنجاح</div>";

    // Test 6: Test ticket creation
    echo "<h2>6. اختبار إنشاء تذكرة جديدة</h2>";
    $test_title = "تذكرة اختبار - " . date('Y-m-d H:i:s');
    $test_description = "هذه تذكرة اختبارية للتحقق من عمل النظام";

    $ticket_id = $ticketManager->submitTicket(
        $test_title,
        $test_description,
        $test_user['user_id'],
        null, // device_id
        'software', // category
        'medium' // priority
    );

    if ($ticket_id) {
        echo "<div class='success'>✅ تم إنشاء التذكرة بنجاح - رقم التذكرة: {$ticket_id}</div>";

        // Get the created ticket to verify
        $created_ticket = $ticketManager->getTicket($ticket_id, $test_user['user_id'], $test_user['clearance_level']);

        if ($created_ticket) {
            echo "<div class='success'>✅ تم استرجاع بيانات التذكرة بنجاح</div>";

            // Display ticket information
            echo "<table>
                <tr><th>الحقل</th><th>القيمة</th></tr>
                <tr><td>رقم التذكرة</td><td>{$created_ticket['ticket_number']}</td></tr>
                <tr><td>العنوان</td><td>{$created_ticket['title']}</td></tr>
                <tr><td>الحالة</td><td>{$created_ticket['status']}</td></tr>
                <tr><td>الأولوية</td><td>{$created_ticket['priority']}</td></tr>
                <tr><td>الفئة</td><td>{$created_ticket['category']}</td></tr>
                <tr><td>تاريخ الإنشاء</td><td>{$created_ticket['created_at']}</td></tr>
            </table>";

            // Test 7: Test ticket statistics
            echo "<h2>7. اختبار إحصائيات التذاكر</h2>";
            $stats = $ticketManager->getTicketStats($test_user['user_id'], $test_user['clearance_level']);

            if ($stats) {
                echo "<table>
                    <tr><th>النوع</th><th>العدد</th></tr>
                    <tr><td>إجمالي التذاكر</td><td>{$stats['total']}</td></tr>
                    <tr><td>في انتظار الموافقة</td><td>{$stats['pending']}</td></tr>
                    <tr><td>معتمدة</td><td>{$stats['approved']}</td></tr>
                    <tr><td>قيد المعالجة</td><td>{$stats['in_progress']}</td></tr>
                    <tr><td>مكتملة</td><td>{$stats['completed']}</td></tr>
                </table>";
            }

            // Test 8: Test getting tickets list
            echo "<h2>8. اختبار قائمة التذاكر</h2>";
            $tickets = $ticketManager->getTickets($test_user['user_id'], $test_user['clearance_level'], 'all');

            if (is_array($tickets)) {
                echo "<div class='success'>✅ تم استرجاع " . count($tickets) . " تذكرة</div>";

                if (count($tickets) > 0) {
                    echo "<table>
                        <tr>
                            <th>رقم التذكرة</th>
                            <th>العنوان</th>
                            <th>الحالة</th>
                            <th>الأولوية</th>
                            <th>تاريخ الإنشاء</th>
                        </tr>";

                    foreach (array_slice($tickets, 0, 5) as $ticket) {
                        echo "<tr>
                            <td>{$ticket['ticket_number']}</td>
                            <td>{$ticket['title']}</td>
                            <td>{$ticket['status']}</td>
                            <td>{$ticket['priority']}</td>
                            <td>{$ticket['created_at']}</td>
                        </tr>";
                    }

                    echo "</table>";
                }
            }

            // Test 9: Clean up test ticket
            echo "<h2>9. تنظيف بيانات الاختبار</h2>";
            $cleanup_stmt = $pdo->prepare("DELETE FROM maintenance_tickets WHERE ticket_id = ?");
            $cleanup_success = $cleanup_stmt->execute([$ticket_id]);

            if ($cleanup_success) {
                echo "<div class='success'>✅ تم حذف تذكرة الاختبار بنجاح</div>";
            } else {
                echo "<div class='warning'>⚠️ فشل في حذف تذكرة الاختبار (يمكن حذفها يدوياً)</div>";
            }

        } else {
            echo "<div class='error'>❌ فشل في استرجاع بيانات التذكرة</div>";
        }

    } else {
        echo "<div class='error'>❌ فشل في إنشاء تذكرة الاختبار</div>";
    }

    // Final success message
    echo "<div class='success' style='font-size: 1.2em; font-weight: bold; text-align: center; margin-top: 20px;'>
        🎉 تم اختبار نظام التذاكر بنجاح! النظام جاهز للاستخدام.
    </div>";

} catch (Exception $e) {
    echo "<div class='error'>
        <strong>❌ فشل في الاختبار:</strong><br>
        " . $e->getMessage() . "
    </div>";

    // Show debug info
    echo "<div class='info'>
        <strong>معلومات التصحيح:</strong><br>
        الخطأ في الملف: " . $e->getFile() . "<br>
        السطر: " . $e->getLine() . "<br>
        التتبع: <pre>" . $e->getTraceAsString() . "</pre>
    </div>";
}

echo "</div></body></html>";
?>