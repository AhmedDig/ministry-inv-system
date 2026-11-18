<?php
/**
 * CLI test for NotificationService.
 * Runs inside a transaction and rolls back — safe to run on the live DB.
 * Usage: php tests/test_notifications.php
 */

require_once __DIR__ . '/../includes/config.php';

use Ministry\Services\NotificationService;

echo "=== Notification System Test ===\n\n";

// Sanity checks — SHOW TABLES doesn't accept placeholders, use inline
$tables = ['notifications', 'notification_deliveries', 'broadcast_messages', 'security_events'];
$existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    if (!in_array($t, $existing, true)) {
        echo "❌ Missing table: $t\n";
        exit(1);
    }
}
echo "✅ All required tables exist\n\n";

// Need at least one entity + one L5 user
$stmt = $pdo->query("SELECT entity_id, full_name FROM entities WHERE is_active = 1 LIMIT 1");
$testEntity = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$testEntity) {
    echo "❌ No active entities in database\n";
    exit(1);
}
echo "Using test entity: #{$testEntity['entity_id']} ({$testEntity['full_name']})\n";

$stmt = $pdo->query(
    "SELECT e.entity_id, e.full_name
     FROM entities e JOIN users u ON e.entity_id = u.entity_id
     WHERE u.clearance_level = 5 AND u.is_active = 1 LIMIT 1"
);
$l5 = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$l5) {
    echo "⚠️  No L5 user found — security/broadcast tests will skip\n";
} else {
    echo "Using L5 entity: #{$l5['entity_id']} ({$l5['full_name']})\n";
}
echo "\n";

$service = new NotificationService($pdo);
$pdo->beginTransaction();

$passed = 0;
$failed = 0;
function ok($msg)  { global $passed; echo "  ✅ $msg\n"; $passed++; }
function bad($msg) { global $failed; echo "  ❌ $msg\n"; $failed++; }
function eq($a, $b, $label) {
    if ($a == $b) ok("$label ($a == $b)");
    else bad("$label (expected $b, got $a)");
}

try {
    // ---------------------------------------------------------
    echo "TEST 1: Create notification to specific entity\n";
    $nid = $service->notify([
        'category'        => 'ticket',
        'severity'        => 'warning',
        'title'           => 'Test ticket notification',
        'body'            => 'Test body',
        'related_type'    => 'ticket',
        'related_id'      => 99999,
        'action_url'      => 'index.php?action=tickets&id=99999',
        'requires_action' => true,
    ], ['entity_ids' => [$testEntity['entity_id']]]);
    eq(!is_null($nid), true, 'Notification created');
    eq(is_numeric($nid), true, 'notification_id returned');

    // ---------------------------------------------------------
    echo "\nTEST 2: Verify delivery row\n";
    $stmt = $pdo->prepare(
        "SELECT * FROM notification_deliveries WHERE notification_id = ?"
    );
    $stmt->execute([$nid]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    eq(!empty($delivery), true, 'Delivery row exists');
    eq($delivery['is_read'], 0, 'Starts unread');
    eq($delivery['reminder_count'], 0, 'Zero reminders');
    eq(!empty($delivery['next_reminder_at']), true, 'next_reminder_at set');

    // ---------------------------------------------------------
    echo "\nTEST 3: Unread count\n";
    $counts = $service->getUnreadCount($testEntity['entity_id']);
    eq($counts['total'] >= 1, true, 'At least 1 unread');
    eq($counts['urgent'], 0, 'No urgent yet');

    // ---------------------------------------------------------
    echo "\nTEST 4: Mark read\n";
    $ok = $service->markRead($delivery['delivery_id'], $testEntity['entity_id']);
    eq($ok, true, 'markRead returned true');
    $counts = $service->getUnreadCount($testEntity['entity_id']);
    eq($counts['total'], 0, 'Unread count is now 0');

    // ---------------------------------------------------------
    echo "\nTEST 5: Create second unread notification for reminder test\n";
    $nid2 = $service->notify([
        'category'        => 'ticket',
        'severity'        => 'warning',
        'title'           => 'Reminder test',
        'requires_action' => true,
    ], ['entity_ids' => [$testEntity['entity_id']]]);
    eq(!is_null($nid2), true, 'Second notification created');

    // Force next_reminder_at into the past to simulate 24h passed
    $pdo->prepare(
        "UPDATE notification_deliveries
         SET next_reminder_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
         WHERE notification_id = ?"
    )->execute([$nid2]);

    echo "\nTEST 6: Process due reminders\n";
    $bumped = $service->processDueReminders($testEntity['entity_id']);
    eq($bumped >= 1, true, 'At least one reminder processed');

    $stmt = $pdo->prepare(
        "SELECT reminder_count, next_reminder_at FROM notification_deliveries
         WHERE notification_id = ?"
    );
    $stmt->execute([$nid2]);
    $d2 = $stmt->fetch(PDO::FETCH_ASSOC);
    eq($d2['reminder_count'], 1, 'reminder_count = 1');
    eq(strtotime($d2['next_reminder_at']) > time(), true, 'next_reminder_at moved to future');

    // Process again — should be 0 because next_reminder_at is now future
    $bumped2 = $service->processDueReminders($testEntity['entity_id']);
    eq($bumped2, 0, 'Second process call found nothing due');

    // ---------------------------------------------------------
    echo "\nTEST 7: Urgent count includes reminded item\n";
    $counts = $service->getUnreadCount($testEntity['entity_id']);
    eq($counts['urgent'], 1, '1 urgent reminder');

    // ---------------------------------------------------------
    echo "\nTEST 8: Fetch notifications\n";
    $list = $service->getNotifications($testEntity['entity_id']);
    eq(is_array($list), true, 'Returns array');
    eq(count($list) >= 2, true, 'At least 2 items');

    // ---------------------------------------------------------
    echo "\nTEST 9: Resolve by related\n";
    $resolved = $service->resolveByRelated('ticket', 99999);
    eq($resolved >= 1, true, 'At least one delivery resolved');

    $stmt = $pdo->prepare(
        "SELECT resolved_at, next_reminder_at FROM notification_deliveries
         WHERE notification_id = ?"
    );
    $stmt->execute([$nid]);
    $d1 = $stmt->fetch(PDO::FETCH_ASSOC);
    eq(!empty($d1['resolved_at']), true, 'resolved_at set');
    eq($d1['next_reminder_at'], null, 'next_reminder_at cleared');

    // ---------------------------------------------------------
    echo "\nTEST 10: Mark all read\n";
    $marked = $service->markAllRead($testEntity['entity_id']);
    eq($marked >= 1, true, 'Bulk mark read affected rows');
    $counts = $service->getUnreadCount($testEntity['entity_id']);
    eq($counts['total'], 0, 'Unread count = 0');

    // ---------------------------------------------------------
    if ($l5) {
        echo "\nTEST 11: Security event logs + notifies L5\n";
        $eventId = $service->logSecurityEvent(
            'unauthorized_consumption',
            $testEntity['entity_id'],
            'ticket',
            99999,
            ['consumable_id' => 5, 'quantity' => 3],
            '127.0.0.1'
        );
        eq(is_numeric($eventId), true, 'security_event created');

        $stmt = $pdo->prepare(
            "SELECT * FROM security_events WHERE event_id = ?"
        );
        $stmt->execute([$eventId]);
        $evt = $stmt->fetch(PDO::FETCH_ASSOC);
        eq($evt['event_type'], 'unauthorized_consumption', 'event_type correct');
        eq((int)$evt['actor_entity_id'], (int)$testEntity['entity_id'], 'actor recorded');

        $counts = $service->getUnreadCount($l5['entity_id']);
        eq($counts['total'] >= 1, true, 'L5 received security notification');

        // ---------------------------------------------------------
        echo "\nTEST 12: Broadcast from L5\n";
        $bid = $service->sendBroadcast(
            'Broadcast test',
            'This is a test broadcast',
            'info',
            'individual',
            $testEntity['entity_id'],
            $l5['entity_id']
        );
        eq(is_numeric($bid), true, 'broadcast_messages row created');

        $stmt = $pdo->prepare(
            "SELECT * FROM broadcast_messages WHERE broadcast_id = ?"
        );
        $stmt->execute([$bid]);
        $bc = $stmt->fetch(PDO::FETCH_ASSOC);
        eq($bc['title'], 'Broadcast test', 'broadcast title stored');

        $counts = $service->getUnreadCount($testEntity['entity_id']);
        eq($counts['total'] >= 1, true, 'Recipient got broadcast notification');
    } else {
        echo "\n⚠️  Skipping tests 11-12 (no L5 user in DB)\n";
    }

    echo "\n========================================\n";
    echo "RESULTS: $passed passed, $failed failed\n";
    echo "========================================\n";

} catch (\Throwable $e) {
    echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    $pdo->rollBack();
    echo "\n(Rolled back — no data left behind)\n";
}

exit($failed === 0 ? 0 : 1);