<?php
declare(strict_types=1);
/**
 * GitHub Actions 에서 환경 변수(Secrets)로 운영용 config.local.php 를 만든다.
 *   php deploy-tools/make-config.php > deploy/config/config.local.php
 *
 * 필수: DB_NAME, DB_USER, DB_PASSWORD, CRYPTO_KEY_1, HMAC_KEY, OPS_TOKEN
 * 선택: DB_HOST(기본 localhost), DB_PORT(3306), APP_URL, CRYPTO_KEY_2.. / CRYPTO_CURRENT(키 교체 시)
 *
 * 값은 var_export 로 내보내므로 비밀번호에 따옴표·$ 가 있어도 안전하다.
 * 하나라도 비어 있거나 키 형식이 틀리면 실패(exit 1)해서 빈 설정이 서버에 올라가지 않게 한다.
 * 이 파일은 deploy/ 밖에 있어 서버에 올라가지 않는다.
 */

$errors = [];
$env = static function (string $name, ?string $default = null) use (&$errors): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default === null) {
            $errors[] = "$name 가 비어 있습니다 (Secrets 확인)";
        }
        return $default ?? '';
    }
    return $value;
};
$key = static function (string $name, string $value) use (&$errors): void {
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) !== 32) {
        $errors[] = "$name 는 base64 로 인코딩한 32바이트여야 합니다 (openssl rand -base64 32)";
    }
};

$keys = [];
for ($version = 1; $version <= 20; $version++) {
    $value = getenv("CRYPTO_KEY_$version");
    if ($value !== false && $value !== '') {
        $key("CRYPTO_KEY_$version", $value);
        $keys[$version] = $value;
    }
}
if (!isset($keys[1])) {
    $errors[] = 'CRYPTO_KEY_1 가 비어 있습니다 (Secrets 확인)';
}
$current = (int) $env('CRYPTO_CURRENT', (string) ($keys === [] ? 1 : max(array_keys($keys))));
if ($keys !== [] && !isset($keys[$current])) {
    $errors[] = "CRYPTO_CURRENT=$current 에 해당하는 CRYPTO_KEY_$current 가 없습니다";
}

$config = [
    'app' => [
        'url'   => rtrim($env('APP_URL', ''), '/'),
        'debug' => false,
    ],
    'db' => [
        'host'     => $env('DB_HOST', 'localhost'),
        'port'     => (int) $env('DB_PORT', '3306'),
        'name'     => $env('DB_NAME'),
        'user'     => $env('DB_USER'),
        'password' => $env('DB_PASSWORD'),
    ],
    'crypto' => [
        'keys'     => $keys,
        'current'  => $current,
        'hmac_key' => $env('HMAC_KEY'),
    ],
    'mail' => [
        // 실제 발송 드라이버가 생기기 전까지 운영에서는 아무것도 저장하지 않는다
        'driver' => 'null',
    ],
    'ops' => [
        'token' => $env('OPS_TOKEN'),
    ],
];
if ($config['crypto']['hmac_key'] !== '') {
    $key('HMAC_KEY', $config['crypto']['hmac_key']);
}
if ($config['ops']['token'] !== '' && strlen($config['ops']['token']) < 32) {
    $errors[] = 'OPS_TOKEN 은 32자 이상이어야 합니다 (openssl rand -hex 32)';
}

if ($errors !== []) {
    fwrite(STDERR, "config.local.php 를 만들 수 없습니다:\n  - " . implode("\n  - ", array_unique($errors)) . "\n");
    exit(1);
}

echo "<?php\n// 배포 시 GitHub Actions 가 생성한 파일 — 직접 수정하지 말 것 (deploy-tools/make-config.php)\nreturn "
    . var_export($config, true) . ";\n";
