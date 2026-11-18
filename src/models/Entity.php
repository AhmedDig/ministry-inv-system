<?php
namespace Ministry\Models;

class Entity extends Model
{
    protected $table = 'entities';
    protected $primaryKey = 'entity_id';
    protected $logEntityType = 'entity';
    public function getByType($type)
    {
        $sql = "SELECT * FROM {$this->table} WHERE type = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    public function getAssignable()
    {
        $sql = "SELECT * FROM {$this->table} WHERE type IN ('employee', 'external_tech', 'repair_shop') AND is_active = 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getTechnicians()
    {
        $sql = "SELECT * FROM {$this->table} WHERE type IN ('employee', 'external_tech') AND is_active = 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get all entities with their linked user (if any).
     * @param array $filters ['type' => string, 'search' => string]
     * @return array
     */
    public function getWithUser($filters = [])
    {
        $sql = "SELECT e.*, u.user_id, u.username, u.clearance_level, u.is_active as user_active,
                   d.department_name_ar
            FROM entities e
            LEFT JOIN users u ON e.entity_id = u.entity_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            WHERE e.is_active = 1";

        $params = [];

        if (!empty($filters['type'])) {
            $sql .= " AND e.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.full_name LIKE ? OR e.email LIKE ? OR e.phone LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY e.full_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }


}