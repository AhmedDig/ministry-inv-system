<?php
namespace Ministry\Models;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';
    protected $logEnabled = false;

    public function findById($id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE notification_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function archiveOlderThan(int $days = 90): int
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications
             SET archived_at = NOW()
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
               AND archived_at IS NULL"
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}