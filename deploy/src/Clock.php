<?php
declare(strict_types=1);

namespace SlowMail;

use DateTimeImmutable;
use DateTimeZone;

/**
 * 현재 시각 공급자. 테스트·수동 검증에서 미래 시각으로 바꿔 끼울 수 있다.
 * now() 는 항상 UTC.
 */
class Clock
{
    private ?DateTimeImmutable $fixed;

    public function __construct(?DateTimeImmutable $fixed = null)
    {
        $this->fixed = $fixed?->setTimezone(new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->fixed ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** DB DATETIME 컬럼용 UTC 문자열 */
    public function nowSql(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }
}
