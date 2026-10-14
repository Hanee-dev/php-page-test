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
        <strong><?= e($letter['email_masked']) ?></strong>로 확인 메일을 보냈어요.<br>
        <?= $ttlHours ?>시간 안에 메일 속 링크를 열면 편지가 봉인되어 별이 됩니다.
    </p>
    <p class="scene__sub">도착 예정일 · <?= e(format_date_ko($letter['deliver_on'])) ?> 아침</p>

    <?php if (!empty($letter['verify_url'])): ?>
        <div class="notice notice--dev">
            <p><strong>[개발용]</strong> 실제 메일 발송이 꺼져 있어 확인 링크를 여기에 보여 줍니다. (debug 모드에서만 표시)</p>
            <a href="<?= e($letter['verify_url']) ?>">확인 링크 열기</a>
        </div>
    <?php endif; ?>
</section>
