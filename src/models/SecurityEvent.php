<?php
namespace Ministry\Models;

class SecurityEvent extends Model
{
    protected $table = 'security_events';
    protected $primaryKey = 'event_id';
    protected $logEnabled = false;

    public function getAll(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, e.full_name AS actor_name
             FROM security_events s
             LEFT JOIN entities e ON s.actor_entity_id = e.entity_id
             ORDER BY s.occurred_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM security_events WHERE event_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}