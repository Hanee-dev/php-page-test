<?php
declare(strict_types=1);
/**
 * 도착 시각이 된 편지 발송 + 정리 작업 (CLI)
 *   php bin/dispatch.php                           지금 기준
 *   php bin/dispatch.php --now="2031-01-01 09:00"  해당 시각(Asia/Seoul)인 것처럼 실행 — 검증용
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
