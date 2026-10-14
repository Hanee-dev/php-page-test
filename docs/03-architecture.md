# 03. 화면 구성 · 흐름 · DB 스키마

> 4단계 결과물 · 2026-10-14
> 전제: 디자인 방향 C 「밤의 우체국」, 실제 메일 발송 없이 `Mailer` 인터페이스까지만 구축, Cafe24 MariaDB 연결 완료

---

## 1. 서비스 규칙 (확정 제안)

| 규칙 | 값 | 근거 |
|---|---|---|
| 기준 시간대 | **Asia/Seoul**. DB에는 **UTC**로 저장 | 서버와 DB의 시간대 설정에 영향받지 않게 한다 |
| 도착 시각 | 선택한 날짜 **오전 9시(KST)** | 아침 메일함에서 발견하게 한다. 사용자는 시각을 고르지 않는다(느림) |
| 최소 기간 | 오늘(KST) + **30일** | Dear Me의 최소 30일. 한 달 미만은 리마인더 영역이다 (`01-references.md`) |
| 최대 기간 | **10년** | FutureMe 프리셋의 최대치. 너무 먼 미래는 운영 보장이 어렵다 |
| 프리셋 | 100일 · 6개월 · **1년(기본)** · 3년 · 5년 · 직접 고르기 | 오프라인 느린 우체통의 기본 단위가 1년이다 |
| 제목 / 본문 길이 | 100자 / 10,000자 | 편지 한두 장 분량 |
| 확인 링크 유효기간 | **48시간** | 만료되면 다시 써야 한다. 미확인 편지는 7일 뒤 삭제 |
| 봉인 후 | 본문 열람 불가, 수정 불가 | LaterMe의 봉인 의식. 취소와 수정은 확장 단계에서 결정 |
| 받는 사람 | MVP는 **이메일 한 개**. 확인 메일도 같은 주소로 간다 | "다른 사람에게 보내기"(보내는 사람 이메일 분리)는 확장 단계. 스키마는 미리 대비한다 |

### 요청 제한 (스팸 방지)
| 대상 | 제한 |
|---|---|
| 같은 IP의 편지 제출 | 시간당 5통, 하루 20통 |
| 같은 받는 사람 이메일 | 미확인 편지 동시 3통, 하루 10통 |
| 확인 링크 요청(GET /verify) | IP당 분당 30회 |
| 허니팟 필드 | 기존 `send_mail.php`의 `website` 필드 방식을 재사용 |

IP와 이메일은 **HMAC 해시로만** 저장해서 원문이 남지 않게 한다.

---

## 2. 사이트맵

```
/                 랜딩 — 밤하늘 + "지금 N통의 편지가 기다리고 있어요" + [편지 쓰기]
/write            편지 쓰기 (GET 폼 · POST 제출)
/posted           봉투가 떠오른 뒤 "확인 메일을 보냈어요" (세션 플래시로만 진입)
/verify?t=…       확인 링크 → 봉인 처리 → /sealed 로 이동
/sealed           봉투가 별이 되는 연출 + 도착 날짜 + 남은 날수 (세션 플래시로만 진입)
/about            "편지는 이렇게 보관됩니다" + FAQ
/privacy          개인정보처리방침 (이메일을 수집하므로 공개 필요)
/cron/dispatch    (6단계) 토큰으로 보호한 발송 트리거. cron이 없을 때만 쓴다
```

- **라우팅**: `www/.htaccess`에 `FallbackResource /index.php`(Apache mod_dir)를 두고, 프런트 컨트롤러 하나로 처리한다.
  - 메일 안의 링크는 수년 뒤에도 열려야 하므로 **URL 구조는 한 번 정하면 바꾸지 않는다**.
  - Cafe24에서 `FallbackResource`가 동작하지 않으면 `index.php?r=verify` 형태로 바꿔 쓸 수 있게 라우터를 만든다.
- 기존 푸드하우스 페이지(`templates/pages/*`, `send_mail.php`)는 삭제한다. FTP-Deploy-Action이 동기화 기록을 근거로 서버에서도 지운다.

---

## 3. 주요 화면 흐름

```
[랜딩 /] ──편지 쓰기──▶ [작성 /write]
                          │ 제목·본문·받는 사람 이메일·도착일(프리셋)
                          │ [봉인하기] 클릭
                          ▼
                  (클라이언트 연출) 편지지 3단 접기 → 봉투에 들어감 → 봉투가 위로 떠오름
                          │ POST /write  (CSRF·허니팟·요청 제한·검증)
                          ├─ 실패 → /write 로 되돌아감(입력값 유지, 오류 표시)
                          ▼
                  [확인 대기 /posted]  "48시간 안에 메일의 링크를 눌러야 별이 됩니다"
                          │   ※ 메일 PASS 기간에는 아래 '미리보기 모드' 참고
                          ▼
                  메일의 링크 → GET /verify?t=토큰  (확인 화면만 — 메일 보안 스캐너의 링크 선열람으로 봉인되지 않게)
                          ├─ 만료/무효 → 안내 화면("다시 써 주세요")
                          │ [봉인하고 별로 띄우기] → POST /verify (CSRF)
                          ▼
                  [봉인 완료 /sealed]  봉투가 작은 별이 되어 하늘에 자리 잡음
                                       "2027년 10월 14일 아침, 이 편지가 도착합니다 · 365일"
                          ⋮ (수년)
                  스케줄러: deliver_at ≤ now 인 sealed 편지 → Mailer.send → delivered
```

### 메일 PASS 기간의 '미리보기 모드'
- 실제 메일이 나가지 않으므로 확인 링크를 받을 방법이 없다. 그래서 config의 `mail.driver = 'log'`일 때는 두 가지를 한다.
  1. 메일 내용을 `storage/mail/*.eml`로 저장한다.
  2. `/posted` 화면에 **"[개발용] 확인 링크 열기"**를 보여 준다.
- 운영에서 이 링크가 보이면 누구나 남의 이메일로 편지를 봉인할 수 있다. 그래서 `app.debug = true`일 때만 보이게 하고, 운영 config에서는 끈다. 사용자에게 확인할 항목 ①

### 화면별 C 컨셉 요소
| 화면 | 요소 |
|---|---|
| 공통 | 배경 `#14182B`. 정적 별 점(CSS radial-gradient). 별 애니메이션은 반짝임만 주고, reduced-motion이면 정지 |
| 랜딩 | 별 개수는 `sealed` 편지 수를 반영한다. 화면에는 최대 200개만 그리고, 숫자는 문구로 보여 준다 |
| 작성 | 램프 빛을 받은 편지지 카드 `#F3E9D2`, 본문 글자 `#2A2622`. 긴 글의 가독성은 카드가 책임진다 |
| 봉인 완료 | 별의 위치는 `public_id` 해시로 정한다. 같은 편지는 항상 같은 자리에 뜬다(저장 컬럼 불필요) |

> "기다리는 편지 수"는 원래 확장 기능(통계)이었다. 하지만 C 컨셉의 핵심 장치라서 **COUNT 하나만 MVP에 넣는다**.
> 질의는 `status='sealed'` 인덱스로 처리하고, 결과를 5분간 파일 캐시에 둔다.

---

## 4. 코드 구조 (5단계 구현 대상)

```
deploy/
  config/config.php            기본값(비밀값 없음)
  config/config.local.php      (git 제외, FTP 수동 업로드) DB 접속 정보, 암호화 키, cron 토큰, debug
  migrations/001_init.sql …    스키마
  bin/migrate.php              CLI 마이그레이션
  bin/dispatch.php             CLI 발송 (6단계)
  src/
    bootstrap.php, helpers.php (기존 확장)
    Db.php                     PDO 래퍼(ERRMODE_EXCEPTION, EMULATE_PREPARES=false, utf8mb4, time_zone '+00:00')
    Clock.php                  현재 시각 주입(테스트에서 미래로 이동)
    Crypto.php                 본문·이메일 암호화, HMAC
    RateLimiter.php
    LetterRepository.php       상태 전이를 모두 여기서 처리
    Mail/Mailer.php            interface
    Mail/Message.php           값 객체 (to, subject, text, html)
    Mail/LogMailer.php         storage/mail/*.eml 로 저장
    Mail/NullMailer.php        아무것도 하지 않음(테스트용)
  storage/                     (웹 루트 밖, .htaccess deny) mail/, cache/, logs/
  templates/ layout/, pages/, mail/(verify.txt.php, deliver.txt.php …)
  www/ index.php, .htaccess, assets/
```

### Mailer 인터페이스 (이번 범위)
```php
interface Mailer
{
    /** 성공 시 메시지 ID, 실패 시 MailException */
    public function send(Message $message): string;
}
```
- 나중에 SMTP나 API 드라이버를 만들 때는 이 인터페이스의 구현체를 하나 추가한다. 그 뒤 `config['mail']['driver']`만 바꾸면 된다.
- 재시도, 백오프, 로그 기록은 발송 로직(디스패처)이 담당한다. 드라이버는 "한 번 보내기"만 책임진다.

---

## 5. DB 스키마

### 설계 원칙 (수년 운영을 고려)
- InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`
- MariaDB 10.x와 MySQL 5.7에서 모두 동작하는 문법만 쓴다. JSON 타입, CHECK 제약, `SKIP LOCKED`는 쓰지 않는다.
- **상태 값은 ENUM이 아니라 VARCHAR**로 둔다. 상태가 늘어날 때 ALTER가 필요 없다.
- 시각 컬럼은 모두 `DATETIME`(UTC)이다. `TIMESTAMP`는 2038년 한계가 있어 쓰지 않는다. 10년 뒤 발송이면 2036년이라 경계에 가깝다.
- 본문 형식이 바뀔 수 있으므로 `body_format`, 암호화 키 교체를 위해 `key_version`을 둔다.

### `letters`
| 컬럼 | 타입 | 설명 |
|---|---|---|
| id | BIGINT UNSIGNED AI PK | 내부 ID |
| public_id | CHAR(32) UNIQUE | `bin2hex(random_bytes(16))`. 외부 노출용 |
| recipient_email_enc | VARBINARY(512) | 암호화한 받는 사람 이메일 |
| recipient_email_hash | BINARY(32) | HMAC-SHA256(소문자 이메일). 요청 제한과 조회에 쓴다 |
| sender_email_enc | VARBINARY(512) NULL | (확장) 다른 사람에게 보내기 |
| sender_email_hash | BINARY(32) NULL | (확장) |
| title_enc | VARBINARY(1024) | 암호화한 제목 |
| body_enc | MEDIUMBLOB | 암호화한 본문 |
| body_format | VARCHAR(10) DEFAULT 'text' | 일반 텍스트. 향후 형식 확장 대비 |
| key_version | SMALLINT UNSIGNED | 암호화 키 버전 |
| deliver_on | DATE | 사용자가 고른 날짜(KST). 표시용 |
| deliver_at | DATETIME | 실제 발송 시각(UTC) = deliver_on 09:00 KST = 같은 날 00:00 UTC |
| status | VARCHAR(20) | 상태 값은 아래 상태 전이 참고 |
| verify_token_hash | BINARY(32) NULL UNIQUE | SHA-256(토큰). 원문은 메일에만 실린다 |
| verify_expires_at | DATETIME NULL | |
| sealed_at | DATETIME NULL | 이메일 확인 시각 |
| attempts | SMALLINT UNSIGNED DEFAULT 0 | 발송 시도 횟수 |
| next_attempt_at | DATETIME NULL | 재시도 예정 시각 |
| locked_by | CHAR(16) NULL / locked_at DATETIME NULL | 동시 실행 방지(아래 참고) |
| last_error | VARCHAR(500) NULL | |
| delivered_at | DATETIME NULL | |
| created_ip_hash | BINARY(32) | HMAC(IP) |
| created_at / updated_at | DATETIME | |

인덱스: `(status, deliver_at)`, `(recipient_email_hash, status)`, `(status, created_at)`

### 상태 전이
```
pending ──verify──▶ sealed ──claim──▶ sending ──ok──▶ delivered
   │                  ▲                  │
   │                  └─retry(백오프)────┤ 실패(시도 < 최대 횟수)
   │                                     └─실패(최대 횟수 도달)──▶ failed
   └─48시간 경과──▶ expired (7일 뒤 행 삭제)
```
- **claim**: `UPDATE letters SET status='sending', locked_by=?, locked_at=? WHERE status='sealed' AND deliver_at<=? AND (next_attempt_at IS NULL OR next_attempt_at<=?) ORDER BY deliver_at LIMIT 20`
  - 이어서 `locked_by`로 SELECT한다.
  - `SKIP LOCKED` 없이도 스케줄러 두 개가 동시에 돌 때 같은 편지를 두 번 보내지 않는다.
- `sending` 상태로 30분 넘게 남은 행은 다음 실행 때 `sealed`로 되돌린다. 프로세스가 중간에 죽은 경우를 대비한 처리다.
- 재시도 간격은 10분 → 1시간 → 6시간 → 24시간 → 72시간이다. 첫 시도와 재시도 5번(총 6번)이 모두 실패하면 `failed`가 되고 운영자가 확인한다.

### `mail_log` (발송 시도 기록, 확인 메일과 도착 메일 모두)
| 컬럼 | 타입 |
|---|---|
| id | BIGINT UNSIGNED AI PK |
| letter_id | BIGINT UNSIGNED, FK → letters.id ON DELETE CASCADE |
| kind | VARCHAR(10) — `verify` / `deliver` |
| driver | VARCHAR(20) — `log`, 향후 `smtp` 등 |
| success | TINYINT(1) |
| message_id | VARCHAR(255) NULL |
| error | VARCHAR(1000) NULL |
| created_at | DATETIME |

### `rate_limits`
| 컬럼 | 타입 |
|---|---|
| bucket | BINARY(32) — HMAC(동작 + IP 또는 이메일) |
| window_start | DATETIME |
| hits | INT UNSIGNED |
PK `(bucket, window_start)`. 스케줄러가 하루 지난 행을 지운다.

### `schema_migrations`
`version VARCHAR(50) PK`, `applied_at DATETIME`

---

## 6. 개인정보 저장 방식 — 선택지

| 안 | 내용 | 장점 | 단점 |
|---|---|---|---|
| ① 평문 | DB에 그대로 저장 | 가장 단순 | DB 덤프나 호스팅 사고 시 편지가 그대로 노출된다 |
| **② 앱 레벨 암호화 (추천)** | 제목, 본문, 이메일을 앱에서 암호화한다. 이메일은 HMAC 해시를 따로 둔다 | DB나 백업 파일만 유출되면 내용을 읽을 수 없다. 백업 파일을 다루기도 안전해진다 | **키를 잃으면 모든 편지를 복구할 수 없다.** 키 백업이 필수다 |
| ③ ② + 발송 후 파기 | ②에 더해, 발송하고 30일이 지나면 본문과 이메일을 삭제한다 | 보관 기간이 최소화된다 | 재발송이나 "다시 보기" 기능이 불가능해진다 |

- **추천은 ② + ③의 파기 정책**이다. 편지가 "도착하면 받은 사람의 메일함으로 넘어간다"는 개념과도 맞다.
- **암호화 방식**
  - 1순위는 `sodium_crypto_secretbox`(XSalsa20-Poly1305)다. PHP 7.2부터 번들되지만 확장이 켜져 있어야 한다.
  - Cafe24에서 `sodium`이 꺼져 있으면 `openssl_encrypt('aes-256-gcm')`으로 대체한다.
  - 저장 형식은 `버전 1바이트 + nonce + 암호문`이다. 두 방식이 섞여도 복호화할 수 있다.
- **키**: `config.local.php`의 `crypto.keys = [1 => base64키]`에 둔다. 키를 교체할 때는 새 버전을 추가하고 기존 키는 복호화용으로 남긴다.

---

## 7. 장기 운영 — 스키마 변경과 백업

- **스키마 변경**
  - 마이그레이션은 **추가 위주(additive)**로 한다. 컬럼을 추가할 때는 NULL 허용이나 기본값을 둔다.
  - 이름 변경이나 삭제는 "새 컬럼 추가 → 코드 전환 → 다음 배포에서 기존 컬럼 삭제" 두 단계로 나눈다.
  - 적용된 마이그레이션 파일은 수정하지 않는다.
  - 마이그레이션은 배포 후 첫 웹 요청 때 앱이 자동 적용한다(`AutoMigrator`). **긴 `ALTER`는 그 첫 요청을 붙잡을 수 있으므로**, 무거운 스키마 변경은 피하거나 한국 IP에서 `ops/migrate`를 호출해 별도로 수동 실행한다.
- **백업**
  - 매주 phpMyAdmin이나 Cafe24 백업 기능으로 SQL 덤프를 받는다(SSH가 되면 cron + `mysqldump`).
  - 덤프는 암호화된 상태이므로 클라우드 드라이브에 보관해도 된다.
  - **암호화 키는 비밀번호 관리자에 따로 보관한다.** 덤프와 같은 곳에 두지 않는다.
  - 분기마다 한 번은 로컬에서 덤프를 복원하고 복호화가 되는지 검증한다.
- **발송 보장**: 스케줄러가 며칠 멈췄다가 다시 돌아와도 `deliver_at <= now` 조건 덕분에 밀린 편지를 모두 보낸다. 늦게 도착할 뿐 유실되지는 않는다.

---

## 8. 발송 스케줄러 — 목업 (2026-10-14 결정)

- 주기 실행 트리거(cron·HTTP·외부 서비스)는 **이번 범위에서 제외**한다. 나중에 다른 방식으로 구현한다.
- 계약만 둔다: `SlowMail\Scheduler\Scheduler` 인터페이스(`name()`, `run($now): SchedulerResult`)
- 현재 구현은 `MockScheduler` 하나다. 호출만 기록하고 발송하지 않으며, DB도 건드리지 않는다.
- 선택은 `config['scheduler']['driver'] = 'mock'`으로 한다. 실제 구현체는 `Scheduler\Factory`에 추가한다.
- 발송, 재시도, 파기 로직(`Dispatcher`)은 구현과 테스트가 끝난 상태로 남겨 둔다.
  - 실제 스케줄러는 이 로직을 호출하기만 하면 된다.
  - 개발 중에는 `bin/dispatch.php --now=…`로 직접 실행해 검증한다.
- 영향: 스케줄러가 없으므로 운영에서는 편지가 **발송되지 않는다**. 만료된 미확인 편지 삭제, 발송 후 파기 같은 정리 작업도 돌지 않는다.
  - 개인정보처리방침의 보관 기간 문구는 실제 스케줄러를 붙일 때 다시 맞춘다.

---

## 직접 해 주셔야 하는 작업 (이번 단계)
- [ ] MariaDB 버전 알려 주기. phpMyAdmin 첫 화면이나 `SELECT VERSION();`으로 확인할 수 있습니다
- [ ] `phpinfo()`로 `sodium` 확장이 켜져 있는지 확인. 암호화 방식이 이 결과에 따라 결정됩니다
- [ ] DB 접속 정보는 5단계에서 드릴 `config.local.php` 템플릿에 직접 넣어 주세요
