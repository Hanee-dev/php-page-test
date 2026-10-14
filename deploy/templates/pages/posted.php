<?php
/**
 * @var array{public_id: string, deliver_on: string, email_masked: string, verify_url: ?string} $letter
 * @var int $ttlHours
 */
?>
<section class="scene">
    <div class="envelope envelope--floating" aria-hidden="true">
        <div class="envelope__back"></div>
        <div class="envelope__flap"></div>
        <div class="envelope__seal"></div>
    </div>
    <h1 class="scene__title">편지가 떠날 준비를 마쳤어요</h1>
    <p class="scene__text">
        <?php if (empty($letter['verify_url'])): ?>
            <strong><?= e($letter['email_masked']) ?></strong>로 확인 메일을 보냈어요.<br>
            <?= $ttlHours ?>시간 안에 메일 속 링크를 열면 편지가 봉인되어 별이 됩니다.
        <?php else: ?>
            편지는 <strong><?= e($letter['email_masked']) ?></strong>로 도착할 거예요.<br>
            <?= $ttlHours ?>시간 안에 아래 확인 링크를 열면 편지가 봉인되어 별이 됩니다.
        <?php endif; ?>
    </p>
    <p class="scene__sub">도착 예정일 · <?= e(format_date_ko($letter['deliver_on'])) ?> 아침</p>

    <?php if (!empty($letter['verify_url'])): ?>
        <div class="notice notice--dev">
            <p><strong>[미리보기]</strong> 지금은 실제 메일을 보내지 않아요. 메일 대신 여기서 확인 링크를 열어 봉인해 주세요.</p>
            <a href="<?= e($letter['verify_url']) ?>">확인 링크 열기</a>
        </div>
    <?php endif; ?>
</section>
