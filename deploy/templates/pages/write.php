<?php
/**
 * @var string[] $errors
 * @var array    $old
 * @var array<string, array{label: string, date: DateTimeImmutable}> $presets
 * @var string   $minDate
 * @var string   $maxDate
 * @var array    $limits
 */
$selected = $old['preset'] ?? '';
?>
<form class="write" method="post" action="<?= e(url('write')) ?>" data-seal-form novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="hp" aria-hidden="true">
        <label>웹사이트 <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <?php if ($errors !== []): ?>
        <div class="notice notice--error" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="stage">
        <div class="paper" data-paper>
            <label class="sr-only" for="title">제목</label>
            <input class="paper__title" id="title" name="title" type="text" required
                   maxlength="<?= (int) $limits['title_max'] ?>" placeholder="편지의 제목"
                   value="<?= e($old['title'] ?? '') ?>">
            <label class="sr-only" for="body">편지 내용</label>
            <textarea class="paper__body" id="body" name="body" required rows="12"
                      maxlength="<?= (int) $limits['body_max'] ?>"
                      placeholder="미래의 나에게,&#10;&#10;지금 이곳의 날씨와 요즘의 고민, 꼭 기억했으면 하는 마음을 적어 보세요."><?= e($old['body'] ?? '') ?></textarea>
            <p class="paper__count"><span data-count>0</span> / <?= number_format($limits['body_max']) ?></p>
        </div>
        <div class="envelope envelope--sealing" aria-hidden="true">
            <div class="envelope__back"></div>
            <div class="envelope__flap"></div>
            <div class="envelope__seal"></div>
        </div>
    </div>

    <fieldset class="field">
        <legend>언제 도착할까요?</legend>
        <div class="presets">
            <?php foreach ($presets as $key => $preset): ?>
                <label class="preset">
                    <input type="radio" name="preset" value="<?= e($key) ?>" <?= $selected === $key ? 'checked' : '' ?>>
                    <span class="preset__label"><?= e($preset['label']) ?></span>
                    <span class="preset__date"><?= e(format_date_ko($preset['date']->format('Y-m-d'))) ?></span>
                </label>
            <?php endforeach; ?>
            <label class="preset">
                <input type="radio" name="preset" value="custom" <?= $selected === 'custom' ? 'checked' : '' ?>>
                <span class="preset__label">직접 고르기</span>
                <span class="preset__date">날짜를 정해요</span>
            </label>
        </div>
        <div class="custom-date" data-custom-date>
            <label for="custom_date">도착할 날</label>
            <input type="date" id="custom_date" name="custom_date"
                   min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" value="<?= e($old['custom_date'] ?? '') ?>">
            <p class="hint">한 달 뒤부터 <?= (int) $limits['max_years'] ?>년 뒤까지 고를 수 있어요. 그날 아침 <?= (int) $limits['deliver_hour'] ?>시쯤 도착해요.</p>
        </div>
    </fieldset>

    <div class="field">
        <label for="email">편지를 받을 이메일</label>
        <input type="email" id="email" name="email" required autocomplete="email" inputmode="email"
               maxlength="<?= (int) $limits['email_max'] ?>" placeholder="me@example.com" value="<?= e($old['email'] ?? '') ?>">
        <p class="hint">이 주소로 확인 메일을 보내요. 링크를 열어야 편지가 봉인됩니다.</p>
    </div>

    <div class="actions">
        <button type="submit" class="button">봉인하기</button>
        <p class="hint">봉인한 편지는 도착하는 날까지 누구도, 당신도 열어볼 수 없어요.</p>
    </div>
</form>
