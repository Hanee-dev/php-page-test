<section class="page-header">
    <div class="container">
        <h1>갤러리</h1>
        <p>매장과 요리 사진입니다.</p>
    </div>
</section>
<section class="content-section">
    <div class="container">
        <div class="gallery-grid">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="gallery-item"><img src="<?= e(img_url("gallery{$i}.jpg", 600, 400)) ?>" alt="갤러리 <?= $i ?>" loading="lazy"></div>
            <?php endfor; ?>
        </div>
    </div>
</section>
