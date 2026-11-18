<?php
namespace Ministry\Models;

class TicketNote extends Model
{
    protected $table = 'ticket_notes';
    protected $primaryKey = 'note_id';
    protected $logEnabled = false;   // notes are their own thread

    /**
     * All notes for a ticket, oldest first (chat order).
     */
    public function getByTicket(int $ticketId): array
    {
        $sql = "SELECT n.note_id, n.ticket_id, n.author_entity_id, n.body, n.created_at,
                       e.full_name AS author_name,
                       e.type AS author_type
                FROM ticket_notes n
                JOIN entities e ON n.author_entity_id = e.entity_id
                WHERE n.ticket_id = ?
                ORDER BY n.created_at ASC, n.note_id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    /**
     * Count notes for a ticket (for badge display).
     */
    public function countByTicket(int $ticketId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ticket_notes WHERE ticket_id = ?");
        $stmt->execute([$ticketId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Add a note. Returns note_id or false.
     */
    public function addNote(int $ticketId, int $authorEntityId, string $body)
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            return false;
        }
        return $this->create([
            'ticket_id' => $ticketId,
            'author_entity_id' => $authorEntityId,
            'body' => $body,
        ]);
    }
}