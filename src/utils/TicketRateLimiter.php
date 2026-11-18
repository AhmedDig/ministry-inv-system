<?php
namespace Ministry\Utils;

class TicketRateLimiter
{
    private const MAX_PER_3_HOURS = 1;
    private const MAX_PER_24_HOURS = 3;

    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Check if the phone can submit a ticket right now.
     * Returns [allowed: bool, reason: string|null, retry_after_minutes: int|null]
     */
    public function check(string $canonicalPhone): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(created_at >= NOW() - INTERVAL 3 HOUR) AS last_3h,
                SUM(created_at >= NOW() - INTERVAL 24 HOUR) AS last_24h,
                MIN(CASE WHEN created_at >= NOW() - INTERVAL 3 HOUR
                         THEN created_at END) AS oldest_in_window
             FROM tickets
             WHERE reporter_phone = ?"
        );
        $stmt->execute([$canonicalPhone]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $last3h = (int) ($row['last_3h'] ?? 0);
        $last24h = (int) ($row['last_24h'] ?? 0);

        // Rule 1: max 1 per 3 hours
        if ($last3h >= self::MAX_PER_3_HOURS) {
            $oldest = $row['oldest_in_window'];
            $retryAt = strtotime($oldest) + 3 * 3600;
            $minutes = max(1, (int) ceil(($retryAt - time()) / 60));
            return [
                'allowed' => false,
                'reason' => 'لديك تذكرة حديثة، سيتم الرد عليك قريباً. يرجى المحاولة بعد ' . $minutes . ' دقيقة.',
                'retry_after_minutes' => $minutes,
            ];
        }

        // Rule 2: max 3 per 24 hours
        if ($last24h >= self::MAX_PER_24_HOURS) {
            return [
                'allowed' => false,
                'reason' => 'تم الوصول للحد اليومي (3 تذاكر). يرجى المحاولة غداً.',
                'retry_after_minutes' => null,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'retry_after_minutes' => null];
    }
}