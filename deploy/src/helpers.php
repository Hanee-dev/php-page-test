<?php
declare(strict_types=1);

/** HTML 출력용 이스케이프 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** www/assets/img 에 파일이 있으면 그 경로, 없으면 placeholder 이미지 */
function img_url(string $file, int $w = 800, int $h = 600): string
{
    if (file_exists(BASE_PATH . '/www/assets/img/' . $file)) {
        return 'assets/img/' . $file;
    }
    $seed = pathinfo($file, PATHINFO_FILENAME);
    return 'https://picsum.photos/seed/' . $seed . '/' . $w . '/' . $h;
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
    header('Location: ' . $url);
    exit;
}
