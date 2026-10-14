# 작업 지시서: 배포 후 DB 마이그레이션을 서버가 스스로 적용하게 바꾸기

> 작성 2026-10-14 · 브랜치 `feature/auto-migrate` (origin/main `b8c60af` 기준)
> 작업 위치: `C:\Users\GOLFZONCLOUD\orca\workspaces\php-page-test\auto-migrate` (이 워크트리 안에서만 작업)

## 1. 배경 — 무엇이 문제인가

느린 우체통은 Cafe24 공유 호스팅(FTP만 가능, SSH 없음)에 GitHub Actions로 배포한다.
`.github/workflows/deploy.yml`의 마지막 단계 "DB 마이그레이션"은 배포 직후 아래 엔드포인트를 호출한다.

```
POST {APP_URL}/index.php?r=ops/migrate   (헤더 X-Ops-Token)
```

첫 운영 배포에서 이 단계가 **Apache 403 Forbidden**으로 실패했다. 조사 결과는 다음과 같다.

| 호출 위치 | 결과 | 해석 |
|---|---|---|
| GitHub Actions(해외 IP), 바디 없는 POST | Apache **403** | **Cafe24가 해외 IP의 웹 접근을 차단**한다 |
| 한국 IP, 바디 없는 POST(`curl -X POST`) | openresty **404** | Cafe24 앞단 프록시가 Content-Length 없는 POST를 거부한다 |
| 한국 IP, `curl -d "" -H "X-Ops-Token: …"` | **200** `{"ok":true,"applied":["001_init"]}` | 정상 (사용자가 직접 실행해 운영 DB 복구 완료) |

결론: **Actions에서 서버로 HTTP를 호출하는 방식으로는 마이그레이션을 할 수 없다.**
지금 상태로는 배포할 때마다 이 단계가 빨간 X로 끝난다. 새 마이그레이션 파일이 생기면 누군가 한국에서 직접 curl을 실행해야 한다.

운영 DB는 현재 `001_init`이 적용된 상태다(`schema_migrations`에 기록됨).

## 2. 목표

1. **배포 후 첫 웹 요청 때 앱이 아직 적용되지 않은 마이그레이션을 스스로 적용한다.**
   - 평소 요청에는 DB 접근 없이 캐시 파일 하나만 확인한다.
2. Actions의 마이그레이션 호출은 **보조 수단**으로 남긴다. `-d ''`를 붙이고, 실패해도 배포를 실패로 만들지 않는다.
3. 테스트와 문서를 함께 갱신한다.

## 3. 구현 요구사항

### 3-1. `deploy/src/AutoMigrator.php` (신규)
- 생성자: `__construct(Migrator $migrator, string $migrationsDir, string $markerFile)`
  - 테스트에서 경로를 바꿔 끼울 수 있게 한다.
- `ensure(): void`
  1. `$migrationsDir/*.sql`을 정렬해 **가장 마지막 파일의 basename**(예: `001_init`)을 구한다. 파일이 없으면 아무것도 하지 않는다.
  2. `$markerFile` 내용이 그 값과 같으면 **즉시 반환한다. DB에 접근하지 않는다.**
  3. 다르거나 파일이 없으면 `Migrator::run()`을 호출한다.
     - `Migrator`는 이미 `GET_LOCK('slowmail_migrate', 10)`으로 동시 실행을 막는다.
     - 성공하면 marker 파일에 최신 버전을 쓴다(`LOCK_EX`, 폴더가 없으면 `mkdir`).
  4. 예외가 나면 `error_log('[slowmail] 자동 마이그레이션 실패: …')`만 남기고 **삼킨다**. marker는 쓰지 않는다. 요청 처리는 계속한다.
  5. marker 파일을 쓸 수 없어도(권한 문제) 요청은 정상 처리한다. 이 경우 매 요청마다 `Migrator::run()`이 돌게 되는데, 이미 적용된 마이그레이션이면 가볍게 끝나므로 허용한다.
- marker 기본 위치: `BASE_PATH . '/storage/cache/schema_version'`
  - `storage/`는 웹 루트 밖이고 `.htaccess`로 차단되어 있으며 git과 배포 대상에서 제외된다.

### 3-2. 연결
- `deploy/config/config.php`의 `app`에 `'auto_migrate' => true`를 추가하고 주석을 단다.
- `deploy/src/Services.php`에 `autoMigrator(): AutoMigrator` getter를 추가한다. 기존 lazy 패턴(`$this->made[__FUNCTION__] ??=`)을 따른다.
- `deploy/www/index.php`에서 라우팅 직전에 `cfg('app.auto_migrate')`가 참이면 `ensure()`를 호출한다.
  - 정적 파일(assets)은 Apache가 직접 서빙하므로 PHP를 거치지 않는다. 별도 처리는 필요 없다.
  - DB 접속 실패 같은 예외가 `ensure()` 밖으로 새면 안 된다.
- `Controller::opsMigrate()`(엔드포인트)는 그대로 둔다. 수동 실행과 보조 경로로 쓴다.

### 3-3. `.github/workflows/deploy.yml`
- "DB 마이그레이션" 단계:
  - `continue-on-error: true`를 추가한다.
  - curl에 `-d ''`를 추가한다(Content-Length 없는 POST는 Cafe24 앞단에서 404).
  - 주석으로 "Cafe24가 해외 IP 웹 접근을 차단하므로 대개 403 — 실제 적용은 첫 웹 요청 때 앱이 수행(AutoMigrator)"이라고 이유를 남긴다.
- 다른 단계는 건드리지 않는다.

### 3-4. 테스트 (`tests/run.php`에 섹션 추가)
임시 디렉터리(`sys_get_temp_dir()`)에 마이그레이션 폴더와 marker 경로를 따로 만들어 검증한다.
1. 새 DB 상태에서 `ensure()` → `schema_migrations`에 반영되고 marker에 최신 버전이 써진다.
2. 다시 `ensure()` → DB 접근이 없다.
   - 검증 방법: 접속 정보가 틀린 `Migrator`(또는 호출되면 예외를 던지는 대역)를 넣어도 예외 없이 반환하는지 확인한다.
3. 임시 폴더에 `00x_probe.sql`(`CREATE TABLE auto_migrate_probe (id INT)`)을 추가 → `ensure()`가 그 파일만 적용하고 marker를 갱신한다.
4. 마이그레이션 SQL이 실패해도 `ensure()`는 예외를 던지지 않고, marker는 갱신되지 않는다.

테스트가 만든 테이블이나 파일은 테스트 끝에 정리한다. 기존 55개 테스트는 계속 통과해야 한다.

### 3-5. 문서
- `README.md`의 "배포" 절:
  - 4번 단계 설명을 "보조 호출(대개 403) — 실제 적용은 배포 후 첫 웹 요청 때 자동"으로 바꾼다.
  - Cafe24가 해외 IP 웹 접근을 차단한다는 사실을 적는다.
  - 수동 실행 방법을 적는다. 한국 IP에서 다음을 실행한다.
    ```
    curl -d "" -H "X-Ops-Token: …" https://hanee.shop/index.php?r=ops/migrate
    ```
- `docs/00-environment.md` "결정 기록"에 2026-10-14 항목을 추가한다(해외 IP 차단 확인 → 자동 마이그레이션으로 전환).
- `docs/03-architecture.md` §7에 한 줄 추가한다. 이 방식에서 **긴 ALTER는 첫 요청을 붙잡을 수 있으니** 무거운 스키마 변경은 피하거나 별도로 수동 실행한다는 주의사항이다.

## 4. 코드 규칙 (기존 코드와 맞출 것)
- PHP **8.0 호환**만 쓴다. Cafe24 PHP 버전이 8.x 중 무엇인지 미확인이라 enum, readonly, `never` 같은 8.1+ 기능은 쓰지 않는다.
- 파일 머리에 `declare(strict_types=1);`를 두고, 네임스페이스는 `SlowMail`, 주석은 한국어로 쓴다. 주석 밀도는 기존 파일 수준에 맞춘다.
- 줄바꿈은 LF로 한다. `.gitattributes`가 있다.
- 시각은 `Clock`을 쓴다(이번 작업에는 거의 필요 없음).

## 5. 로컬 개발·검증 환경
- 다른 워크트리(`…/php-page-test/slow-mail`)에서 docker compose 스택이 8080/3307 포트로 떠 있다. **먼저 내린다.**
  ```bash
  cd C:/Users/GOLFZONCLOUD/orca/workspaces/php-page-test/slow-mail && docker compose down
  ```
- 이 워크트리에서 올린다.
  ```bash
  cp deploy/config/config.local.example.php deploy/config/config.local.php
  # crypto.keys[1], crypto.hmac_key 에 `openssl rand -base64 32` 결과를 각각 넣는다 (git 제외 파일)
  docker compose up -d --build
  ```
- Git Bash에서 docker exec를 쓸 때는 반드시 `MSYS_NO_PATHCONV=1`을 붙인다. 붙이지 않으면 `/var/www/...` 경로가 Windows 경로로 바뀐다.
  ```bash
  MSYS_NO_PATHCONV=1 docker compose exec -T web php /var/www/tests/run.php
  MSYS_NO_PATHCONV=1 docker compose exec -T web sh /var/www/tests/e2e.sh
  ```
- **자동 마이그레이션 실측**: 새 DB 볼륨(이 워크트리는 compose 프로젝트명이 달라 볼륨도 새로 생김)에서 **`bin/migrate.php`를 실행하지 않고** `curl http://localhost:8080/`이 200이 나오는지 확인한다.
  - 예전에는 테이블이 없어서 500이 났다.
  - 그다음 `storage/cache/schema_version`이 생겼는지 확인한다.
- e2e.sh를 다시 돌리기 전에는 요청 제한 기록을 비운다.
  ```bash
  docker compose exec -T db mariadb -uslowmail -pslowmail slowmail -e "TRUNCATE rate_limits"
  ```
- Windows의 curl로 한글 데이터를 보내면 인코딩이 깨진다. 한글이 들어간 HTTP 검증은 컨테이너 안에서 한다.
- 참고: Apache는 모르는 상태 코드(예: 419)를 500으로 바꾼다.

## 6. 커밋·푸시 규칙
- **작업 단위로 커밋**한다. 예:
  1. AutoMigrator와 연결
  2. 테스트
  3. 워크플로 변경
  4. 문서와 이 지시서
- 커밋 메시지는 한국어로 쓴다. 첫 줄은 요약, 본문에는 이유를 적는다. 마지막 줄은 아래와 같다.
  ```
  Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
  ```
- 테스트 55개 이상과 e2e가 모두 통과한 뒤 `git push -u origin feature/auto-migrate`를 실행한다.
- **main에 push하거나 merge하지 않는다.** main에 들어가면 운영 배포가 바로 실행된다.
- PR은 만들지 않는다. 이 PC의 `gh` 계정(`juhan-golfzon`)은 저장소 협업자가 아니라 실패한다. 대신 완료 보고에 아래 URL을 적는다.
  ```
  https://github.com/Hanee-dev/php-page-test/compare/main...feature/auto-migrate?expand=1
  ```
- `deploy/config/config.local.php`는 절대 커밋하지 않는다(.gitignore 대상).

## 7. 완료 보고에 포함할 것
- 커밋 목록
- 테스트와 e2e 결과(통과 수)
- 자동 마이그레이션 실측 결과
- 바꾼 파일 목록
- 남은 위험이나 주의사항
- PR 생성 URL
