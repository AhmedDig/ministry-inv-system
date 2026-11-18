<?php
namespace Ministry\Models;

class Consumable extends Model
{
    protected $table = 'consumables';
    protected $primaryKey = 'consumable_id';
    protected $logEntityType = 'consumable';
    /**
     * Get all consumables with supplier and location names
     */
    public function getWithDetails()
    {
        $sql = "SELECT c.*,
                    s.name as supplier_name,
                    l.name as location_name
                FROM {$this->table} c
                LEFT JOIN suppliers s ON c.supplier_id = s.supplier_id
                LEFT JOIN locations l ON c.location_id = l.location_id
                WHERE c.is_active = 1
                ORDER BY c.name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get low stock consumables (quantity <= min_quantity)
     */
    public function getLowStock()
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE quantity <= min_quantity
                AND is_active = 1
                ORDER BY quantity ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Update quantity (add or subtract)
     */
    public function updateQuantity($id, $change, $reason = null)
    {
        $sql = "UPDATE {$this->table}
                SET quantity = quantity + ?
                WHERE consumable_id = ?";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([$change, $id]);

        if ($success) {
            // Log the change in activity_log
            $this->logActivity($id, $change, $reason);
        }

        return $success;
    }

    /**
     * Log consumable stock changes
     */
    private function logActivity($id, $change, $reason)
    {
        $sql = "INSERT INTO activity_log
                (entity_type, entity_id, event_type, details, actor_id)
                VALUES ('consumable', ?, 'consumed', ?, ?)";
        $details = json_encode(['quantity_change' => $change, 'reason' => $reason]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $details, $_SESSION['user_id'] ?? null]);
    }
}