<?php
declare(strict_types=1);
/**
 * 푸드/레스토랑 - 페이지별 이동 방식
 * PHP 8.x
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$siteName = $config['site_name'];

$page = $_GET['page'] ?? 'home';
$allowed = ['home', 'about', 'menu', 'gallery', 'contact'];
if (!in_array($page, $allowed, true)) {
    $page = 'home';
}

$titles = [
    'home'    => '홈',
    'about'   => '소개',
    'menu'    => '메뉴',
    'gallery' => '갤러리',
    'contact' => '문의하기',
];
$title = $titles[$page];

require BASE_PATH . '/templates/layout/header.php';
?>

<article class="page-content" id="content-<?= e($page) ?>">
    <?php require BASE_PATH . '/templates/pages/' . $page . '.php'; ?>
</article>

<?php require BASE_PATH . '/templates/layout/footer.php';
