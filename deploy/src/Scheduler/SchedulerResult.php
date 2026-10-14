<?php
declare(strict_types=1);

namespace SlowMail\Scheduler;

use DateTimeImmutable;

/** 스케줄러 한 번 실행의 결과 */
final class SchedulerResult
{
    /**
     * @param array<string, int> $stats 처리 통계 (예: delivered, retried, failed …)
     */
    public function __construct(
        public string $scheduler,
        public DateTimeImmutable $ranAt,
        public array $stats = [],
        public bool $mocked = false,
    ) {
    }
}
