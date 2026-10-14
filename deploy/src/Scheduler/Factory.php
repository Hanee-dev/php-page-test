<?php
declare(strict_types=1);

namespace SlowMail\Scheduler;

use InvalidArgumentException;

final class Factory
{
    public static function fromConfig(array $scheduler): Scheduler
    {
        return match ($scheduler['driver'] ?? 'mock') {
            'mock'  => new MockScheduler(),
            // 실제 구현체는 나중에 여기에 추가
            default => throw new InvalidArgumentException('알 수 없는 스케줄러 드라이버: ' . $scheduler['driver']),
        };
    }
}
