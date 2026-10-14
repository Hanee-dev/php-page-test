<?php
declare(strict_types=1);
/**
 * 느린 우체통 — 프런트 컨트롤러
 * www/.htaccess 의 FallbackResource 로 모든 경로가 여기로 온다.
 * (FallbackResource 가 안 되는 환경: config app.pretty_urls=false → /index.php?r=경로)
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use SlowMail\Clock;
use SlowMail\Controller;
use SlowMail\Services;

header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; "
    . "font-src https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; "
    . "base-uri 'self'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// 확인 링크의 토큰이 외부(웹폰트 등) 요청의 Referer 로 새지 않게
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$route = isset($_GET['r'])
    ? trim((string) $_GET['r'], '/')
    : trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($route === 'index.php') {
    $route = '';
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$services = new Services($config, new Clock());
$controller = new Controller($services, $config);

// 배포 후 첫 요청에서 새 마이그레이션 적용. 평소엔 marker 파일만 확인하고, 실패해도 예외를 밖으로 내지 않는다
if (cfg('app.auto_migrate')) {
    $services->autoMigrator()->ensure();
}

try {
    match (true) {
        $route === ''                           => $controller->home(),
        $route === 'write' && $method === 'GET'  => $controller->writeForm(),
        $route === 'write' && $method === 'POST' => $controller->writeSubmit(),
        $route === 'posted'                     => $controller->posted(),
        $route === 'verify' && $method === 'GET'  => $controller->verifyForm(),
        $route === 'verify' && $method === 'POST' => $controller->verifySubmit(),
        $route === 'sealed'                     => $controller->sealed(),
        $route === 'about'                      => $controller->page('about', '편지는 이렇게 보관됩니다'),
        $route === 'privacy'                    => $controller->page('privacy', '개인정보처리방침'),
        $route === 'ops/migrate' && $method === 'POST' => $controller->opsMigrate(),
        default                                 => $controller->notFound(),
    };
} catch (Throwable $e) {
    error_log('[slowmail] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        $controller->message(500, '잠시 우체국이 쉬고 있어요', $config['app']['debug']
            ? $e->getMessage()
            : '일시적인 문제가 생겼어요. 잠시 후 다시 시도해 주세요.');
    }
}
