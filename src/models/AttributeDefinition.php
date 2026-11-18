<?php
namespace Ministry\Models;

class AttributeDefinition extends Model
{
    protected $table = 'attribute_definitions';
    protected $primaryKey = 'attr_key';
    protected $logEnabled = false;

    private static $cache = null;

    public function getAll(): array
    {
        if (self::$cache === null) {
            $stmt = $this->db->query("SELECT * FROM attribute_definitions ORDER BY sort_order, attr_key");
            self::$cache = $stmt->fetchAll();
            // Decode JSON columns
            foreach (self::$cache as &$def) {
                foreach (['allowed_values', 'applies_to_types', 'visible_when'] as $col) {
                    if (!empty($def[$col]) && is_string($def[$col])) {
                        $def[$col . '_decoded'] = json_decode($def[$col], true);
                    } else {
                        $def[$col . '_decoded'] = null;
                    }
                }
            }
            unset($def);
        }
        return self::$cache;
    }

    public function getByKey(string $key): ?array
    {
        foreach ($this->getAll() as $def) {
            if ($def['attr_key'] === $key)
                return $def;
        }
        return null;
    }

    public function forAssetType(string $assetType): array
    {
        $out = [];
        foreach ($this->getAll() as $def) {
            $types = $def['applies_to_types_decoded'];
            if ($types === null || in_array($assetType, $types, true)) {
                $out[] = $def;
            }
        }
        return $out;
    }
}