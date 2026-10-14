<?php
/**
 * 로컬 개발용 설정 예시 — config.local.php 로 복사해서 사용 (git 제외 대상)
 * 운영 서버의 config.local.php 는 배포 시 GitHub Secrets 로 자동 생성되므로 직접 올리지 않는다.
 *
 * 키 생성: openssl rand -base64 32
 */
return [
    'app' => [
        'url'   => 'http://localhost:8080',
        'debug' => true,
    ],
    'db' => [
        'host'     => 'db',
        'name'     => 'slowmail',
        'user'     => 'slowmail',
        'password' => 'slowmail',
    ],
    'crypto' => [
        'keys'     => [1 => 'REPLACE_WITH_BASE64_32_BYTES'],
        'hmac_key' => 'REPLACE_WITH_BASE64_32_BYTES',
    ],
    'ops' => [
        'token' => 'local-ops-token',
    ],
];
