<?php
declare(strict_types=1);

/** HTML 출력용 이스케이프 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** 점 표기 설정 조회: cfg('letter.min_days') */
function cfg(string $path, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }
        $value = $value[$key];
    }
    return $value;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** 사이트 내부 경로: url('write') → /write (pretty_urls 꺼지면 /index.php?r=write) */
function url(string $route = '', array $query = []): string
{
    if ($route !== '' && !cfg('app.pretty_urls', true)) {
        $query = ['r' => $route] + $query;
        $route = 'index.php';
    }
    $qs = $query === [] ? '' : '?' . http_build_query($query);
    return '/' . $route . $qs;
}

/** 메일 등 외부에서 쓰는 절대 주소 */
function absolute_url(string $route = '', array $query = []): string
{
    $base = rtrim((string) cfg('app.url', ''), '/');
    if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
        $base = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    }
    return $base . url($route, $query);
}

function asset(string $path): string
{
    $file = BASE_PATH . '/www/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return '/assets/' . $path . '?v=' . $version;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** 다음 요청에서 한 번만 꺼내 쓰는 세션 값 */
function flash(string $key, mixed $value = null): mixed
{
    if (func_num_args() === 2) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

/** 'Y-m-d' (표시 시간대 기준) → '2027년 10월 14일' */
function format_date_ko(string $ymd): string
{
    [$y, $m, $d] = array_map('intval', explode('-', $ymd));
    return sprintf('%d년 %d월 %d일', $y, $m, $d);
}

function client_ip(): string
{
    // 공유 호스팅에서 프록시 헤더는 위조 가능하므로 REMOTE_ADDR 만 신뢰
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
