<?php
require_once __DIR__ . '/menu.php';  // defines $menu_items
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------------
// Load Database Credentials from Secure Directory
// ------------------------------------------------------------------
$secrets_path = '/home/ahmed/secrets/ministry/db_credentials.php';

if (!file_exists($secrets_path)) {
    die('Database credentials file not found. Please contact the system administrator.');
}

$db_config = require $secrets_path;

define('DB_HOST', $db_config['host']);
define('DB_NAME', $db_config['name']);
define('DB_USER', $db_config['user']);
define('DB_PASS', $db_config['pass']);
define('DB_CHARSET', $db_config['charset']);

// ------------------------------------------------------------------
// Database Connection (PDO)
// ------------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die('Database connection error. Please try again later.');
}

// ------------------------------------------------------------------
// Autoloader for MVC classes (src/ directory)
// ------------------------------------------------------------------
spl_autoload_register(function ($class) {
    $prefix = 'Ministry\\';
    $base_dir = __DIR__ . '/../src/';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Case‑insensitive folder mapping
    $file = str_replace('/Models/', '/models/', $file);
    $file = str_replace('/Services/', '/services/', $file);
    $file = str_replace('/Utils/', '/utils/', $file);

    if (file_exists($file)) {
        require $file;
    }
});

// ------------------------------------------------------------------
// Global Helper Functions (optional)
// ------------------------------------------------------------------
// require_once __DIR__ . '/helpers.php';

// Return the PDO connection for legacy code (if any)
return $pdo;