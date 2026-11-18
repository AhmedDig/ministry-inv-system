<?php
namespace Ministry\Models;

class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';
    protected $primaryKey = 'delivery_id';
    protected $logEnabled = false;

    public function findByRecipient(int $recipientEntityId, bool $unreadOnly = false): array
    {
        $sql = "SELECT * FROM notification_deliveries WHERE recipient_entity_id = ?";
        if ($unreadOnly) $sql .= " AND is_read = 0";
        $sql .= " ORDER BY delivered_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$recipientEntityId]);
        return $stmt->fetchAll();
    }

    public function unresolvedForRecipient(int $recipientEntityId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM notification_deliveries
             WHERE recipient_entity_id = ? AND resolved_at IS NULL
             ORDER BY next_reminder_at ASC"
        );
        $stmt->execute([$recipientEntityId]);
        return $stmt->fetchAll();
    }
}