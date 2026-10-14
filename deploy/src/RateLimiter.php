<?php
declare(strict_types=1);

namespace SlowMail;

use PDO;

/**
 * 고정 창(fixed window) 요청 제한. 식별자(IP·이메일)는 HMAC 으로만 저장한다.
 */
final class RateLimiter
{
    public function __construct(private PDO $pdo, private Crypto $crypto, private Clock $clock)
    {
    }

    /**
     * 한 번 기록하고, 모든 규칙을 통과하면 true
     * @param array<int, array{0: int, 1: int}> $rules [[횟수, 초], ...]
     */
    public function hit(string $action, string $subject, array $rules): bool
    {
        $now = $this->clock->now()->getTimestamp();
        $allowed = true;
        foreach ($rules as [$limit, $seconds]) {
            $bucket = $this->crypto->hmac($action . '|' . $seconds . '|' . $subject);
            $windowStart = gmdate('Y-m-d H:i:s', intdiv($now, $seconds) * $seconds);

            $this->pdo->prepare(
                'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE hits = hits + 1'
            )->execute([$bucket, $windowStart]);

            $stmt = $this->pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?');
            $stmt->execute([$bucket, $windowStart]);
            if ((int) $stmt->fetchColumn() > $limit) {
                $allowed = false;
            }
        }
        return $allowed;
    }

    public function cleanup(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?');
        $stmt->execute([$this->clock->now()->modify('-2 days')->format('Y-m-d H:i:s')]);
        return $stmt->rowCount();
    }
}
