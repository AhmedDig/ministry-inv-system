<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

use Ministry\Models\Asset;
use Ministry\Models\Entity;

echo "<pre>";
echo "Testing Asset class:\n";
try {
    $asset = new Asset();
    echo "✅ Asset class loaded successfully.\n";
    var_dump($asset);
} catch (Error $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTesting Entity class:\n";
try {
    $entity = new Entity();
    echo "✅ Entity class loaded successfully.\n";
    var_dump($entity);
} catch (Error $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";