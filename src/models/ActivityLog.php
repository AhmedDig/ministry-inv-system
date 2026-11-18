<?php
namespace Ministry\Models;

class ActivityLog extends Model
{
    protected $table = 'activity_log';
    protected $primaryKey = 'log_id';

    /**
     * Log an activity event.
     *
     * @param string $entityType  'asset', 'consumable', 'ticket', 'user', 'entity', 'login', 'system'
     * @param int $entityId       ID of the affected entity
     * @param string $eventType   'created', 'updated', 'assigned', 'unassigned', 'status_changed', 'quantity_changed', 'deleted', 'approved', 'login_success', 'login_failed', etc.
     * @param array $details      Additional details (old value, new value, reason, etc.)
     * @param int|null $actorId   ID of the user who performed the action (from entities table)
     * @return int|false
     */
    public function log($entityType, $entityId, $eventType, $details = [], $actorId = null)
    {
        if ($actorId === null && isset($_SESSION['user_data']['entity_id'])) {
            $actorId = (int) $_SESSION['user_data']['entity_id'];
        }

        $data = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event_type' => $eventType,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'actor_id' => $actorId,
            'occurred_at' => date('Y-m-d H:i:s')
        ];

        error_log("Logging: " . json_encode($data));

        return $this->create($data);
    }

    /**
     * Get activity log for a specific entity.
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $limit
     * @return array
     */
    public function getForEntity($entityType, $entityId, $limit = 50)
    {
        $sql = "SELECT al.*, e.full_name as actor_name
            FROM activity_log al
            LEFT JOIN entities e ON al.actor_id = e.entity_id
            WHERE al.entity_type = ? AND al.entity_id = ?
            ORDER BY al.occurred_at DESC
            LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType, $entityId, $limit]);
        return $stmt->fetchAll();
    }
    /**
     * Get recent activity (global feed).
     *
     * @param int $limit
     * @param array $excludeTypes
     * @return array
     */
    public function getRecent($limit = 50, $excludeTypes = ['login'])
    {
        $excludePlaceholders = implode(',', array_fill(0, count($excludeTypes), '?'));
        $sql = "SELECT al.*, e.full_name as actor_name,
                       CASE 
                           WHEN al.entity_type = 'asset' THEN (SELECT asset_tag FROM assets WHERE asset_id = al.entity_id)
                           WHEN al.entity_type = 'consumable' THEN (SELECT name FROM consumables WHERE consumable_id = al.entity_id)
                           WHEN al.entity_type = 'ticket' THEN (SELECT title FROM tickets WHERE ticket_id = al.entity_id)
                           WHEN al.entity_type = 'entity' THEN (SELECT full_name FROM entities WHERE entity_id = al.entity_id)
                           WHEN al.entity_type = 'user' THEN (SELECT username FROM users WHERE user_id = al.entity_id)
                           ELSE NULL
                       END as entity_name
                FROM activity_log al
                LEFT JOIN entities e ON al.actor_id = e.entity_id
                WHERE al.entity_type NOT IN ($excludePlaceholders)
                ORDER BY al.occurred_at DESC
                LIMIT ?";
        $params = array_merge($excludeTypes, [$limit]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get activity summary (counts by type).
     *
     * @return array
     */
    public function getSummary()
    {
        $sql = "SELECT entity_type, COUNT(*) as count
                FROM activity_log
                WHERE occurred_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY entity_type";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}