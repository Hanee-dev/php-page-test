# php-page-test

푸드하우스 소개 사이트 (PHP 8.x, 프레임워크 없음)

## 폴더 구조

```
deploy/            ← 배포 대상. 이 폴더의 내용을 Cafe24 FTP 루트에 그대로 업로드
  config/          사이트 설정 (메일 주소, 문의 폼 옵션)
  src/             공통 코드 (bootstrap, helpers)
  templates/
    layout/        header, footer
    pages/         페이지별 본문
  www/             웹 루트 — 외부에서 접근 가능한 파일만 둠
    index.php      페이지 라우팅 진입점
    send_mail.php  문의 폼 처리
    assets/        css, js, img
README.md, .gitignore 등   개발용 (배포하지 않음)
```

## 배포 (Cafe24)

- `deploy/` 안의 `config/`, `src/`, `templates/`, `www/`를 FTP 루트에 업로드합니다.
  `config/`, `src/`, `templates/`는 웹 루트(`www/`) 바깥에 있으므로 웹에서 접근할 수 없습니다.
- 각 폴더의 `.htaccess`(Require all denied)는 실수로 `www/` 안에 올렸을 때를 대비한 이중 차단용입니다.
- `config/config.php`의 `mail.from`은 Cafe24에 등록된 도메인 메일 주소로 설정합니다.

## 로컬 실행

```
php -S localhost:8000 -t deploy/www
```
