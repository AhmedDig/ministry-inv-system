<?php
namespace Ministry\Models;

use Ministry\Services\ActivityLogger;

abstract class Model
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    // Logging configuration
    protected $logEnabled = true;
    protected $logEntityType = null; // Must be set in child classes (e.g., 'asset', 'user')

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find all records with optional filters.
     */
    public function findAll($filters = [], $order = '', $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];

        foreach ($filters as $key => $value) {
            $sql .= " AND {$key} = ?";
            $params[] = $value;
        }

        if ($order) {
            $sql .= " ORDER BY {$order}";
        }

        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find a single record by ID.
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create a new record and log the event.
     */
    public function create($data)
    {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute(array_values($data));
        $id = $this->db->lastInsertId();

        if ($success && $id && $this->logEnabled && $this->logEntityType) {
            $logger = ActivityLogger::getInstance();
            // Extract a clean name for logging (if available)
            $name = $data['name'] ?? $data['title'] ?? $data['full_name'] ?? $data['username'] ?? $data['asset_tag'] ?? null;
            $logger->log($this->logEntityType, $id, 'created', ['name' => $name]);
        }
        return $id;
    }

    /**
     * Update a record and log the changes.
     */
    public function update($id, $data)
    {
        $oldData = $this->findById($id);
        if (!$oldData) {
            return false;
        }

        $fields = array_keys($data);
        $set = implode(' = ?, ', $fields) . ' = ?';

        $sql = "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?";
        $values = array_values($data);
        $values[] = $id;

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($values);

        if ($success && $this->logEnabled && $this->logEntityType) {
            $changes = [];
            foreach ($data as $key => $value) {
                if (isset($oldData[$key]) && $oldData[$key] != $value) {
                    $changes[$key] = ['old' => $oldData[$key], 'new' => $value];
                }
            }
            if (!empty($changes)) {
                $logger = ActivityLogger::getInstance();
                $logger->log($this->logEntityType, $id, 'updated', ['changes' => $changes]);
            }
        }
        return $success;
    }

    /**
     * Soft delete a record (sets is_active = 0) and log the event.
     */
    public function delete($id)
    {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([$id]);

        if ($success && $this->logEnabled && $this->logEntityType) {
            $logger = ActivityLogger::getInstance();
            $logger->log($this->logEntityType, $id, 'deleted', []);
        }
        return $success;
    }

    /**
     * Hard delete a record (removes permanently) – use with caution.
     */
    public function hardDelete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
}