<?php
/**
 * @var string $title
 * @var string $page
 * @var string $appName
 */
$fullTitle = $page === 'home' ? $appName : $title . ' · ' . $appName;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#14182B">
    <meta name="description" content="지금의 마음을 편지로 써 두면, 정해 둔 날 아침 메일함으로 천천히 도착합니다.">
    <title><?= e($fullTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gowun+Batang:wght@400;700&family=Gowun+Dodum&family=Song+Myung&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="page-<?= e($page) ?>">
    <div class="sky" aria-hidden="true" data-stars="<?= (int) ($waiting ?? 0) ?>"></div>
    <header class="site-header">
        <a href="<?= e(url()) ?>" class="logo">
            <svg class="logo__star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l2.4 7.2H22l-6 4.6 2.3 7.2L12 16.6 5.7 21l2.3-7.2-6-4.6h7.6z"/></svg>
            <?= e($appName) ?>
        </a>
        <?php if ($page !== 'write'): ?>
            <a href="<?= e(url('write')) ?>" class="header-link">편지 쓰기</a>
        <?php endif; ?>
    </header>
    <main class="main" id="main">
