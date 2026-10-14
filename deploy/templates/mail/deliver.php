<?php
/**
 * 도착 메일
 * @var string $title
 * @var string $body
 * @var string $writtenOn  '2026년 10월 14일'
 * @var int    $days       작성 후 지난 날 수
 * @var int    $purgeDays
 * @var string $writeUrl
 * @var string $appName
 */
return [
    'subject' => "[{$appName}] {$writtenOn}의 당신이 보낸 편지가 도착했어요",
    'text'    => <<<TXT
{$days}일 동안 밤하늘에서 기다린 편지가 오늘 도착했습니다.

────────────────────

{$title}

{$body}

────────────────────

{$writtenOn}에 쓴 편지입니다.

이 편지의 내용은 {$purgeDays}일 뒤 {$appName}에서 지워집니다.
오래 간직하고 싶다면 이 메일을 보관해 주세요.

오늘의 마음도 미래로 보내 보시겠어요?
{$writeUrl}

— {$appName}
TXT,
];
