<?php
namespace Ministry\Models;

class Location extends Model
{
    protected $table = 'locations';
    protected $primaryKey = 'location_id';

    public function getHierarchy()
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY parent_id, name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}