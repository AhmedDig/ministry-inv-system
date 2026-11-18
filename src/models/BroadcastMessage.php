<?php
namespace Ministry\Models;

class BroadcastMessage extends Model
{
    protected $table = 'broadcast_messages';
    protected $primaryKey = 'broadcast_id';
    protected $logEnabled = false;

    public function getAll(): array
    {
        $stmt = $this->db->query(
            "SELECT b.*, e.full_name AS created_by_name
             FROM broadcast_messages b
             LEFT JOIN entities e ON b.created_by = e.entity_id
             ORDER BY b.created_at DESC"
        );
        return $stmt->fetchAll();
    }
}