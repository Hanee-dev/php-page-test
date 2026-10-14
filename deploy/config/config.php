<?php
/**
 * 사이트 설정
 * 비밀번호·API 키 등 민감 정보가 생기면 이 파일에 넣지 말고 config.local.php(.gitignore 대상)로 분리
 */
return [
    'site_name' => '푸드하우스',

    'mail' => [
        'to'   => 'join@hanee.shop',
        // Cafe24 등 호스팅에 등록된 발신 주소 (방문자 주소를 From에 쓰면 SPF/DMARC 실패로 스팸 처리됨)
        'from' => 'noreply@hanee.shop',
    ],

    'contact' => [
        'subjects'           => ['일반 문의', '예약 문의', '제휴 문의', '기타'],
        'rate_limit_seconds' => 60,
        'max_length'         => [
            'name'    => 50,
            'email'   => 100,
            'phone'   => 20,
            'message' => 2000,
        ],
    ],
];
