<?php
namespace Ministry\Models;

class Supplier extends Model
{
    protected $table = 'suppliers';
    protected $primaryKey = 'supplier_id';

    public function getByType($type)
    {
        $sql = "SELECT * FROM {$this->table} WHERE type = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
}