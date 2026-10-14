<?php
declare(strict_types=1);
/**
 * 문의하기 메일 전송
 * Cafe24 등 PHP 호스팅에서 사용. 수신/발신 주소는 config/config.php
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$contactUrl = 'index.php?page=contact';
$contactConfig = $config['contact'];
$maxLength = $contactConfig['max_length'];

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

// 스팸 방지: 숨겨진 필드에 값이 있으면 무시
if (!empty($_POST['website'])) {
    http_response_code(400);
    echo '잘못된 요청입니다.';
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

$old = [
    'name'    => $name,
    'email'   => $email,
    'phone'   => $phone,
    'subject' => $subject,
    'message' => $message,
];

$fail = static function (array $errors) use ($old, $contactUrl): void {
    $_SESSION['contact_errors'] = $errors;
    $_SESSION['contact_old'] = $old;
    redirect($contactUrl);
};

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $fail(['요청이 만료되었습니다. 페이지를 새로고침한 후 다시 시도해 주세요.']);
}

$lastSentAt = (int) ($_SESSION['contact_last_sent_at'] ?? 0);
if (time() - $lastSentAt < $contactConfig['rate_limit_seconds']) {
    $fail(['잠시 후 다시 시도해 주세요.']);
}

$errors = [];
if ($name === '') {
    $errors[] = '이름을 입력해 주세요.';
} elseif (mb_strlen($name) > $maxLength['name']) {
    $errors[] = '이름은 ' . $maxLength['name'] . '자 이내로 입력해 주세요.';
}
if ($email === '') {
    $errors[] = '이메일을 입력해 주세요.';
} elseif (mb_strlen($email) > $maxLength['email'] || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '올바른 이메일 주소를 입력해 주세요.';
}
if ($phone !== '' && (mb_strlen($phone) > $maxLength['phone'] || !preg_match('/^[0-9+\-\s()]+$/', $phone))) {
    $errors[] = '올바른 연락처를 입력해 주세요.';
}
if ($subject !== '' && !in_array($subject, $contactConfig['subjects'], true)) {
    $errors[] = '문의 유형을 다시 선택해 주세요.';
}
if ($message === '') {
    $errors[] = '문의 내용을 입력해 주세요.';
} elseif (mb_strlen($message) > $maxLength['message']) {
    $errors[] = '문의 내용은 ' . $maxLength['message'] . '자 이내로 입력해 주세요.';
}

if ($errors !== []) {
    $fail($errors);
}

$mailSubject = '[문의] ' . ($subject !== '' ? $subject : '웹사이트 문의');
$mailBody = "다음 내용으로 웹사이트 문의가 도착했습니다.\n\n";
$mailBody .= "---\n";
$mailBody .= "이름: " . $name . "\n";
$mailBody .= "이메일: " . $email . "\n";
if ($phone !== '') {
    $mailBody .= "연락처: " . $phone . "\n";
}
if ($subject !== '') {
    $mailBody .= "문의 유형: " . $subject . "\n";
}
$mailBody .= "---\n\n";
$mailBody .= "내용:\n" . $message . "\n";

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: =?UTF-8?B?' . base64_encode($config['site_name']) . '?= <' . $config['mail']['from'] . '>';
$headers[] = 'Reply-To: ' . $email;

$sent = @mail($config['mail']['to'], '=?UTF-8?B?' . base64_encode($mailSubject) . '?=', $mailBody, implode("\r\n", $headers));

if (!$sent) {
    $fail(['메일 발송에 실패했습니다. 잠시 후 다시 시도해 주세요.']);
}

$_SESSION['contact_last_sent_at'] = time();
$_SESSION['contact_sent'] = true;
unset($_SESSION['contact_errors'], $_SESSION['contact_old']);
redirect($contactUrl);
