<?php
declare(strict_types=1);
/**
 * 발송 로직(Dispatcher) 수동 실행 — 개발·검증용 (CLI)
 *   php bin/dispatch.php                           지금 기준
 *   php bin/dispatch.php --now="2031-01-01 09:00"  해당 시각(Asia/Seoul)인 것처럼 실행
 *
 * 주기 실행(스케줄러)은 범위 밖이라 목업(SlowMail\Scheduler\MockScheduler)만 있다.
 * 운영에서 이 스크립트를 cron 에 등록하지 않는다 — 실제 스케줄러는 나중에 다른 방식으로 구현.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use SlowMail\Clock;
use SlowMail\Services;

$options = getopt('', ['now:']);
$fixed = isset($options['now'])
    ? new DateTimeImmutable((string) $options['now'], new DateTimeZone($config['timezone']))
    : null;

$services = new Services($config, new Clock($fixed));
$started = microtime(true);
$stats = $services->dispatcher()->run();

printf(
    "[%s] %s (%.2fs)\n",
    $services->clock()->now()->setTimezone(new DateTimeZone($config['timezone']))->format('Y-m-d H:i:s T'),
    http_build_query($stats, '', ' '),
    microtime(true) - $started
);
