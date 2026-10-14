<?php
/**
 * 확인 메일
 * @var string $verifyUrl
 * @var string $deliverOn  '2027년 10월 14일'
 * @var int    $ttlHours
 * @var string $appName
 */
return [
    'subject' => "[{$appName}] 편지를 별로 띄우려면 확인해 주세요",
    'text'    => <<<TXT
방금 {$appName}에 편지를 맡겨 주셨어요.

아래 링크를 열어 이 주소가 당신의 것임을 확인해 주시면,
편지는 봉인되어 밤하늘의 작은 별이 됩니다.

{$verifyUrl}

도착 예정일: {$deliverOn} 아침
이 링크는 {$ttlHours}시간 동안만 열려 있어요. 지나면 편지는 조용히 사라집니다.

직접 쓴 편지가 아니라면 이 메일은 무시하셔도 괜찮습니다.

— {$appName}
TXT,
];
