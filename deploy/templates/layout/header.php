<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | <?= e($siteName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header" id="site-header">
        <div class="container">
            <a href="index.php" class="logo"><?= e($siteName) ?></a>
            <nav class="main-nav" aria-label="메인 메뉴">
                <a href="index.php" class="nav-link<?= $page === 'home' ? ' active' : '' ?>">홈</a>
                <a href="index.php?page=about" class="nav-link<?= $page === 'about' ? ' active' : '' ?>">소개</a>
                <a href="index.php?page=menu" class="nav-link<?= $page === 'menu' ? ' active' : '' ?>">메뉴</a>
                <a href="index.php?page=gallery" class="nav-link<?= $page === 'gallery' ? ' active' : '' ?>">갤러리</a>
                <a href="index.php?page=contact" class="nav-link<?= $page === 'contact' ? ' active' : '' ?>">문의하기</a>
            </nav>
            <button type="button" class="nav-toggle" aria-label="메뉴 열기" aria-expanded="false">☰</button>
        </div>
    </header>
    <main class="main-content">
