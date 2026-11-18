<?php
namespace Ministry\Services;

use Ministry\Models\ActivityLog;

class ActivityLogger
{
    private static $instance = null;
    private $model;

    private function __construct()
    {
        $this->model = new ActivityLog();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($entityType, $entityId, $eventType, $details = [], $actorId = null)
    {
        if (empty($entityType) || empty($entityId)) {
            return false;
        }
        return $this->model->log($entityType, $entityId, $eventType, $details, $actorId);
    }

    public function asset($action, $assetId, $details = [])
    {
        return $this->log('asset', $assetId, $action, $details);
    }

    public function consumable($action, $consumableId, $details = [])
    {
        return $this->log('consumable', $consumableId, $action, $details);
    }

    public function ticket($action, $ticketId, $details = [])
    {
        return $this->log('ticket', $ticketId, $action, $details);
    }

    public function user($action, $userId, $details = [])
    {
        return $this->log('user', $userId, $action, $details);
    }

    public function entity($action, $entityId, $details = [])
    {
        return $this->log('entity', $entityId, $action, $details);
    }

    public function login($userId, $status = 'success', $details = [])
    {
        return $this->log('login', $userId, 'login_' . $status, $details);
    }

    public function system($event, $details = [])
    {
        return $this->log('system', 0, $event, $details);
    }
}