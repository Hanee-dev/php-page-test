# 느린 우체통

지금 쓴 편지를 정해 둔 날(최소 30일, 최대 10년 뒤) 아침 메일함으로 보내 주는 서비스.
PHP 8.x(프레임워크 없음) · MariaDB 10.x · Cafe24 공유 호스팅 · GitHub Actions FTP 배포

기획·조사·설계 문서는 [`docs/`](docs/)에 있다.

## 현재 범위

| 기능 | 상태 |
|---|---|
| 편지 쓰기 → 확인 링크 → 봉인 → 밤하늘의 별 | 구현 |
| 제목·본문·이메일 암호화, 요청 제한, CSRF·XSS 방어 | 구현 |
| 메일 발송 | **인터페이스까지만** (`Mailer`). 실제 발송 드라이버 없음 → 확인 링크를 화면에 대신 표시 |
| 발송 스케줄러 | **목업** (`Scheduler` + `MockScheduler`). 발송 로직(`Dispatcher`)은 구현·테스트 완료, 주기 실행 트리거는 나중에 |

## 폴더 구조

```
deploy/              ← 배포 대상. 이 폴더의 내용이 Cafe24 FTP 최상위(/)에 그대로 올라감
  config/            config.php(기본값) + config.local.php(비밀값, git 제외 — 배포 시 자동 생성)
  migrations/        NNN_*.sql 스키마 (적용된 파일은 수정 금지, 새 번호로 추가)
  bin/               CLI: migrate.php, dispatch.php(발송 로직 수동 실행 — 개발·검증용)
  src/               앱 코드 (SlowMail\ 네임스페이스)
    Mail/            Mailer 인터페이스, LogMailer, NullMailer
    Scheduler/       Scheduler 인터페이스, MockScheduler
  templates/         layout, pages, mail
  storage/           런타임 파일(캐시, 로컬 메일) — 서버에서 자동 생성
  www/               웹 루트 — index.php(프런트 컨트롤러), assets
deploy-tools/        배포 보조 스크립트 (서버에 올라가지 않음)
dev/, docker-compose.yml   로컬 개발 환경
tests/               run.php(자동 테스트), e2e.sh(화면 흐름)
docs/                기획·조사·설계 문서
```

웹 루트 밖(`config/`, `src/`, `bin/` …)은 웹에서 접근할 수 없다. 각 폴더의 `.htaccess`(Require all denied)는 실수로 `www/` 안에 올렸을 때를 대비한 이중 차단이다.

## 로컬 개발

```bash
cp deploy/config/config.local.example.php deploy/config/config.local.php
# crypto.keys[1], crypto.hmac_key 에 `openssl rand -base64 32` 값을 각각 넣는다

docker compose up -d                                        # http://localhost:8080
docker compose exec web php /var/www/app/bin/migrate.php    # 스키마 생성 (생략해도 첫 요청 때 자동 적용)

docker compose exec web php /var/www/tests/run.php          # 자동 테스트 (slowmail_test DB 사용)
docker compose exec web sh /var/www/tests/e2e.sh            # 화면 흐름 확인

# 미래 시각인 것처럼 발송 로직 실행 → deploy/storage/mail/*.eml 로 결과 확인
docker compose exec web php /var/www/app/bin/dispatch.php --now="2027-10-14 09:00"
```

Windows Git Bash 에서는 `MSYS_NO_PATHCONV=1` 을 앞에 붙인다 (컨테이너 경로가 변환되는 문제).
DB 볼륨을 지우고 새로 만들었다면 `deploy/storage/cache/schema_version` 도 지운다 (남아 있으면 자동 마이그레이션이 이미 적용된 줄 안다).

## 배포 (GitHub Actions → Cafe24)

`main` 에 push 되면 `.github/workflows/deploy.yml` 이 다음 순서로 실행된다.

1. Secrets 로 `deploy/config/config.local.php` 생성 (`deploy-tools/make-config.php`) — 하나라도 비면 배포 중단
2. PHP 문법 검사
3. FTP 동기화 (변경된 파일만, 이전에 올렸다가 삭제한 파일은 서버에서도 삭제)
4. `POST /index.php?r=ops/migrate` (X-Ops-Token 헤더) 보조 호출 — **대개 403 (실패해도 배포는 성공 처리)**.
   실제 적용은 배포 후 첫 웹 요청 때 앱이 자동으로 한다 (`AutoMigrator`)

### DB 마이그레이션

Cafe24 는 **해외 IP 의 웹 접근을 차단**한다. GitHub Actions 러너는 해외 IP 라서 4번 호출은 대개 Apache 403 으로 끝난다.
그래서 앱이 요청마다 `migrations/` 의 마지막 파일 이름과 `storage/cache/schema_version` 을 비교하고,
다르면 그 요청에서 아직 적용되지 않은 마이그레이션을 적용한 뒤 기록한다(평소에는 파일 하나만 읽고 DB 에 접속하지 않음).
실패하면 서버 error_log 에 `[slowmail] 자동 마이그레이션 실패` 를 남기고 다음 요청에서 다시 시도한다.
끄려면 `config.php` 의 `app.auto_migrate` 를 `false` 로 둔다.

수동으로 실행하려면 **한국 IP** 에서:

```bash
curl -d "" -H "X-Ops-Token: …" https://hanee.shop/index.php?r=ops/migrate
# → {"ok":true,"applied":[...]}
```

`-d ""` 가 없으면(Content-Length 없는 POST) Cafe24 앞단 프록시가 404 를 돌려준다.
긴 `ALTER` 처럼 오래 걸리는 스키마 변경은 첫 요청을 붙잡으므로 피하거나 위 방법으로 따로 실행한다.

저장소 Settings → Environments → **production** 에 등록:

| 종류 | 이름 | 값 |
|---|---|---|
| Secret | `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` | Cafe24 FTP 접속 정보 |
| Secret | `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Cafe24 MariaDB 접속 정보 |
| Secret (선택) | `DB_HOST` | 기본 `localhost` |
| Secret | `CRYPTO_KEY_1` | `openssl rand -base64 32` — **원본을 비밀번호 관리자에도 보관** (잃으면 편지 복구 불가) |
| Secret | `HMAC_KEY` | `openssl rand -base64 32` — 위와 같이 보관 |
| Secret | `OPS_TOKEN` | `openssl rand -hex 32` |
| Variable | `APP_URL` | 사이트 주소 (예: `https://hanee.shop`) |
| Variable (선택) | `FTP_PROTOCOL` | `ftp`(기본) / `ftps` — 설정 파일에 DB 비밀번호가 담기므로 지원되면 `ftps` 권장 |
| Variable (선택) | `FTP_SERVER_DIR` | 업로드 위치(절대 경로), 기본 `/` (끝에 `/`) |

- 동기화 상태는 서버의 `/.ftp-deploy-sync-state.json` 에 저장된다(웹 루트 밖).
- Cafe24 FTP 는 로그인 시 `/www` 에서 시작하므로 경로는 항상 절대 경로(`/...`)로 지정한다.
- Cafe24 의 FTP 국내 IP 제한이 켜져 있으면 GitHub Actions(해외 IP)에서 업로드가 거부된다.
- `FallbackResource` 가 동작하지 않으면 `config.php` 의 `app.pretty_urls` 를 `false` 로 바꾼다 (`/index.php?r=write` 형태).
