<?php /** @var int $waiting */ ?>
<section class="hero">
    <p class="eyebrow">오늘의 마음을, 먼 아침에게</p>
    <h1 class="hero__title">지금 쓴 편지는<br>밤하늘에서<br>천천히 기다립니다</h1>
    <p class="hero__lead">편지를 써서 봉인하면, 정해 둔 날 아침 메일함으로 도착해요.<br>빨라도 한 달, 멀게는 십 년 뒤에.</p>
    <a class="button" href="<?= e(url('write')) ?>">편지 쓰기</a>
    <p class="hero__count">
        <?php if ($waiting > 0): ?>
            지금 <strong><?= number_format($waiting) ?></strong>통의 편지가 도착을 기다리고 있어요
        <?php else: ?>
            오늘 밤, 첫 번째 별이 되어 주세요
        <?php endif; ?>
    </p>
</section>

<section class="steps" aria-label="이용 방법">
    <ol>
        <li><span class="steps__no">一</span><strong>쓰기</strong><span>미래의 나에게 하고 싶은 말을 천천히 적어요.</span></li>
        <li><span class="steps__no">二</span><strong>봉인</strong><span>메일로 받은 링크를 열면 편지가 봉인되어 별이 돼요.</span></li>
        <li><span class="steps__no">三</span><strong>기다림</strong><span>약속한 날 아침, 편지가 메일함에 도착해요.</span></li>
    </ol>
</section>
