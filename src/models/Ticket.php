<?php
namespace Ministry\Models;

use Ministry\Services\ActivityLogger;

class Ticket extends Model
{
    protected $table = 'tickets';
    protected $primaryKey = 'ticket_id';
    protected $logEntityType = 'ticket';
    protected $logEnabled = false;

    // ==================================================================
    // LIST
    // ==================================================================
    public function getWithDetails($filters = [])
    {
        $sql = "SELECT t.*,
                    a.asset_tag,
                    a.serial_number,
                    a.asset_type,
                    e.full_name as created_by_name,
                    e.department_id
                FROM tickets t
                LEFT JOIN assets a ON t.asset_id = a.asset_id
                LEFT JOIN entities e ON t.created_by = e.entity_id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['asset_id'])) {
            $sql .= " AND t.asset_id = ?";
            $params[] = $filters['asset_id'];
        }
        if (!empty($filters['created_by'])) {
            $sql .= " AND t.created_by = ?";
            $params[] = $filters['created_by'];
        }
        if (!empty($filters['ticket_type'])) {
            $sql .= " AND t.ticket_type = ?";
            $params[] = $filters['ticket_type'];
        }

        $sql .= " ORDER BY t.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ==================================================================
    // STATS — new status set
    // ==================================================================
    public function getStats()
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) as pending_approval,
                    SUM(CASE WHEN status = 'open'             THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_progress'      THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'awaiting_parts'   THEN 1 ELSE 0 END) as awaiting_parts,
                    SUM(CASE WHEN status = 'frozen'           THEN 1 ELSE 0 END) as frozen,
                    SUM(CASE WHEN status = 'closed'           THEN 1 ELSE 0 END) as closed
                FROM {$this->table}";
        $stmt = $this->db->query($sql);
        $row = $stmt->fetch();

        // Legacy keys for any remaining UI that still references them
        $row['resolved'] = 0;
        $row['rejected'] = 0;
        $row['pending'] = (int) $row['pending_approval'];
        return $row;
    }

    // ==================================================================
    // STATUS MACHINE
    // ==================================================================
    public function transitionStatus(int $id, string $newStatus, int $actorEntityId): bool
    {
        $validTransitions = [
            'pending_approval' => ['open', 'frozen', 'closed'],
            'open' => ['in_progress', 'frozen', 'closed'],
            'in_progress' => ['awaiting_parts', 'closed', 'open', 'frozen'],
            'awaiting_parts' => ['in_progress', 'frozen', 'closed', 'open'],
            'frozen' => ['pending_approval', 'open', 'in_progress', 'awaiting_parts'],
            'closed' => [],
        ];

        $current = $this->findById($id);
        if (!$current)
            return false;

        $oldStatus = $current['status'];
        if ($oldStatus === $newStatus)
            return true;
        if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [], true)) {
            return false;
        }

        $extra = [];
        if ($newStatus === 'open' && empty($current['approved_at'])) {
            $extra['approved_by'] = $actorEntityId;
            $extra['approved_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'frozen') {
            $extra['frozen_by'] = $actorEntityId;
            $extra['frozen_at'] = date('Y-m-d H:i:s');
        }
        if ($oldStatus === 'frozen' && $newStatus !== 'frozen') {
            $extra['frozen_by'] = null;
            $extra['frozen_at'] = null;
        }
        if ($newStatus === 'closed') {
            $extra['closed_at'] = date('Y-m-d H:i:s');
        }

        $data = array_merge(['status' => $newStatus], $extra);
        $result = $this->update($id, $data);

        if ($result) {
            $logger = ActivityLogger::getInstance();
            $logger->log('ticket', $id, 'status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }
        return $result;
    }

    public function approveTicket(int $id, int $approverEntityId): bool
    {
        return $this->transitionStatus($id, 'open', $approverEntityId);
    }

    public function freezeTicket(int $id, int $actorEntityId): bool
    {
        return $this->transitionStatus($id, 'frozen', $actorEntityId);
    }

    public function unfreezeTicket(int $id, int $actorEntityId, string $targetStatus = 'open'): bool
    {
        $valid = ['pending_approval', 'open', 'in_progress', 'awaiting_parts'];
        if (!in_array($targetStatus, $valid, true))
            $targetStatus = 'open';
        return $this->transitionStatus($id, $targetStatus, $actorEntityId);
    }

    public function reassignTechnician(int $id, int $newTechnicianEntityId, int $actorEntityId): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM ticket_assignments WHERE ticket_id = ?")->execute([$id]);

            $assignModel = new TicketAssignment();
            $assignmentId = $assignModel->assign($id, $newTechnicianEntityId, 'pending');
            if (!$assignmentId)
                throw new \RuntimeException('Failed to create assignment');

            $this->db->commit();

            $logger = ActivityLogger::getInstance();
            $logger->log('ticket', $id, 'assigned', [
                'technician_id' => $newTechnicianEntityId,
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Reassign failed: ' . $e->getMessage());
            return false;
        }
    }

    // ==================================================================
    // EDIT FIELDS
    // ==================================================================
    public function updateTicket($id, $data)
    {
        $old = $this->findById($id);
        if (!$old)
            return false;

        $allowed = ['title', 'description', 'priority', 'asset_id', 'ticket_type', 'category', 'estimated_hours', 'internal_notes'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData))
            return true;

        $result = $this->update($id, $updateData);
        if ($result) {
            $logger = ActivityLogger::getInstance();
            $changes = [];
            foreach ($updateData as $field => $newValue) {
                if (isset($old[$field]) && $old[$field] != $newValue) {
                    $changes[$field] = ['old' => $old[$field], 'new' => $newValue];
                }
            }
            if (!empty($changes)) {
                $logger->log('ticket', $id, 'updated', ['changes' => $changes]);
            }
        }
        return $result;
    }

    // ==================================================================
    // FULL DETAILS
    // ==================================================================
    public function getFullDetails($id)
    {
        $sql = "SELECT t.*,
                    a.asset_tag,
                    a.serial_number,
                    a.asset_type,
                    e.full_name as created_by_name,
                    e.department_id
                FROM {$this->table} t
                LEFT JOIN assets a ON t.asset_id = a.asset_id
                LEFT JOIN entities e ON t.created_by = e.entity_id
                WHERE t.{$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if ($ticket) {
            $assignModel = new TicketAssignment();
            $ticket['assignments'] = $assignModel->getByTicket($id);

            $stmt = $this->db->prepare(
                "SELECT attachment_id, file_path, original_name, mime_type, file_size, created_at
                 FROM ticket_attachments
                 WHERE ticket_id = ?
                 ORDER BY created_at"
            );
            $stmt->execute([$id]);
            $ticket['attachments'] = $stmt->fetchAll();
        }

        return $ticket;
    }

    // ==================================================================
// CREATE — mandatory paper, auto-assign uploader, status = pending_approval
// ==================================================================
    public function createTicket($data)
    {
        if (empty($data['title']) || empty($data['description'])) {
            throw new \InvalidArgumentException('العنوان والوصف مطلوبان');
        }
        if (empty($data['attachment_path']) || empty($data['attachment_name'])) {
            throw new \InvalidArgumentException('يجب إرفاق التذكرة الورقية أو تقرير التقديم');
        }

        $hasUser = !empty($data['created_by']);
        $hasPublic = !empty($data['reporter_name']) && !empty($data['reporter_phone']);
        if ($hasUser === $hasPublic) {
            throw new \InvalidArgumentException('يجب تحديد هوية مقدّم واحد فقط');
        }

        $data['status'] = 'pending_approval';
        $data['priority'] = $data['priority'] ?? 'medium';
        $data['ticket_type'] = $data['ticket_type'] ?? 'internal';

        $attachment = [
            'path' => $data['attachment_path'],
            'name' => $data['attachment_name'],
            'mime' => $data['attachment_mime'] ?? 'application/octet-stream',
            'size' => (int) ($data['attachment_size'] ?? 0),
        ];
        $ticketData = $data;
        unset(
            $ticketData['attachment_path'],
            $ticketData['attachment_name'],
            $ticketData['attachment_mime'],
            $ticketData['attachment_size'],
            $ticketData['attachment_id']
        );

        // NOTE: no beginTransaction() / commit() / rollBack() here.
        // The caller owns the transaction.
        $ticketId = $this->create($ticketData);
        if (!$ticketId) {
            throw new \RuntimeException('Failed to create ticket row');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO ticket_attachments
            (ticket_id, file_path, original_name, mime_type, file_size, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $ticketId,
            $attachment['path'],
            $attachment['name'],
            $attachment['mime'],
            $attachment['size'],
            $hasUser ? $data['created_by'] : null,
        ]);

        if ($hasUser) {
            $assignModel = new TicketAssignment();
            $assignmentId = $assignModel->assign($ticketId, $data['created_by'], 'pending');
            if (!$assignmentId) {
                throw new \RuntimeException('Failed to auto-assign ticket');
            }
        }

        ActivityLogger::getInstance()->log('ticket', $ticketId, 'created', [
            'title' => $data['title'],
            'status' => 'pending_approval',
            'paper_name' => $attachment['name'],
            'assigned_to' => $hasUser ? (int) $data['created_by'] : null,
            'public' => !$hasUser,
        ]);

        return $ticketId;
    }
    // ==================================================================
    // LIST-VIEW ASSIGNMENT PRE-FETCH
    // ==================================================================
    public function getAssignmentsForTickets($ticketIds)
    {
        if (empty($ticketIds))
            return [];
        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT a.ticket_id, a.assignment_id, a.technician_id, a.status,
                       e.full_name as technician_name
                FROM ticket_assignments a
                JOIN entities e ON a.technician_id = e.entity_id
                WHERE a.ticket_id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ticketIds);
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['ticket_id']][] = $row;
        }
        return $grouped;
    }
}