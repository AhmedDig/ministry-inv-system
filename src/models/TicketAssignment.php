<?php
namespace Ministry\Models;

use Ministry\Services\ActivityLogger;

class TicketAssignment extends Model
{
    protected $table = 'ticket_assignments';
    protected $primaryKey = 'assignment_id';

    /**
     * Get all assignments for a given ticket.
     *
     * @param int $ticketId
     * @return array
     */
    public function getByTicket($ticketId)
    {
        $sql = "SELECT a.*, e.full_name as technician_name, e.type as technician_type
                FROM ticket_assignments a
                JOIN entities e ON a.technician_id = e.entity_id
                WHERE a.ticket_id = ?
                ORDER BY a.assigned_at";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    /**
     * Assign a technician to a ticket.
     *
     * @param int $ticketId
     * @param int $technicianId
     * @param string $status (pending, active)
     * @return int|false (assignment_id on success)
     */
    public function assign($ticketId, $technicianId, $status = 'pending')
    {
        // Check for duplicate
        $stmt = $this->db->prepare("SELECT assignment_id FROM ticket_assignments WHERE ticket_id = ? AND technician_id = ?");
        $stmt->execute([$ticketId, $technicianId]);
        if ($stmt->fetch()) {
            return false;
        }

        $data = [
            'ticket_id' => $ticketId,
            'technician_id' => $technicianId,
            'status' => $status,
            'assigned_at' => date('Y-m-d H:i:s')
        ];
        $result = $this->create($data);
        if ($result) {
            $entityModel = new Entity();
            $tech = $entityModel->findById($technicianId);
            $logger = ActivityLogger::getInstance();
            $logger->log('ticket', $ticketId, 'assigned', [
                'technician_name' => $tech['full_name'] ?? 'غير معروف'
            ]);
        }
        return $result;
    }

    /**
     * Remove a technician from a ticket (hard delete).
     *
     * @param int $assignmentId
     * @return bool
     */
    public function unassign($assignmentId)
    {
        $assignment = $this->findById($assignmentId);
        if (!$assignment) {
            return false;
        }

        // Hard delete
        $sql = "DELETE FROM ticket_assignments WHERE assignment_id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$assignmentId]);

        if ($result) {
            $logger = ActivityLogger::getInstance();
            $logger->log('ticket', $assignment['ticket_id'], 'unassigned', []);
        }
        return $result;
    }

    /**
     * Update assignment status (e.g., to 'completed').
     *
     * @param int $assignmentId
     * @param string $status
     * @return bool
     */
    public function updateAssignmentStatus($assignmentId, $status)
    {
        $allowed = ['pending', 'active', 'completed'];
        if (!in_array($status, $allowed)) {
            return false;
        }

        $data = ['status' => $status];
        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        $result = $this->update($assignmentId, $data);
        if ($result) {
            $assignment = $this->findById($assignmentId);
            if ($assignment) {
                $logger = ActivityLogger::getInstance();
                $logger->log('ticket', $assignment['ticket_id'], 'assignment_status_changed', [
                    'assignment_id' => $assignmentId,
                    'new_status' => $status
                ]);
            }
        }
        return $result;
    }
}