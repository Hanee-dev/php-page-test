# 00. 환경 확인 결과

> 1단계 결과물 · 작성일 2026-10-14
> 출처: 저장소 분석(`.github/workflows/deploy.yml`, `README.md`, `deploy/**`)과 사용자 답변

## 확인된 환경

| 항목 | 값 | 근거 |
|---|---|---|
| PHP | 8.x, 프레임워크 없음 | README, 코드의 `declare(strict_types=1)`, `mixed` 타입 |
| 웹서버 | Apache (Cafe24 공유 호스팅) | `.htaccess`의 `Require all denied` |
| DB | Cafe24 제공 MariaDB/MySQL — **생성·연결 완료**(버전 미확인) | 사용자 답변 (2026-10-14) |
| CI/CD | GitHub Actions에서 `SamKirkland/FTP-Deploy-Action@v4.3.5`로 `deploy/`를 FTP `/`에 동기화 | `deploy.yml` |
| 디렉터리 구조 | `config/`, `src/`, `templates/`는 웹 루트 밖, `www/`가 웹 루트 | README |
| 메일 발송 | **실제 발송은 PASS — `Mailer` 인터페이스 레벨까지만 구축** | 사용자 답변 (2026-10-14) |
| 도메인 | hanee.shop. 기존 푸드하우스 사이트를 **대체**함 | `config.php`, 사용자 답변 |
| SSH / cron | **미확인** | 사용자 확인 필요 |
| HTTPS | **미확인** | 사용자 확인 필요 |

## 기존 구조에서 이어 쓸 것

- `deploy/src/helpers.php`: `e()`, `csrf_token()`, `csrf_verify()`, `redirect()`
- `deploy/src/bootstrap.php`: 세션 쿠키 설정 `httponly`, `samesite=Lax`, HTTPS일 때 `secure`
- `config.php`에 기본값을 두고, 민감한 값은 `config.local.php`(git 제외)로 분리하는 규칙
- 배포 파이프라인: PHP 문법 검사 후 FTP 동기화. 이 구조는 그대로 유지하고 단계만 추가한다.

## 환경 때문에 정한 설계 방향

1. **메일 추상화**
   - `Mailer` 인터페이스와 `LogMailer`만 구현한다. `LogMailer`는 `storage/mail/`에 `.eml` 파일로 저장한다.
   - 실제 발송 드라이버(SMTP/API)는 나중에 구현체를 하나 추가하는 것으로 끝나게 한다.
   - 이메일 소유 확인 링크는 debug 모드에서만 화면에 보여 준다. 운영 전에 흐름을 검증하기 위한 용도다.
2. **스케줄러는 실행 방식과 분리한다**
   - 발송 로직은 `bin/dispatch.php`(CLI)에 둔다.
   - cron이 없는 경우에는 토큰으로 보호한 HTTPS 엔드포인트로 같은 로직을 감싸고, GitHub Actions `schedule`이나 외부 크론 서비스가 이 엔드포인트를 호출하게 한다.
3. **마이그레이션**
   - `deploy/migrations/NNN_*.sql` 파일과 `schema_migrations` 테이블로 관리한다.
   - FTP만 쓸 수 있으면 배포가 끝난 뒤 Actions가 토큰을 붙인 `curl`로 마이그레이션을 실행한다.
4. **비밀값**
   - DB 비밀번호, 암호화 키, cron 토큰은 `config/config.local.php`에 둔다. 이 파일은 FTP로 한 번만 수동 업로드한다.
   - FTP-Deploy-Action은 자신이 올리지 않은 파일을 지우지 않는다(README에 기재된 동작). 따라서 배포해도 이 파일은 남는다.
5. **문서 위치**
   - 문서는 저장소 루트의 `docs/`에 둔다. 워크플로 `paths`가 `deploy/**`이므로 배포되지 않는다.

## 직접 해 주셔야 하는 작업

- [ ] Cafe24 관리 페이지에서 **호스팅 상품명**, **SSH 접속 가능 여부**, **크론 등록 가능 여부** 확인
- [ ] `phpinfo()`로 PHP 버전과 `sodium`, `pdo_mysql`, `mbstring` 확장 활성화 여부 확인
  - 확인이 끝나면 `phpinfo()` 파일은 반드시 삭제해 주세요.
- [ ] MariaDB/MySQL 버전 확인, DB 생성
  - 접속 정보는 채팅에 붙여 넣지 말고, 나중에 `config.local.php`에 직접 넣어 주세요.
- [ ] hanee.shop의 **HTTPS(SSL 인증서) 적용 여부** 확인

## 결정 기록

- 2026-10-14: 디자인 방향 **C. 「밤의 우체국」** 선택(`02-design-directions.md`)
- 2026-10-14: 메일 발송은 인터페이스 레벨까지만 구축하기로 함. DB는 사용자가 생성·연결 완료

## 발견한 문제

- `.gitignore`의 `config/config.local.php` 패턴은 저장소 루트 기준이라 실제 위치인 `deploy/config/config.local.php`를 **무시하지 못한다**.
  - 확인 방법: `git check-ignore`가 매칭 없이 exit 1을 반환한다.
  - 위험: 이대로 두면 비밀값이 커밋될 수 있다.
  - 조치: 5단계 첫 커밋에서 패턴을 `deploy/config/config.local.php`로 고친다.
