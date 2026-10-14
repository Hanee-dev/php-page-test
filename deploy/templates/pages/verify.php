<?php
/**
 * 메일 링크의 GET 은 보안 스캐너가 미리 열어 볼 수 있으므로, 봉인은 버튼(POST)으로만 한다.
 * @var array{status: string, public_id: string, deliver_on: string} $letter
 * @var string $token
 */
?>
<section class="scene">
    <div class="envelope envelope--floating" aria-hidden="true">
        <div class="envelope__back"></div>
        <div class="envelope__flap"></div>
        <div class="envelope__seal"></div>
    </div>
    <h1 class="scene__title">이 편지를 봉인할까요?</h1>
    <p class="scene__text">
        봉인하면 편지는 별이 되어 <strong><?= e(format_date_ko($letter['deliver_on'])) ?></strong> 아침까지
        밤하늘에서 기다려요. 그동안은 누구도 열어볼 수 없어요.
    </p>
    <form method="post" action="<?= e(url('verify')) ?>" data-star-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="t" value="<?= e($token) ?>">
        <button type="submit" class="button">봉인하고 별로 띄우기</button>
    </form>
</section>
