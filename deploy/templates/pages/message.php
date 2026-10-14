<?php
/**
 * @var string $heading
 * @var string $text
 */
?>
<section class="scene">
    <h1 class="scene__title"><?= e($heading) ?></h1>
    <p class="scene__text"><?= e($text) ?></p>
    <div class="actions actions--center">
        <a class="button button--ghost" href="<?= e(url()) ?>">처음으로</a>
    </div>
</section>
