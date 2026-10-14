<?php
$contactErrors = $_SESSION['contact_errors'] ?? [];
$contactOld = $_SESSION['contact_old'] ?? [];
$contactSent = !empty($_SESSION['contact_sent']);
// 한 번 보여준 후 제거
unset($_SESSION['contact_sent'], $_SESSION['contact_errors'], $_SESSION['contact_old']);

$subjects = $config['contact']['subjects'];
$maxLength = $config['contact']['max_length'];
?>
<section class="page-header">
    <div class="container">
        <h1>문의하기</h1>
        <p>궁금한 점이 있으시면 아래 양식을 작성해 보내 주세요.</p>
    </div>
</section>
<section class="content-section contact-section">
    <div class="container contact-wrap">
        <div class="contact-info">
            <p><strong>주소</strong> 서울특별시 강남구 테헤란로 123</p>
            <p><strong>전화</strong> 02-1234-5678</p>
            <p><strong>이메일</strong> <?= e($config['mail']['to']) ?></p>
            <p><strong>영업 시간</strong> 화~일 11:00 - 22:00 (월요일 휴무)</p>
        </div>
        <div class="contact-form-wrap">
            <?php if ($contactSent): ?>
                <div class="form-message form-message--success">
                    문의가 접수되었습니다. 빠르게 연락드리겠습니다.
                </div>
            <?php endif; ?>
            <?php if ($contactErrors !== []): ?>
                <ul class="form-errors">
                    <?php foreach ($contactErrors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form class="contact-form" action="send_mail.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
                <div class="form-row">
                    <label for="contact-name">이름 <span class="required">*</span></label>
                    <input type="text" id="contact-name" name="name" value="<?= e($contactOld['name'] ?? '') ?>" required maxlength="<?= $maxLength['name'] ?>" placeholder="이름을 입력하세요">
                </div>
                <div class="form-row">
                    <label for="contact-email">이메일 <span class="required">*</span></label>
                    <input type="email" id="contact-email" name="email" value="<?= e($contactOld['email'] ?? '') ?>" required maxlength="<?= $maxLength['email'] ?>" placeholder="example@email.com">
                </div>
                <div class="form-row">
                    <label for="contact-phone">연락처</label>
                    <input type="tel" id="contact-phone" name="phone" value="<?= e($contactOld['phone'] ?? '') ?>" maxlength="<?= $maxLength['phone'] ?>" placeholder="010-0000-0000">
                </div>
                <div class="form-row">
                    <label for="contact-subject">문의 유형</label>
                    <select id="contact-subject" name="subject">
                        <option value="">선택하세요</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= e($subject) ?>" <?= ($contactOld['subject'] ?? '') === $subject ? 'selected' : '' ?>><?= e($subject) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="contact-message">문의 내용 <span class="required">*</span></label>
                    <textarea id="contact-message" name="message" rows="5" required maxlength="<?= $maxLength['message'] ?>" placeholder="문의 내용을 입력하세요"><?= e($contactOld['message'] ?? '') ?></textarea>
                </div>
                <div class="form-row form-actions">
                    <button type="submit" class="btn btn-primary">보내기</button>
                </div>
            </form>
        </div>
    </div>
</section>
