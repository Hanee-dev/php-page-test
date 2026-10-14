#!/bin/sh
# 화면 흐름 E2E 확인 (debug=true 인 로컬 환경 전용)
#   docker compose exec web sh /var/www/tests/e2e.sh
# 작성 → 확인 대기 → 확인 링크 → 봉인 → 재클릭까지 HTTP 로 따라간다.
set -u
B=${BASE_URL:-http://localhost}
J=$(mktemp)
fails=0

ok()   { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; fails=$((fails + 1)); }
expect() { if [ "$2" = "$3" ]; then ok "$1"; else fail "$1 (기대 $3, 실제 $2)"; fi; }
has()    { if printf '%s' "$2" | grep -q "$3"; then ok "$1"; else fail "$1"; fi; }

csrf() { printf '%s' "$1" | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"$//'; }
code() { curl -s -o /dev/null -w '%{http_code}' -c "$J" -b "$J" "$@"; }

echo "공통"
H=$(curl -s -D - -o /dev/null "$B/")
has "CSP 헤더" "$H" "Content-Security-Policy"
has "Referrer-Policy same-origin" "$H" "Referrer-Policy: same-origin"
expect "랜딩 200" "$(code "$B/")" 200
expect "없는 경로 404" "$(code "$B/nope")" 404
expect "pretty_urls 대체 경로(?r=)" "$(code "$B/index.php?r=about")" 200

echo "작성"
FORM=$(curl -s -c "$J" -b "$J" "$B/write")
T=$(csrf "$FORM")
has "프리셋 6개(5 + 직접 고르기)" "$(printf '%s' "$FORM" | grep -c 'name="preset"')" "^6$"
expect "CSRF 불일치 403" "$(code -X POST "$B/write" --data-urlencode "csrf_token=bad" --data-urlencode "title=t" --data-urlencode "body=b" --data-urlencode "email=a@b.co" --data-urlencode "preset=1y")" 403
expect "허니팟 400" "$(code -X POST "$B/write" --data-urlencode "csrf_token=$T" --data-urlencode "website=spam" --data-urlencode "title=t" --data-urlencode "body=b" --data-urlencode "email=a@b.co" --data-urlencode "preset=1y")" 400
expect "검증 오류 422" "$(code -X POST "$B/write" --data-urlencode "csrf_token=$T" --data-urlencode "title=" --data-urlencode "body=b" --data-urlencode "email=bad" --data-urlencode "preset=1y")" 422

XSS='<script>alert(1)</script>'
E=$(curl -s -c "$J" -b "$J" -X POST "$B/write" --data-urlencode "csrf_token=$T" --data-urlencode "title=" \
    --data-urlencode "body=$XSS" --data-urlencode "email=bad" --data-urlencode "preset=1y")
has "오류 화면에서 입력값 이스케이프(XSS)" "$E" "&lt;script&gt;alert(1)&lt;/script&gt;"
if printf '%s' "$E" | grep -q "$XSS"; then fail "스크립트 원문이 그대로 출력됨"; fi
R=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$J" -b "$J" -X POST "$B/write" \
    --data-urlencode "csrf_token=$T" --data-urlencode "title=첫 편지" --data-urlencode "body=$XSS 안녕, 미래의 나" \
    --data-urlencode "email=e2e-$(date +%s)@example.com" --data-urlencode "preset=1y")
has "정상 제출 → 303 /posted" "$R" "^303 .*/posted$"

POSTED=$(curl -s -c "$J" -b "$J" "$B/posted")
has "확인 대기 화면 + 이메일 마스킹" "$POSTED" "e\*\*\*@example.com"
# 링크 호스트는 app.url 기준이므로 토큰만 뽑아 이 환경 주소로 연다
TOK=$(printf '%s' "$POSTED" | grep -o 'verify?t=[0-9a-f]\{64\}' | head -1 | sed 's/.*t=//')
VURL="$B/verify?t=$TOK"
has "debug 모드 확인 링크 노출" "$TOK" "^[0-9a-f]\{64\}$"
expect "새로고침하면 플래시 소멸 → 303" "$(code "$B/posted")" 303

echo "봉인"
V=$(curl -s -c "$J" -b "$J" "$VURL")
has "GET /verify 는 확인 화면만" "$V" "이 편지를 봉인할까요?"
R=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$J" -b "$J" -X POST "$B/verify" \
    --data-urlencode "csrf_token=$(csrf "$V")" --data-urlencode "t=$TOK")
has "POST /verify → 303 /sealed" "$R" "^303 .*/sealed$"
S=$(curl -s -c "$J" -b "$J" "$B/sealed")
has "봉인 완료 화면" "$S" "봉인되었습니다"
has "남은 날 수 표시" "$S" "days__num"
has "링크 재클릭 → 이미 봉인" "$(curl -s -c "$J" -b "$J" "$VURL")" "이미 봉인된 편지예요"
expect "잘못된 토큰 404" "$(code "$B/verify?t=abc")" 404

echo "배포용 마이그레이션 엔드포인트"
OPS=${OPS_TOKEN:-local-ops-token}
expect "토큰 없으면 404" "$(code -X POST "$B/index.php?r=ops/migrate")" 404
expect "틀린 토큰 404" "$(code -X POST -H "X-Ops-Token: wrong" "$B/index.php?r=ops/migrate")" 404
expect "GET 은 404" "$(code -H "X-Ops-Token: $OPS" "$B/index.php?r=ops/migrate")" 404
M=$(curl -s -X POST -H "X-Ops-Token: $OPS" "$B/index.php?r=ops/migrate")
has "올바른 토큰 → 마이그레이션 실행(JSON)" "$M" '"ok":true'

echo "저장소 접근 차단(웹 루트 밖)"
expect "config 직접 접근 불가" "$(code "$B/../config/config.php")" 404

rm -f "$J"
echo
if [ "$fails" -eq 0 ]; then echo "E2E 모두 통과"; else echo "E2E 실패 $fails 건"; exit 1; fi
