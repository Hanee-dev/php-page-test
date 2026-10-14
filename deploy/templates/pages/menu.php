<?php
$menus = [
    ['img' => 'menu1.jpg', 'alt' => '그릴드 스테이크', 'name' => '그릴드 스테이크', 'desc' => '프리미엄 등심을 그릴에서 직접 구워 내는 시그니처 스테이크입니다.'],
    ['img' => 'menu2.jpg', 'alt' => '파스타', 'name' => '시그니처 파스타', 'desc' => '홈메이드 소스와 신선한 재료가 어우러진 파스타입니다.'],
    ['img' => 'menu3.jpg', 'alt' => '브런치', 'name' => '브런치 플레이트', 'desc' => '에그 베네딕트와 신선한 샐러드로 구성된 브런치 세트입니다.'],
];
?>
<section class="page-header">
    <div class="container">
        <h1>대표 메뉴</h1>
        <p>시그니처 요리를 만나보세요.</p>
    </div>
</section>
<section class="content-section">
    <div class="container">
        <div class="menu-grid">
            <?php foreach ($menus as $menu): ?>
                <article class="menu-card">
                    <div class="menu-card-img">
                        <img src="<?= e(img_url($menu['img'], 600, 400)) ?>" alt="<?= e($menu['alt']) ?>" loading="lazy">
                    </div>
                    <div class="menu-card-body">
                        <h3><?= e($menu['name']) ?></h3>
                        <p><?= e($menu['desc']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
