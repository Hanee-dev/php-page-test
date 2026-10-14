<?php
declare(strict_types=1);

namespace SlowMail;

use PDO;
use SlowMail\Mail\MailException;
use SlowMail\Mail\Mailer;
use Throwable;

/**
 * 편지 작성 → 확인 메일 → 봉인 흐름.
 * 상태 값과 전이는 docs/03-architecture.md §5 참고.
 */
final class LetterService
{
    public const PENDING = 'pending';
    public const SEALED = 'sealed';
    public const SENDING = 'sending';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';

    /** @var callable(string): string 확인 토큰 → 확인 링크 */
    private $verifyUrl;

    public function __construct(
        private PDO $pdo,
        private Crypto $crypto,
        private Mailer $mailer,
        private MailTemplates $templates,
        private DeliveryRules $rules,
        private RateLimiter $limiter,
        private Clock $clock,
        private array $config,
        callable $verifyUrl,
    ) {
        $this->verifyUrl = $verifyUrl;
    }

    /**
     * @param array{title?: mixed, body?: mixed, email?: mixed, preset?: mixed, custom_date?: mixed} $input
     * @return array{errors: string[], letter?: array, verify_url?: string}
     */
    public function submit(array $input, string $ip): array
    {
        $letterCfg = $this->config['letter'];
        $limits = $this->config['rate_limit'];

        if (!$this->limiter->hit('write_ip', $ip, $limits['write_ip'])) {
            return ['errors' => ['편지를 너무 많이 보냈어요. 조금 쉬었다가 다시 써 주세요.']];
        }

        $title = self::clean($input['title'] ?? '', false);
        $body = self::clean($input['body'] ?? '', true);
        $email = trim((string) ($input['email'] ?? ''));
        $preset = (string) ($input['preset'] ?? '');
        $custom = trim((string) ($input['custom_date'] ?? ''));

        $errors = [];
        if ($title === '') {
            $errors[] = '편지 제목을 적어 주세요.';
        } elseif (mb_strlen($title) > $letterCfg['title_max']) {
            $errors[] = "제목은 {$letterCfg['title_max']}자 이내로 적어 주세요.";
        }
        if ($body === '') {
            $errors[] = '편지 내용을 적어 주세요.';
        } elseif (mb_strlen($body) > $letterCfg['body_max']) {
            $errors[] = '편지가 조금 길어요. ' . number_format($letterCfg['body_max']) . '자 이내로 줄여 주세요.';
        }
        if ($email === '' || strlen($email) > $letterCfg['email_max'] || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '편지를 받을 이메일 주소를 다시 확인해 주세요.';
        }
        $deliverOn = $this->rules->resolve($preset, $custom);
        if (is_string($deliverOn)) {
            $errors[] = $deliverOn;
        }
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $emailHash = $this->crypto->hmac(mb_strtolower($email));
        if (!$this->limiter->hit('write_email', bin2hex($emailHash), $limits['write_email'])
            || $this->countPending($emailHash) >= $limits['pending_per_email']) {
            return ['errors' => ['이 주소로 확인을 기다리는 편지가 많아요. 받은 메일의 링크를 먼저 눌러 주세요.']];
        }

        $now = $this->clock->now();
        $token = bin2hex(random_bytes(32));
        $publicId = bin2hex(random_bytes(16));
        $this->pdo->prepare(
            'INSERT INTO letters
                (public_id, recipient_email_enc, recipient_email_hash, title_enc, body_enc, key_version,
                 deliver_on, deliver_at, status, verify_token_hash, verify_expires_at,
                 created_ip_hash, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $publicId,
            $this->crypto->encrypt($email),
            $emailHash,
            $this->crypto->encrypt($title),
            $this->crypto->encrypt($body),
            $this->crypto->currentVersion(),
            $deliverOn->format('Y-m-d'),
            $this->rules->deliverAt($deliverOn)->format('Y-m-d H:i:s'),
            self::PENDING,
            hash('sha256', $token, true),
            $now->modify('+' . (int) $letterCfg['verify_ttl_hours'] . ' hours')->format('Y-m-d H:i:s'),
            $this->crypto->hmac($ip),
            $now->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
        ]);
        $letterId = (int) $this->pdo->lastInsertId();

        $verifyUrl = ($this->verifyUrl)($token);
        $message = $this->templates->build('verify', $email, [
            'verifyUrl'  => $verifyUrl,
            'deliverOn'  => format_date_ko($deliverOn->format('Y-m-d')),
            'ttlHours'   => (int) $letterCfg['verify_ttl_hours'],
            'appName'    => $this->config['app']['name'],
        ]);
        try {
            $messageId = $this->mailer->send($message);
            $this->logMail($letterId, 'verify', true, $messageId, null);
        } catch (MailException $e) {
            // 확인 메일이 안 나가면 봉인할 방법이 없으므로 편지를 남기지 않는다
            $this->pdo->prepare('DELETE FROM letters WHERE id = ?')->execute([$letterId]);
            return ['errors' => ['확인 메일을 보내지 못했어요. 잠시 후 다시 시도해 주세요.']];
        }

        return [
            'errors'     => [],
            'letter'     => [
                'public_id'    => $publicId,
                'deliver_on'   => $deliverOn->format('Y-m-d'),
                'email_masked' => self::maskEmail($email),
            ],
            'verify_url' => $verifyUrl,
        ];
    }

    /**
     * 확인 링크로 들어온 편지 상태 조회 (봉인 전 확인 화면용)
     * @return array{status: string, public_id: string, deliver_on: string}|null
     */
    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT public_id, deliver_on, status, verify_expires_at FROM letters WHERE verify_token_hash = ?'
        );
        $stmt->execute([hash('sha256', $token, true)]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $status = $row['status'];
        if ($status === self::PENDING && $row['verify_expires_at'] <= $this->clock->nowSql()) {
            $status = self::EXPIRED;
        }
        return ['status' => $status, 'public_id' => $row['public_id'], 'deliver_on' => $row['deliver_on']];
    }

    /**
     * 봉인. 이미 봉인된 편지면 그대로 결과를 돌려준다(링크 두 번 클릭 대비).
     * @return array{status: string, public_id: string, deliver_on: string}|null
     */
    public function seal(string $token): ?array
    {
        $found = $this->findByToken($token);
        if ($found === null || $found['status'] !== self::PENDING) {
            return $found;
        }
        $now = $this->clock->nowSql();
        $this->pdo->prepare(
            'UPDATE letters SET status = ?, sealed_at = ?, updated_at = ?
             WHERE verify_token_hash = ? AND status = ? AND verify_expires_at > ?'
        )->execute([self::SEALED, $now, $now, hash('sha256', $token, true), self::PENDING, $now]);

        return $this->findByToken($token);
    }

    /** 밤하늘에서 기다리는 편지 수 (봉인됨 + 발송 중) */
    public function waitingCount(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM letters WHERE status IN (?, ?)');
        $stmt->execute([self::SEALED, self::SENDING]);
        return (int) $stmt->fetchColumn();
    }

    public function logMail(int $letterId, string $kind, bool $success, ?string $messageId, ?string $error): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO mail_log (letter_id, kind, driver, success, message_id, error, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $letterId, $kind, $this->mailer->name(), $success ? 1 : 0,
                $messageId, $error === null ? null : mb_substr($error, 0, 1000), $this->clock->nowSql(),
            ]);
        } catch (Throwable $e) {
            // 로그 실패가 본 흐름을 깨뜨리지 않게 한다
            error_log('[slowmail] mail_log 기록 실패: ' . $e->getMessage());
        }
    }

    private function countPending(string $emailHash): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM letters WHERE recipient_email_hash = ? AND status = ? AND verify_expires_at > ?'
        );
        $stmt->execute([$emailHash, self::PENDING, $this->clock->nowSql()]);
        return (int) $stmt->fetchColumn();
    }

    /** 잘못된 UTF-8·제어문자 제거, 줄바꿈 통일 */
    private static function clean(mixed $value, bool $multiline): string
    {
        $value = is_string($value) ? $value : '';
        if (!mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $value);
        return trim($value);
    }

    /** hanee@example.com → h***@example.com (앞부분 길이를 드러내지 않도록 * 개수 고정) */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + [1 => ''];
        return mb_substr($local, 0, 1) . '***@' . $domain;
    }
}
