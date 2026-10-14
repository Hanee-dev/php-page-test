<?php
/**
 * 사이트 기본 설정 (비밀값 없음)
 * DB 접속 정보·암호화 키·토큰은 config.local.php 에서 덮어쓴다.
 *   - 운영: 배포 시 GitHub Secrets 로 생성 (deploy-tools/make-config.php)
 *   - 로컬: config.local.example.php 를 복사해서 작성
 */
return [
    'app' => [
        'name'        => '느린 우체통',
        // 메일 속 링크에 쓰는 절대 주소 (끝에 / 없이). 비우면 웹 요청의 호스트로 추정
        'url'         => '',
        'debug'       => false,
        // Cafe24 에서 FallbackResource 가 동작하지 않으면 false (→ /index.php?r=write 형태)
        'pretty_urls' => true,
        // 개인정보처리방침에 표시하는 문의처
        'contact_email' => 'join@hanee.shop',
    ],

    // 표시·입력 기준 시간대. DB 에는 항상 UTC 로 저장
    'timezone' => 'Asia/Seoul',

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => '',
        'user'     => '',
        'password' => '',
    ],

    'crypto' => [
        // [버전 => base64(32바이트)]. 키 교체 시 새 버전을 추가하고 current 를 올린다 (옛 키는 복호화용으로 유지)
        'keys'     => [],
        'current'  => 1,
        // 이메일·IP 해시용 base64(32바이트)
        'hmac_key' => '',
    ],

    'mail' => [
        // log: storage/mail/*.eml 로 저장 (실제 발송 없음) / null: 아무것도 안 함
        'driver'    => 'log',
        // Cafe24 등 호스팅에 등록된 도메인 주소 (SPF/DKIM 정렬)
        'from'      => 'noreply@hanee.shop',
        'from_name' => '느린 우체통',
    ],

    'letter' => [
        'min_days'              => 30,
        'max_years'             => 10,
        'deliver_hour'          => 9,     // 도착일 오전 9시 (timezone 기준)
        'title_max'             => 100,
        'body_max'              => 10000,
        'email_max'             => 254,
        'verify_ttl_hours'      => 48,
        'purge_expired_days'    => 7,     // 확인 안 된 편지 삭제
        'purge_delivered_days'  => 30,    // 발송 후 본문·이메일 파기
        'presets' => [
            '100d' => ['label' => '100일 뒤', 'modify' => '+100 days'],
            '6m'   => ['label' => '6개월 뒤', 'modify' => '+6 months'],
            '1y'   => ['label' => '1년 뒤',   'modify' => '+1 year'],
            '3y'   => ['label' => '3년 뒤',   'modify' => '+3 years'],
            '5y'   => ['label' => '5년 뒤',   'modify' => '+5 years'],
        ],
        'default_preset' => '1y',
    ],

    'rate_limit' => [
        // [횟수, 초]
        'write_ip'         => [[5, 3600], [20, 86400]],
        'write_email'      => [[10, 86400]],
        'pending_per_email' => 3,
        'verify_ip'        => [[30, 60]],
    ],

    'dispatch' => [
        'batch'         => 20,
        'retry_minutes' => [10, 60, 360, 1440, 4320],
        'stale_minutes' => 30,
    ],

    // 발송 스케줄러(트리거). 현재는 목업만 있다 — 실제 방식은 나중에 구현체로 교체
    'scheduler' => [
        'driver' => 'mock',
    ],

    'cron' => [
        'token' => '',
    ],
];
