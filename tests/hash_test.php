// quick_test.php
<?
require_once '/../includes/config.php';
require_once '/../includes/password_utils.php';

$test_users = ['ahmed', 'agent.fahad']; // Your test users

foreach ($test_users as $username) {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && PasswordUtils::isPasswordHashed($user['password_hash'])) {
        echo "✓ {$username}: Properly hashed\n";
    } else {
        echo "✗ {$username}: NOT hashed\n";
    }
}
?>