<?php
echo "<h3>اختبار الاتصال النهائي</h3>";
echo "<div style='direction: rtl; text-align: right;'>";

// XAMPP default credentials
$config = [
    'host' => 'localhost',
    'user' => 'root', 
    'pass' => '',
    'db'   => 'ministry_db'
];

try {
    // Test connection
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']}", $config['user'], $config['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ الاتصال بنجاح مع:<br>";
    echo "- الخادم: {$config['host']}<br>";
    echo "- المستخدم: {$config['user']}<br>";
    echo "- قاعدة البيانات: {$config['db']}<br>";
    
    // Test if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ جدول المستخدمين موجود<br>";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "✅ أعمدة الجدول: " . implode(', ', $columns) . "<br>";
    } else {
        echo "❌ جدول المستخدمين غير موجود<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ فشل الاتصال: " . $e->getMessage() . "<br>";
    echo "<h4>الحلول المقترحة:</h4>";
    echo "1. تأكد من تشغيل MySQL في XAMPP<br>";
    echo "2. افتح phpMyAdmin على http://localhost/phpmyadmin<br>";
    echo "3. أنشئ قاعدة بيانات باسم 'ministry_db'<br>";
    echo "4. أنشئ جدول 'users' باستخدام SQL أعلاه<br>";
}

echo "</div>";
?>