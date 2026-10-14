<?php
declare(strict_types=1);

namespace SlowMail\Scheduler;

use DateTimeImmutable;

/**
 * 발송 스케줄러 계약 — 현재는 목업(MockScheduler)만 있다.
 *
 * 스케줄러는 "언제, 무엇이" 발송을 일으키는지(트리거)를 맡는다.
 * 실제 발송·재시도·파기 로직은 SlowMail\Dispatcher 에 이미 있으므로,
 * 나중에 실제 구현체를 붙일 때는 이 인터페이스를 구현하고 config['scheduler']['driver'] 만 바꾼다.
 * (cron, 외부 크론 서비스, 큐 등 어떤 방식이든 이 계약 뒤에 숨긴다)
 */
interface Scheduler
{
    /** 구현체 이름 (로그·화면 표시용) */
    public function name(): string;

    /** 주어진 시각 기준으로 한 번 실행한다 */
    public function run(DateTimeImmutable $now): SchedulerResult;
}
