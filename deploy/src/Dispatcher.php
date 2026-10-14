<?php
declare(strict_types=1);

namespace SlowMail;

use PDO;
use SlowMail\Mail\Mailer;
use Throwable;

/**
 * 도착 시각이 된 편지를 발송하고, 오래된 데이터를 정리한다.
 * cron(CLI)·HTTP 트리거 어느 쪽에서 불러도 같은 결과가 나오도록 상태는 모두 DB 에 둔다.
 *
 * 중복 발송 방지: UPDATE … LIMIT 로 행을 먼저 선점(locked_by)한 뒤 보낸다.
 * (MariaDB 10.6 미만에는 SKIP LOCKED 가 없어 이 방식을 쓴다)
 */
final class Dispatcher
{
    public function __construct(
        private PDO $pdo,
        private Crypto $crypto,
        private Mailer $mailer,
        private MailTemplates $templates,
        private LetterService $letters,
        private RateLimiter $limiter,
        private Clock $clock,
        private array $config,
    ) {
    }

    /** @return array<string, int> 처리 통계 */
    public function run(): array
    {
        $stats = ['released' => $this->releaseStale()];
        $stats += $this->deliverDue();
        $stats += $this->housekeeping();
        return $stats;
    }

    /** 프로세스가 중간에 죽어 sending 에 남은 편지를 되돌린다 */
    private function releaseStale(): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE letters SET status = ?, locked_by = NULL, locked_at = NULL, updated_at = ?
             WHERE status = ? AND locked_at < ?'
        );
        $stmt->execute([
            LetterService::SEALED,
            $this->clock->nowSql(),
            LetterService::SENDING,
            $this->clock->now()->modify('-' . (int) $this->config['dispatch']['stale_minutes'] . ' minutes')->format('Y-m-d H:i:s'),
        ]);
        return $stmt->rowCount();
    }

    /** @return array{claimed: int, delivered: int, retried: int, failed: int} */
    private function deliverDue(): array
    {
        $now = $this->clock->nowSql();
        $lock = bin2hex(random_bytes(8));
        $batch = (int) $this->config['dispatch']['batch'];

        $this->pdo->prepare(
            "UPDATE letters SET status = ?, locked_by = ?, locked_at = ?, updated_at = ?
             WHERE status = ? AND deliver_at <= ? AND (next_attempt_at IS NULL OR next_attempt_at <= ?)
             ORDER BY deliver_at
             LIMIT $batch"
        )->execute([LetterService::SENDING, $lock, $now, $now, LetterService::SEALED, $now, $now]);

        $stmt = $this->pdo->prepare('SELECT * FROM letters WHERE locked_by = ? AND status = ?');
        $stmt->execute([$lock, LetterService::SENDING]);
        $rows = $stmt->fetchAll();

        $result = ['claimed' => count($rows), 'delivered' => 0, 'retried' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $outcome = $this->deliver($row);
            $result[$outcome]++;
        }
        return $result;
    }

    /** @return string 'delivered' | 'retried' | 'failed' */
    private function deliver(array $row): string
    {
        $id = (int) $row['id'];
        $attempts = (int) $row['attempts'] + 1;
        $now = $this->clock->now();

        try {
            $version = (int) $row['key_version'];
            $message = $this->templates->build('deliver', $this->crypto->decrypt($row['recipient_email_enc'], $version), [
                'title'      => $this->crypto->decrypt($row['title_enc'], $version),
                'body'       => $this->crypto->decrypt($row['body_enc'], $version),
                'writtenOn'  => $this->localDate($row['created_at']),
                'days'       => (int) (new \DateTimeImmutable($row['created_at']))->diff($now)->format('%a'),
                'purgeDays'  => (int) $this->config['letter']['purge_delivered_days'],
                'writeUrl'   => absolute_url('write'),
                'appName'    => $this->config['app']['name'],
            ]);
            $messageId = $this->mailer->send($message);
        } catch (Throwable $e) {
            return $this->fail($id, $attempts, $e->getMessage());
        }

        $this->pdo->prepare(
            'UPDATE letters SET status = ?, attempts = ?, delivered_at = ?, next_attempt_at = NULL,
                    locked_by = NULL, locked_at = NULL, last_error = NULL, updated_at = ?
             WHERE id = ?'
        )->execute([LetterService::DELIVERED, $attempts, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), $id]);
        $this->letters->logMail($id, 'deliver', true, $messageId, null);
        return 'delivered';
    }

    /** 재시도 간격을 다 쓰면 failed, 아니면 다음 시도 예약 */
    private function fail(int $id, int $attempts, string $error): string
    {
        $retry = $this->config['dispatch']['retry_minutes'];
        $now = $this->clock->now();
        $final = $attempts > count($retry);
        $next = $final ? null : $now->modify('+' . (int) $retry[$attempts - 1] . ' minutes')->format('Y-m-d H:i:s');

        $this->pdo->prepare(
            'UPDATE letters SET status = ?, attempts = ?, next_attempt_at = ?, last_error = ?,
                    locked_by = NULL, locked_at = NULL, updated_at = ?
             WHERE id = ?'
        )->execute([
            $final ? LetterService::FAILED : LetterService::SEALED,
            $attempts, $next, mb_substr($error, 0, 500), $now->format('Y-m-d H:i:s'), $id,
        ]);
        $this->letters->logMail($id, 'deliver', false, null, $error);
        error_log(sprintf('[slowmail] 발송 실패 letter=%d attempt=%d: %s', $id, $attempts, $error));
        return $final ? 'failed' : 'retried';
    }

    /** @return array{expired: int, deleted: int, purged: int, rate_limits: int} */
    private function housekeeping(): array
    {
        $now = $this->clock->now();
        $nowSql = $now->format('Y-m-d H:i:s');
        $letter = $this->config['letter'];

        // 확인 기한이 지난 편지
        $expire = $this->pdo->prepare('UPDATE letters SET status = ?, updated_at = ? WHERE status = ? AND verify_expires_at <= ?');
        $expire->execute([LetterService::EXPIRED, $nowSql, LetterService::PENDING, $nowSql]);

        // 만료 후 일정 기간이 지나면 행 삭제
        $delete = $this->pdo->prepare('DELETE FROM letters WHERE status = ? AND created_at < ?');
        $delete->execute([
            LetterService::EXPIRED,
            $now->modify('-' . (int) $letter['purge_expired_days'] . ' days')->format('Y-m-d H:i:s'),
        ]);

        // 도착한 편지는 일정 기간 뒤 본문·이메일 파기 (행과 날짜는 통계용으로 남김)
        $purge = $this->pdo->prepare(
            'UPDATE letters SET recipient_email_enc = NULL, title_enc = NULL, body_enc = NULL,
                    verify_token_hash = NULL, purged_at = ?, updated_at = ?
             WHERE status = ? AND purged_at IS NULL AND delivered_at < ?'
        );
        $purge->execute([
            $nowSql, $nowSql, LetterService::DELIVERED,
            $now->modify('-' . (int) $letter['purge_delivered_days'] . ' days')->format('Y-m-d H:i:s'),
        ]);

        return [
            'expired'     => $expire->rowCount(),
            'deleted'     => $delete->rowCount(),
            'purged'      => $purge->rowCount(),
            'rate_limits' => $this->limiter->cleanup(),
        ];
    }

    private function localDate(string $utc): string
    {
        $date = (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($this->config['timezone']));
        return format_date_ko($date->format('Y-m-d'));
    }
}
