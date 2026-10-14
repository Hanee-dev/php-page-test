<?php
declare(strict_types=1);
/**
 * 느린 우체통 자동 테스트 (PHPUnit 없이 실행)
 *   docker compose exec web php /var/www/tests/run.php
 *
 * slowmail_test DB 를 매번 비우고, 시계를 앞으로 돌려 "미래 날짜 발송"까지 검증한다.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$appRoot = getenv('APP_ROOT') ?: '/var/www/app';
require $appRoot . '/src/bootstrap.php';

use SlowMail\AutoMigrator;
use SlowMail\Clock;
use SlowMail\Crypto;
use SlowMail\LetterService;
use SlowMail\Mail\MailException;
use SlowMail\Mail\Mailer;
use SlowMail\Mail\Message;
use SlowMail\Mail\NullMailer;
use SlowMail\Migrator;
use SlowMail\Services;

// ── 테스트 설정 ────────────────────────────────────────────────
$config['db']['name'] = getenv('TEST_DB_NAME') ?: 'slowmail_test';
$config['mail']['driver'] = 'null';
$config['app']['url'] = 'https://example.test';
$config['crypto'] = [
    'keys'     => [1 => base64_encode(str_repeat('k', 32)), 2 => base64_encode(str_repeat('n', 32))],
    'current'  => 1,
    'hmac_key' => base64_encode(str_repeat('h', 32)),
];
$GLOBALS['config'] = $config;

$passed = 0;
$failed = [];
function check(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ✓ $name\n";
    } else {
        $failed[] = $name;
        echo "  ✗ $name\n";
    }
}
function section(string $name): void
{
    echo "\n$name\n";
}

/** 지정 시각(Asia/Seoul)의 서비스 묶음. Mailer 를 바꿔 끼울 수 있다 */
function services(string $seoulTime, ?Mailer $mailer = null): Services
{
    global $config;
    $clock = new Clock(new DateTimeImmutable($seoulTime, new DateTimeZone('Asia/Seoul')));
    $services = new Services($config, $clock);
    if ($mailer !== null) {
        (function () use ($mailer) {
            $this->made['mailer'] = $mailer;
        })->call($services);
    }
    return $services;
}

final class FailingMailer implements Mailer
{
    public function name(): string
    {
        return 'failing';
    }

    public function send(Message $message): string
    {
        throw new MailException('SMTP 연결 실패(테스트)');
    }
}

function input(array $override = []): array
{
    return $override + [
        'title'       => '스물아홉의 나에게',
        'body'        => "안녕, 미래의 나.\n오늘은 비가 왔어.",
        'email'       => 'Me@Example.com',
        'preset'      => '1y',
        'custom_date' => '',
    ];
}

function row(string $publicId): array
{
    $stmt = services('now')->pdo()->prepare('SELECT * FROM letters WHERE public_id = ?');
    $stmt->execute([$publicId]);
    return $stmt->fetch() ?: [];
}

function tokenFrom(NullMailer $mailer): string
{
    $last = end($mailer->sent);
    preg_match('/[?&]t=([0-9a-f]{64})/', $last->text, $m);
    return $m[1] ?? '';
}

// ── 준비: 테스트 DB 초기화 + 마이그레이션 ───────────────────────
$pdo = services('now')->pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec("DROP TABLE `$table`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
services('now')->migrator()->run();

// ── Crypto ─────────────────────────────────────────────────────
section('암호화');
$crypto = Crypto::fromConfig($config['crypto']);
$blob = $crypto->encrypt('비밀 편지 ✉️');
check($crypto->decrypt($blob, 1) === '비밀 편지 ✉️', 'sodium 암복호화 왕복');
check($blob !== $crypto->encrypt('비밀 편지 ✉️'), '같은 평문도 매번 다른 암호문 (nonce)');
check($crypto->hmac('a@b.c') === $crypto->hmac('a@b.c') && strlen($crypto->hmac('x')) === 32, 'HMAC 결정적·32바이트');

$key = base64_decode($config['crypto']['keys'][1]);
$nonce = random_bytes(12);
$tag = '';
$gcm = "\x02" . $nonce . openssl_encrypt('GCM 편지', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16) . $tag;
check($crypto->decrypt($gcm, 1) === 'GCM 편지', 'AES-256-GCM 형식(sodium 없는 서버에서 만든 암호문) 복호화');

try {
    $crypto->decrypt($blob, 2);
    check(false, '다른 키 버전으로는 복호화 실패');
} catch (RuntimeException) {
    check(true, '다른 키 버전으로는 복호화 실패');
}

// ── 도착일 규칙 ────────────────────────────────────────────────
section('도착일 규칙 (기준: 2026-10-14 23:30 KST)');
$rules = services('2026-10-14 23:30')->rules();
check($rules->today()->format('Y-m-d') === '2026-10-14', 'UTC 로는 14:30 이어도 오늘은 서울 기준 10월 14일');
check($rules->minDate()->format('Y-m-d') === '2026-11-13', '최소 30일 뒤 = 11월 13일');
check($rules->resolve('1y', '')->format('Y-m-d') === '2027-10-14', '1년 프리셋');
check(is_string($rules->resolve('custom', '2026-11-12')), '29일 뒤는 거절');
check($rules->resolve('custom', '2026-11-13') instanceof DateTimeImmutable, '30일 뒤는 허용');
check(is_string($rules->resolve('custom', '2036-10-15')), '10년 초과 거절');
check(is_string($rules->resolve('custom', '2027-02-30')), '존재하지 않는 날짜 거절');
check(is_string($rules->resolve('2y', '')), '없는 프리셋 거절');
check(
    $rules->deliverAt(new DateTimeImmutable('2027-10-14', new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i') === '2027-10-14 00:00',
    '도착 시각: 2027-10-14 09:00 KST = 00:00 UTC'
);

// ── 작성 → 확인 → 봉인 ─────────────────────────────────────────
section('작성 · 확인 메일 · 봉인');
$mailer = new NullMailer();
$s = services('2026-10-14 10:00', $mailer);
$result = $s->letters()->submit(input(), '10.0.0.1');
check($result['errors'] === [], '정상 제출');
$publicId = $result['letter']['public_id'];
$r = row($publicId);
check($r['status'] === 'pending', '상태 pending');
check(!str_contains($r['body_enc'], '비가') && !str_contains($r['recipient_email_enc'], 'Example'), 'DB 에 본문·이메일 평문 없음');
check($r['deliver_at'] === '2027-10-14 00:00:00' && $r['deliver_on'] === '2027-10-14', 'deliver_on / deliver_at(UTC) 저장');
check(count($mailer->sent) === 1 && $mailer->sent[0]->to === 'Me@Example.com', '확인 메일 1통 발송(인터페이스 호출)');
$token = tokenFrom($mailer);
check(strlen($token) === 64 && str_contains($result['verify_url'], 'https://example.test/verify?t='), '확인 링크에 64자 토큰');
check($result['letter']['email_masked'] === 'M***@Example.com', '이메일 마스킹');

$errors = $s->letters()->submit(input(['title' => '', 'email' => 'not-an-email', 'preset' => 'custom', 'custom_date' => '2026-10-20']), '10.0.0.9')['errors'];
check(count($errors) === 3, '검증 오류 3건(제목·이메일·날짜)');
$errors = $s->letters()->submit(input(['body' => str_repeat('가', 10001)]), '10.0.0.9')['errors'];
check(count($errors) === 1, '본문 10,000자 초과 거절');

$found = $s->letters()->findByToken($token);
check($found['status'] === 'pending', 'GET /verify 는 상태만 조회 (봉인 안 함)');
check($s->letters()->findByToken(str_repeat('0', 64)) === null && $s->letters()->findByToken("' OR 1=1 --") === null, '잘못된 토큰은 null');

$sealed = services('2026-10-15 09:00')->letters()->seal($token);
check($sealed['status'] === 'sealed' && row($publicId)['sealed_at'] === '2026-10-15 00:00:00', '23시간 뒤 봉인 성공');
check(services('2026-10-15 09:05')->letters()->seal($token)['status'] === 'sealed', '링크 두 번 열어도 그대로 sealed');
check(services('2026-10-15 09:05')->letters()->waitingCount() === 1, '기다리는 편지 수 1');

$late = new NullMailer();
$r2 = services('2026-10-14 10:00', $late)->letters()->submit(input(['email' => 'late@example.com']), '10.0.0.2');
check(services('2026-10-16 10:01')->letters()->seal(tokenFrom($late))['status'] === 'expired', '48시간 지나면 봉인 불가(expired)');

// ── 요청 제한 ─────────────────────────────────────────────────
section('요청 제한');
$s = services('2026-10-14 11:00', new NullMailer());
$okCount = 0;
for ($i = 0; $i < 6; $i++) {
    $res = $s->letters()->submit(input(['email' => "rate$i@example.com"]), '10.0.0.3');
    $okCount += $res['errors'] === [] ? 1 : 0;
}
check($okCount === 5, '같은 IP 시간당 5통까지만');
$okCount = 0;
for ($i = 0; $i < 4; $i++) {
    $res = $s->letters()->submit(input(['email' => 'same@example.com']), '10.0.1.' . $i);
    $okCount += $res['errors'] === [] ? 1 : 0;
}
check($okCount === 3, '같은 이메일 미확인 편지 동시 3통까지만');
check(services('2026-10-14 12:30', new NullMailer())->letters()->submit(input(['email' => 'next@example.com']), '10.0.0.3')['errors'] === [], '한 시간이 지나면 다시 허용');

// ── 발송 (미래로 시계 이동) ─────────────────────────────────────
section('발송 스케줄러');
$out = new NullMailer();
$stats = services('2027-10-14 08:59', $out)->dispatcher()->run();
check($stats['claimed'] === 0 && $out->sent === [], '도착 1분 전: 아무것도 보내지 않음');

$stats = services('2027-10-14 09:00', $out)->dispatcher()->run();
check($stats['delivered'] === 1 && count($out->sent) === 1, '2027-10-14 09:00 KST 정각에 1통 발송');
$mail = $out->sent[0];
check(str_contains($mail->text, "안녕, 미래의 나.\n오늘은 비가 왔어.") && str_contains($mail->text, '스물아홉의 나에게'), '도착 메일에 복호화된 제목·본문');
check(str_contains($mail->subject, '2026년 10월 14일의 당신이 보낸 편지'), '도착 메일 제목에 작성일(KST)');
check(row($publicId)['status'] === 'delivered', '상태 delivered');
$log = $pdo->query("SELECT kind, success FROM mail_log WHERE letter_id = " . (int) row($publicId)['id'] . " ORDER BY id")->fetchAll();
check(array_column($log, 'kind') === ['verify', 'deliver'] && (int) $log[1]['success'] === 1, 'mail_log 에 verify·deliver 기록');

$stats = services('2027-10-14 09:10', $out)->dispatcher()->run();
check($stats['claimed'] === 0 && count($out->sent) === 1, '다시 실행해도 중복 발송 없음');

// 발송 지연: 스케줄러가 며칠 멈췄다가 돌아와도 밀린 편지를 보낸다
$m = new NullMailer();
$res = services('2026-10-14 13:00', $m)->letters()->submit(input(['email' => 'pause@example.com', 'preset' => 'custom', 'custom_date' => '2026-12-01']), '10.0.0.4');
services('2026-10-14 13:05')->letters()->seal(tokenFrom($m));
$stats = services('2026-12-05 03:00', $out)->dispatcher()->run();
check(row($res['letter']['public_id'])['status'] === 'delivered', '4일 늦게 실행돼도 유실 없이 발송');

section('재시도 · 실패');
$m = new NullMailer();
$res = services('2026-10-14 14:00', $m)->letters()->submit(input(['email' => 'retry@example.com', 'preset' => 'custom', 'custom_date' => '2027-01-01']), '10.0.0.5');
services('2026-10-14 14:05')->letters()->seal(tokenFrom($m));
$id = $res['letter']['public_id'];
$t = new DateTimeImmutable('2027-01-01 09:00', new DateTimeZone('Asia/Seoul'));
$stats = services($t->format('Y-m-d H:i'), new FailingMailer())->dispatcher()->run();
$r = row($id);
check($stats['retried'] === 1 && $r['status'] === 'sealed' && (int) $r['attempts'] === 1, '첫 실패 → sealed 로 되돌리고 재시도 예약');
check($r['next_attempt_at'] === '2027-01-01 00:10:00', '10분 뒤 재시도');
$stats = services($t->modify('+5 minutes')->format('Y-m-d H:i'), new FailingMailer())->dispatcher()->run();
check($stats['claimed'] === 0, '재시도 시각 전에는 건드리지 않음');
foreach ([10, 60, 360, 1440] as $i => $_) {
    $t = new DateTimeImmutable((string) row($id)['next_attempt_at'], new DateTimeZone('UTC'));
    services($t->setTimezone(new DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i'), new FailingMailer())->dispatcher()->run();
}
$r = row($id);
check((int) $r['attempts'] === 5 && $r['status'] === 'sealed', '5번째 실패까지는 계속 재시도');
$t = new DateTimeImmutable((string) $r['next_attempt_at'], new DateTimeZone('UTC'));
$stats = services($t->setTimezone(new DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i'), new FailingMailer())->dispatcher()->run();
$r = row($id);
check($stats['failed'] === 1 && $r['status'] === 'failed' && (int) $r['attempts'] === 6, '6번째 실패 → failed');
check(str_contains((string) $r['last_error'], 'SMTP 연결 실패'), 'last_error 기록');

section('멈춘 작업 복구');
$m = new NullMailer();
$res = services('2026-10-14 15:00', $m)->letters()->submit(input(['email' => 'stale@example.com', 'preset' => 'custom', 'custom_date' => '2027-02-01']), '10.0.0.6');
services('2026-10-14 15:05')->letters()->seal(tokenFrom($m));
$pdo->prepare("UPDATE letters SET status = 'sending', locked_by = 'deadbeefdeadbeef', locked_at = '2027-02-01 00:00:00' WHERE public_id = ?")
    ->execute([$res['letter']['public_id']]);
$stats = services('2027-02-01 09:10', $out)->dispatcher()->run();
check($stats['released'] === 0 && row($res['letter']['public_id'])['status'] === 'sending', '30분 전에는 다른 프로세스 작업으로 보고 그대로 둠');
$stats = services('2027-02-01 09:31', $out)->dispatcher()->run();
check($stats['released'] === 1 && row($res['letter']['public_id'])['status'] === 'delivered', '30분 지나면 되돌려서 발송');

section('정리 작업');
$stats = services('2027-11-14 09:00')->dispatcher()->run();
$r = row($publicId);
check($r['purged_at'] !== null && $r['body_enc'] === null && $r['recipient_email_enc'] === null, '발송 30일 뒤 본문·이메일 파기');
check($r['deliver_on'] === '2027-10-14' && $r['status'] === 'delivered', '파기 후에도 날짜·상태는 남김');
check(row($r2['letter']['public_id']) === [], '확인 안 된 편지는 만료 7일 뒤 삭제');
check((int) $pdo->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn() === 0, '오래된 요청 제한 기록 삭제');

// ── 스케줄러(목업) ─────────────────────────────────────────────
section('스케줄러 목업');
$before = $pdo->query("SELECT COUNT(*), SUM(status = 'delivered') FROM letters")->fetch(PDO::FETCH_NUM);
$scheduler = services('2030-01-01 09:00')->scheduler();
$result = $scheduler->run(new DateTimeImmutable('2030-01-01 00:00', new DateTimeZone('UTC')));
check($scheduler->name() === 'mock' && $result->mocked === true && $result->stats === [], '기본 드라이버는 mock, 결과는 mocked');
check(count($scheduler->calls) === 1, '호출만 기록');
check($pdo->query("SELECT COUNT(*), SUM(status = 'delivered') FROM letters")->fetch(PDO::FETCH_NUM) == $before, 'DB 를 건드리지 않음(발송 없음)');

// ── 자동 마이그레이션 ─────────────────────────────────────────
// 마지막 섹션: 테스트 DB 를 비워 "배포 직후 새 DB" 를 만들고, 임시 폴더의 마이그레이션·marker 로 검증한다
section('자동 마이그레이션 (AutoMigrator)');
$tmp = sys_get_temp_dir() . '/slowmail-automigrate-' . bin2hex(random_bytes(4));
$migDir = $tmp . '/migrations';
$marker = $tmp . '/cache/schema_version';   // cache/ 는 아직 없음 → ensure() 가 만들어야 한다
mkdir($migDir, 0700, true);
$realFiles = glob($appRoot . '/migrations/*.sql');
sort($realFiles, SORT_STRING);
foreach ($realFiles as $file) {
    copy($file, $migDir . '/' . basename($file));
}
$realVersions = array_map(static fn (string $f): string => basename($f, '.sql'), $realFiles);
$errorLog = $tmp . '/error.log';
$prevErrorLog = ini_set('error_log', $errorLog);

/** 예외 없이 끝나면 true */
function ensureQuietly(AutoMigrator $auto): bool
{
    try {
        $auto->ensure();
        return true;
    } catch (Throwable) {
        return false;
    }
}
/** 호출되면 $called 를 세우고 예외를 던지는 Migrator 대역 (= DB 접근 시도 감지) */
function forbiddenMigrator(bool &$called): Closure
{
    return static function () use (&$called): Migrator {
        $called = true;
        throw new RuntimeException('DB 에 접근하면 안 됨');
    };
}
$versions = static fn (): array => $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
$markerValue = static fn (): ?string => is_file($marker) ? (string) file_get_contents($marker) : null;

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec("DROP TABLE `$table`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$auto = new AutoMigrator(new Migrator($pdo, $migDir, new Clock()), $migDir, $marker);
check(ensureQuietly($auto) && $versions() === $realVersions, '새 DB: ensure() 가 모든 마이그레이션 적용');
check($pdo->query("SHOW TABLES LIKE 'letters'")->fetchColumn() === 'letters', '새 DB: letters 테이블 생성');
check($markerValue() === end($realVersions), 'marker 폴더를 만들고 최신 버전 기록');

$called = false;
$ok = ensureQuietly(new AutoMigrator(forbiddenMigrator($called), $migDir, $marker));
check($ok && !$called, 'marker 가 최신이면 DB 에 접근하지 않음');

$called = false;
$ok = ensureQuietly(new AutoMigrator(forbiddenMigrator($called), $tmp . '/empty', $tmp . '/none'));
check($ok && !$called && !is_file($tmp . '/none'), '마이그레이션 파일이 없으면 아무것도 하지 않음');

file_put_contents($migDir . '/990_probe.sql', "CREATE TABLE auto_migrate_probe (id INT);\n");
check(ensureQuietly($auto) && $versions() === [...$realVersions, '990_probe'], '새 파일 추가 → 그 파일만 적용');
check($pdo->query("SHOW TABLES LIKE 'auto_migrate_probe'")->fetchColumn() === 'auto_migrate_probe', '새 파일의 테이블 생성');
check($markerValue() === '990_probe', 'marker 를 새 버전으로 갱신');

file_put_contents($migDir . '/995_broken.sql', "CREATE TABLE auto_migrate_probe (id INT);\n");   // 이미 있는 테이블 → 실패
check(ensureQuietly($auto), '마이그레이션 SQL 이 실패해도 ensure() 는 예외를 던지지 않음');
check($markerValue() === '990_probe' && !in_array('995_broken', $versions(), true), '실패하면 marker·schema_migrations 갱신 안 함');
check(str_contains((string) @file_get_contents($errorLog), '자동 마이그레이션 실패'), '실패는 error_log 에 남김');

// 정리: 테스트가 만든 테이블·기록·파일 (DB 는 원래 마이그레이션만 적용된 상태로 둔다)
ini_set('error_log', (string) $prevErrorLog);
$pdo->exec('DROP TABLE IF EXISTS auto_migrate_probe');
$pdo->exec("DELETE FROM schema_migrations WHERE version IN ('990_probe', '995_broken')");
foreach ([...glob($migDir . '/*'), $marker, $errorLog] as $file) {
    @unlink($file);
}
@rmdir($migDir);
@rmdir(dirname($marker));
@rmdir($tmp);
check(!is_dir($tmp) && $versions() === $realVersions, '임시 파일·테이블 정리');

// ── 결과 ──────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 40) . "\n";
echo sprintf("통과 %d · 실패 %d\n", $passed, count($failed));
foreach ($failed as $name) {
    echo "  ✗ $name\n";
}
exit($failed === [] ? 0 : 1);
