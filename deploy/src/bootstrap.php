<?php
declare(strict_types=1);
/**
 * 공통 초기화: 설정 로드, 오토로드, 헬퍼 로드, (웹이면) 세션 시작
 * www/index.php 와 bin/*.php 에서만 require
 */
define('BASE_PATH', dirname(__DIR__));

// 내부 계산은 모두 UTC. 표시용 시간대는 $config['timezone']
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

$config = require BASE_PATH . '/config/config.php';
if (is_file(BASE_PATH . '/config/config.local.php')) {
    $config = array_replace_recursive($config, require BASE_PATH . '/config/config.local.php');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'SlowMail\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/helpers.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ]);
    session_start();
}
