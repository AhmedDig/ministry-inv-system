<?php
namespace Ministry\Services;

use Ministry\Models\AttributeDefinition;

class AssetAttributeWriter
{
    private $db;
    private $defModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->defModel = new AttributeDefinition();
    }

    /**
     * Validate attributes without saving.
     * @return ['success' => bool, 'errors' => [...], 'normalized' => [key => ['value'=>..., 'num'=>...]]]
     */
    public function validate(array $pairs, string $assetType): array
    {
        $errors = [];
        $normalized = [];
        $seenKeys = [];

        foreach ($pairs as $pair) {
            $key = trim($pair['key'] ?? '');
            $rawValue = trim((string) ($pair['value'] ?? ''));
            if ($key === '')
                continue;

            $def = $this->defModel->getByKey($key);
            if (!$def) {
                $errors[] = "الخاصية '{$key}' غير معروفة";
                continue;
            }

            if (isset($seenKeys[$key])) {
                $errors[] = "الخاصية '{$def['display_label_ar']}' مكررة";
                continue;
            }
            $seenKeys[$key] = true;

            $types = $def['applies_to_types_decoded'];
            if ($types !== null && !in_array($assetType, $types, true)) {
                $errors[] = "الخاصية '{$def['display_label_ar']}' لا تنطبق على هذا النوع من الأصول";
                continue;
            }

            if ($rawValue === '') {
                $errors[] = "قيمة الخاصية '{$def['display_label_ar']}' مطلوبة";
                continue;
            }

            $norm = $this->normalize($def, $rawValue);
            if ($norm === null) {
                $errors[] = "قيمة الخاصية '{$def['display_label_ar']}' غير صالحة";
                continue;
            }

            $normalized[$key] = $norm;
        }

        // Prerequisite (visible_when) validation
        foreach (array_keys($normalized) as $key) {
            $def = $this->defModel->getByKey($key);
            $rule = $def['visible_when_decoded'] ?? null;
            if (!$rule)
                continue;

            $prereqKey = $rule['attr_key'] ?? null;
            $operator = $rule['operator'] ?? '=';
            $expected = $rule['value'] ?? null;

            if (!$prereqKey)
                continue;

            if (!isset($normalized[$prereqKey])) {
                $prereqDef = $this->defModel->getByKey($prereqKey);
                $prereqLabel = $prereqDef['display_label_ar'] ?? $prereqKey;
                $errors[] = "الخاصية '{$def['display_label_ar']}' تتطلب تحديد '{$prereqLabel}' أولاً";
                unset($normalized[$key]);
                continue;
            }

            $actual = (string) ($normalized[$prereqKey]['value'] ?? '');
            if (!$this->ruleMatches($actual, $operator, $expected)) {
                $errors[] = "الخاصية '{$def['display_label_ar']}' غير متوافقة مع القيمة الحالية لـ '{$prereqKey}'";
                unset($normalized[$key]);
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'normalized' => []];
        }
        return ['success' => true, 'errors' => [], 'normalized' => $normalized];
    }

    /**
     * Persist normalized attributes for a given asset (replaces existing).
     */
    public function save(int $assetId, array $normalized): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM asset_attributes WHERE asset_id = ?");
            $stmt->execute([$assetId]);

            $ins = $this->db->prepare("INSERT INTO asset_attributes (asset_id, attr_key, attr_value, attr_num) VALUES (?, ?, ?, ?)");
            foreach ($normalized as $key => $norm) {
                $ins->execute([$assetId, $key, $norm['value'], $norm['num']]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Attribute save failed for asset ' . $assetId . ': ' . $e->getMessage());
            return false;
        }
    }

    private function normalize(array $def, string $raw): ?array
    {
        $type = $def['value_type'];

        if ($type === 'number') {
            preg_match('/\d+(\.\d+)?/', str_replace(',', '.', $raw), $m);
            if (!isset($m[0]))
                return null;
            return ['value' => $m[0], 'num' => (float) $m[0]];
        }

        if ($type === 'enum') {
            $allowed = $def['allowed_values_decoded'] ?? [];
            $lower = mb_strtolower(trim($raw));
            foreach ($allowed as $opt) {
                if (mb_strtolower($opt) === $lower) {
                    return ['value' => $opt, 'num' => null];
                }
            }
            return null;
        }

        return ['value' => preg_replace('/\s+/', ' ', trim($raw)), 'num' => null];
    }

    private function ruleMatches(string $actual, string $operator, $expected): bool
    {
        switch ($operator) {
            case '=':
                return $actual === (string) $expected;
            case 'in':
                return is_array($expected) && in_array($actual, $expected, true);
            case 'starts_with':
                return str_starts_with($actual, (string) $expected);
            case 'contains':
                return str_contains($actual, (string) $expected);
            default:
                return false;
        }
    }
}