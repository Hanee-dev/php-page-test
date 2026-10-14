<?php
/**
 * @var array{status: string, public_id: string, deliver_on: string} $letter
 * @var int  $daysLeft
 * @var int  $waiting
 * @var bool $again   이미 봉인된 편지의 링크를 다시 연 경우
 */
$again = $again ?? false;
?>
<section class="scene scene--sealed">
    <div class="my-star<?= $again ? ' my-star--still' : '' ?>" data-my-star data-seed="<?= e($letter['public_id']) ?>" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 2l2.4 7.2H22l-6 4.6 2.3 7.2L12 16.6 5.7 21l2.3-7.2-6-4.6h7.6z"/></svg>
    </div>
    <p class="eyebrow"><?= $again ? '이미 봉인된 편지예요' : '봉인되었습니다' ?></p>
    <h1 class="scene__title"><?= e(format_date_ko($letter['deliver_on'])) ?> 아침,<br>이 편지가 도착합니다</h1>
    <p class="days"><span class="days__num"><?= number_format($daysLeft) ?></span><span class="days__unit">일 남았어요</span></p>
    <p class="scene__text">
        <?php if ($waiting > 1): ?>
            오늘 밤, <?= number_format($waiting - 1) ?>통의 편지가 당신의 별 곁에서 함께 기다려요.
        <?php else: ?>
            오늘 밤 하늘의 첫 번째 별이에요.
        <?php endif; ?>
    </p>
    <div class="actions actions--center">
        <a class="button button--ghost" href="<?= e(url('write')) ?>">한 통 더 쓰기</a>
    </div>
</section>
