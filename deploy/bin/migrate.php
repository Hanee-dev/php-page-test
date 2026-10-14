<?php
declare(strict_types=1);
/**
 * 스키마 마이그레이션 (CLI)
 *   php bin/migrate.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use SlowMail\Clock;
use SlowMail\Services;

$applied = (new Services($config, new Clock()))->migrator()->run();
echo $applied === [] ? "적용할 마이그레이션이 없습니다.\n" : '적용: ' . implode(', ', $applied) . "\n";
