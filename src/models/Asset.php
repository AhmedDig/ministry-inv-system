<?php
namespace Ministry\Models;

class Asset extends Model
{
    protected $table = 'assets';
    protected $primaryKey = 'asset_id';
    protected $logEntityType = 'asset';

    public function getWithDetails($id = null)
    {
        $sql = "SELECT 
                    a.*,
                    s.name as supplier_name,
                    w.name as warranty_provider_name,
                    l.name as location_name,
                    e.full_name as assigned_to_name,
                    e.type as assigned_to_type,
                    d.department_name_ar as department_name
                FROM {$this->table} a
                LEFT JOIN suppliers s ON a.supplier_id = s.supplier_id
                LEFT JOIN suppliers w ON a.warranty_provider_id = w.supplier_id
                LEFT JOIN locations l ON a.location_id = l.location_id
                LEFT JOIN entities e ON a.assigned_to = e.entity_id
                LEFT JOIN departments d ON a.department_id = d.department_id
                WHERE a.is_active = 1";

        if ($id) {
            $sql .= " AND a.{$this->primaryKey} = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        }

        $sql .= " ORDER BY a.created_at DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getByStatus($status)
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    public function getByEntity($entityId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE assigned_to = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityId]);
        return $stmt->fetchAll();
    }

    public function getStats()
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                    SUM(CASE WHEN status = 'under_repair' THEN 1 ELSE 0 END) as under_repair,
                    SUM(CASE WHEN status = 'retired' THEN 1 ELSE 0 END) as retired
                FROM {$this->table}
                WHERE is_active = 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
    public function update($id, $data)
    {
        // Check if assignment changed
        $old = $this->findById($id);
        $oldAssignedTo = $old['assigned_to'] ?? null;
        $oldStatus = $old['status'] ?? null;

        $result = parent::update($id, $data);

        if ($result && isset($data['assigned_to'])) {
            $newAssignedTo = $data['assigned_to'];
            $logger = \Ministry\Services\ActivityLogger::getInstance();

            if ($newAssignedTo !== null && $oldAssignedTo !== $newAssignedTo) {
                // Asset assigned
                $entityModel = new \Ministry\Models\Entity();
                $entity = $entityModel->findById($newAssignedTo);
                $logger->log('asset', $id, 'assigned', [
                    'assigned_to' => $newAssignedTo,
                    'assigned_to_name' => $entity['full_name'] ?? 'غير معروف'
                ]);
            } elseif ($newAssignedTo === null && $oldAssignedTo !== null) {
                // Asset unassigned
                $logger->log('asset', $id, 'unassigned', []);
            }
        }

        return $result;
    }
    /**
     * Get other active assets assigned to the same entity.
     */
    public function getRelated(int $entityId, int $excludeAssetId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE assigned_to = ? AND asset_id != ? AND is_active = 1
                ORDER BY asset_tag, serial_number";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityId, $excludeAssetId]);
        return $stmt->fetchAll();
    }
    /**
     * Load attributes for one asset, returned as [key => value].
     */
    public function getAttributes(int $assetId): array
    {
        $stmt = $this->db->prepare(
            "SELECT attr_key, attr_value FROM asset_attributes WHERE asset_id = ?"
        );
        $stmt->execute([$assetId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['attr_key']] = $row['attr_value'];
        }
        return $out;
    }
}