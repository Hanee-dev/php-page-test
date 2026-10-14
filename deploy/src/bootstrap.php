<?php
declare(strict_types=1);
/**
 * 공통 초기화: 설정 로드, 헬퍼 로드, 세션 시작
 * public/ 아래 진입점(index.php, send_mail.php)에서만 require
 */
define('BASE_PATH', dirname(__DIR__));

$config = require BASE_PATH . '/config/config.php';
require BASE_PATH . '/src/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
