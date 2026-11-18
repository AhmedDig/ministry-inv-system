<?php
namespace Ministry\Services;

use PDO;

class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a notification and deliver it to resolved recipients.
     *
     * @param array $data {
     *   category (required), severity, title (required), body,
     *   related_type, related_id, action_url,
     *   source_type ('activity'|'broadcast'|'system'), source_id,
     *   requires_action (bool), created_by (entity_id|null)
     * }
     * @param array $recipients {
     *   entity_ids: int[]  | roles: int[]  | departments: int[]  | all: bool
     * }
     * @return int|null notification_id, or null if no recipients
     */
    public function notify(array $data, array $recipients): ?int
    {
        if (empty($data['title']) || empty($data['category'])) {
            error_log('NotificationService::notify - missing title or category');
            return null;
        }

        $entityIds = $this->resolveRecipients($recipients);
        if (empty($entityIds)) {
            error_log('NotificationService::notify - no recipients resolved');
            return null;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications
                (category, severity, title, body, related_type, related_id,
                 action_url, source_type, source_id, requires_action, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['category'],
            $data['severity']          ?? 'info',
            $data['title'],
            $data['body']              ?? null,
            $data['related_type']      ?? null,
            $data['related_id']        ?? null,
            $data['action_url']        ?? null,
            $data['source_type']       ?? 'system',
            $data['source_id']         ?? null,
            !empty($data['requires_action']) ? 1 : 0,
            $data['created_by']        ?? null,
        ]);
        $notificationId = (int)$this->pdo->lastInsertId();

        $requiresAction = !empty($data['requires_action']);
        $nextReminderAt = $requiresAction
            ? date('Y-m-d H:i:s', time() + 86400) // +24h
            : null;

        $ins = $this->pdo->prepare(
            "INSERT IGNORE INTO notification_deliveries
                (notification_id, recipient_entity_id, next_reminder_at)
             VALUES (?, ?, ?)"
        );
        foreach ($entityIds as $eid) {
            $ins->execute([$notificationId, $eid, $nextReminderAt]);
        }

        return $notificationId;
    }

    /**
     * Resolve all deliveries for a given related object.
     * Called when the underlying action is completed (ticket approved, request answered, etc).
     */
    public function resolveByRelated(string $relatedType, int $relatedId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_deliveries d
             JOIN notifications n ON d.notification_id = n.notification_id
             SET d.resolved_at = NOW(),
                 d.next_reminder_at = NULL
             WHERE n.related_type = ?
               AND n.related_id = ?
               AND d.resolved_at IS NULL"
        );
        $stmt->execute([$relatedType, $relatedId]);
        return $stmt->rowCount();
    }

    /**
     * Resolve a single delivery by ID.
     */
    public function resolveDelivery(int $deliveryId, int $recipientEntityId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_deliveries
             SET resolved_at = NOW(), next_reminder_at = NULL
             WHERE delivery_id = ? AND recipient_entity_id = ?"
        );
        return $stmt->execute([$deliveryId, $recipientEntityId]);
    }

    /**
     * Process due reminders for a recipient. Called at start of getNotifications().
     * Returns number of deliveries that were bumped.
     */
    public function processDueReminders(int $recipientEntityId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_deliveries
             SET reminder_count   = reminder_count + 1,
                 last_reminder_at = NOW(),
                 next_reminder_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE recipient_entity_id = ?
               AND is_read = 0
               AND resolved_at IS NULL
               AND next_reminder_at IS NOT NULL
               AND next_reminder_at <= NOW()"
        );
        $stmt->execute([$recipientEntityId]);
        return $stmt->rowCount();
    }

    /**
     * Fetch notifications for a recipient. Automatically processes reminders first.
     */
    public function getNotifications(int $recipientEntityId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->processDueReminders($recipientEntityId);

        $where  = ['d.recipient_entity_id = ?', 'n.archived_at IS NULL'];
        $params = [$recipientEntityId];

        if (!empty($filters['unread_only'])) {
            $where[] = 'd.is_read = 0';
        }
        if (!empty($filters['category'])) {
            $where[] = 'n.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['severity'])) {
            $where[] = 'n.severity = ?';
            $params[] = $filters['severity'];
        }
        if (!empty($filters['requires_action'])) {
            $where[] = 'n.requires_action = 1 AND d.resolved_at IS NULL';
        }

        $limit  = max(1, min((int)$limit, 200));
        $offset = max(0, (int)$offset);

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT n.notification_id, n.category, n.severity, n.title, n.body,
                       n.related_type, n.related_id, n.action_url,
                       n.requires_action, n.created_by, n.created_at,
                       d.delivery_id, d.is_read, d.read_at, d.resolved_at,
                       d.reminder_count, d.last_reminder_at
                FROM notifications n
                JOIN notification_deliveries d ON n.notification_id = d.notification_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY d.reminder_count DESC, d.is_read ASC, n.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Unread count breakdown for the bell.
     */
    public function getUnreadCount(int $recipientEntityId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN d.reminder_count > 0 THEN 1 ELSE 0 END) AS urgent
             FROM notification_deliveries d
             JOIN notifications n ON d.notification_id = n.notification_id
             WHERE d.recipient_entity_id = ?
               AND d.is_read = 0
               AND n.archived_at IS NULL"
        );
        $stmt->execute([$recipientEntityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total'  => (int)($row['total'] ?? 0),
            'urgent' => (int)($row['urgent'] ?? 0),
        ];
    }

    public function markRead(int $deliveryId, int $recipientEntityId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_deliveries
             SET is_read = 1, read_at = NOW()
             WHERE delivery_id = ? AND recipient_entity_id = ?"
        );
        return $stmt->execute([$deliveryId, $recipientEntityId]);
    }

    public function markAllRead(int $recipientEntityId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_deliveries
             SET is_read = 1, read_at = NOW()
             WHERE recipient_entity_id = ? AND is_read = 0"
        );
        $stmt->execute([$recipientEntityId]);
        return $stmt->rowCount();
    }

    /**
     * Log a security event AND notify L5 with a danger alert.
     */
    public function logSecurityEvent(
        string $eventType,
        ?int $actorEntityId,
        ?string $targetType = null,
        ?int $targetId = null,
        array $details = [],
        ?string $ipAddress = null
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO security_events
                (event_type, actor_entity_id, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $eventType,
            $actorEntityId,
            $targetType,
            $targetId,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            $ipAddress,
        ]);
        $eventId = (int)$this->pdo->lastInsertId();

        $actorName = 'غير معروف';
        if ($actorEntityId) {
            $s = $this->pdo->prepare("SELECT full_name FROM entities WHERE entity_id = ?");
            $s->execute([$actorEntityId]);
            $actorName = $s->fetchColumn() ?: $actorName;
        }

        $labels = [
            'unauthorized_consumption' => 'محاولة استهلاك بدون تصريح',
            'unauthorized_api_access'  => 'محاولة وصول غير مصرح',
            'brute_force'              => 'محاولات دخول مشبوهة',
            'suspicious_activity'      => 'نشاط مشبوه',
        ];

        $this->notify([
            'category'        => 'security',
            'severity'        => 'danger',
            'title'           => $labels[$eventType] ?? 'حدث أمني',
            'body'            => "المستخدم: {$actorName}\n" .
                                 (!empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : ''),
            'related_type'    => $targetType,
            'related_id'      => $targetId,
            'action_url'      => 'index.php?action=security_events&id=' . $eventId,
            'requires_action' => true,
            'source_type'     => 'system',
            'source_id'       => $eventId,
        ], ['roles' => [5]]);

        return $eventId;
    }

    /**
     * Send a manual broadcast from L5.
     */
    public function sendBroadcast(
        string $title,
        string $body,
        string $severity,
        string $targetType,
        $targetValue,
        int $createdBy
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO broadcast_messages
                (title, body, severity, target_type, target_value, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$title, $body, $severity, $targetType, $targetValue, $createdBy]);
        $broadcastId = (int)$this->pdo->lastInsertId();

        $recipients = [];
        switch ($targetType) {
            case 'all':        $recipients = ['all' => true]; break;
            case 'role':       $recipients = ['roles' => [(int)$targetValue]]; break;
            case 'department': $recipients = ['departments' => [(int)$targetValue]]; break;
            case 'individual': $recipients = ['entity_ids' => [(int)$targetValue]]; break;
        }

        $this->notify([
            'category'        => 'broadcast',
            'severity'        => $severity,
            'title'           => $title,
            'body'            => $body,
            'source_type'     => 'broadcast',
            'source_id'       => $broadcastId,
            'requires_action' => false,
            'created_by'      => $createdBy,
        ], $recipients);

        return $broadcastId;
    }

    /**
     * Resolve recipient spec to an array of unique entity IDs.
     */
    private function resolveRecipients(array $spec): array
    {
        $ids = [];

        if (!empty($spec['entity_ids'])) {
            $ids = array_merge($ids, array_map('intval', $spec['entity_ids']));
        }

        if (!empty($spec['roles'])) {
            $ph = implode(',', array_fill(0, count($spec['roles']), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT e.entity_id FROM entities e
                 JOIN users u ON e.entity_id = u.entity_id
                 WHERE u.clearance_level IN ($ph)
                   AND u.is_active = 1
                   AND e.is_active = 1"
            );
            $stmt->execute(array_values($spec['roles']));
            $ids = array_merge($ids, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }

        if (!empty($spec['departments'])) {
            $ph = implode(',', array_fill(0, count($spec['departments']), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT entity_id FROM entities
                 WHERE department_id IN ($ph) AND is_active = 1"
            );
            $stmt->execute(array_values($spec['departments']));
            $ids = array_merge($ids, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }

        if (!empty($spec['all'])) {
            $stmt = $this->pdo->query("SELECT entity_id FROM entities WHERE is_active = 1");
            $ids = array_merge($ids, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }

        return array_values(array_unique($ids));
    }
}