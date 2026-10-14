<?php
declare(strict_types=1);

namespace SlowMail\Mail;

use InvalidArgumentException;

final class Factory
{
    public static function fromConfig(array $mail, string $storagePath): Mailer
    {
        return match ($mail['driver'] ?? 'log') {
            'log'  => new LogMailer($storagePath . '/mail'),
            'null' => new NullMailer(),
            // 'smtp' => new SmtpMailer(...),   ← 실제 발송을 붙일 때 추가
            default => throw new InvalidArgumentException('알 수 없는 메일 드라이버: ' . $mail['driver']),
        };
    }
}
