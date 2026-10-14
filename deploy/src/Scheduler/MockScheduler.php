<?php
declare(strict_types=1);

namespace SlowMail\Scheduler;

use DateTimeImmutable;

/**
 * 목업 스케줄러: 호출만 기록하고 아무 편지도 보내지 않으며 DB 도 건드리지 않는다.
 * 실제 스케줄러는 다른 방식으로 나중에 구현한다 (docs/03-architecture.md §8).
 */
final class MockScheduler implements Scheduler
{
    /** @var DateTimeImmutable[] 호출된 시각 (테스트·확인용) */
    public array $calls = [];

    public function name(): string
    {
        return 'mock';
    }

    public function run(DateTimeImmutable $now): SchedulerResult
    {
        $this->calls[] = $now;
        return new SchedulerResult($this->name(), $now, [], true);
    }
}
