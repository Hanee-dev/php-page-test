# 01. 디자인 레퍼런스 조사

> 2단계 결과물 · 조사일 2026-10-14
> 이 세션에서 WebFetch/WebSearch로 **직접 열어 본 내용만** 적었다. 확인하지 못한 항목은 "미확인"으로 표시했다.
> 레퍼런스는 영감을 얻는 데만 쓴다. 디자인, 로고, 일러스트, 문구는 복제하지 않는다.

---

## A. 같은 컨셉의 서비스

### 1. FutureMe — https://www.futureme.org/
- **확인한 내용**
  - 헤드라인은 "Write a letter to your future self", 설명 문구는 "Write. Pick a date. Send. Verify. That's it 😊"이다.
  - 본문 입력란이 "Dear FutureMe,"로 시작한다. 단어 수 표시와 "Inspire me!" 글감 버튼이 있다.
  - 발송 시점은 6개월, 1년, 3년, 5년, 10년 프리셋 중에서 고르거나 직접 날짜를 지정한다.
  - 공개 범위는 Private 또는 "Public, but anonymous" 중에서 고른다. 확인 이메일을 보내 수신 주소를 검증한다.
  - 문구 톤은 이모지를 섞은 친근하고 가벼운 말투다.
- **참고할 점**
  - 작성 → 날짜 → 확인 메일로 이어지는 3단계가 짧다.
  - 프리셋이 **6개월부터** 시작한다. "리마인더가 아니라 편지"라는 성격을 프리셋으로 드러낸다.
- **적용 방식**
  - 발송 시점 입력은 **기간 프리셋을 기본으로 하고, 직접 날짜 지정을 보조**로 둔다.
  - 이모지와 가벼운 말투는 쓰지 않는다. 우리는 *느림*을 강조하므로 차분한 존댓말 문장으로 차별화한다.
  - 공개 편지(익명)는 확장 기능 단계에서 이 구조를 참고한다.

### 2. FuturePost — https://futurepost.app/
- **확인한 내용**
  - 헤드라인은 "Send a Letter to Your Future Self"이다. "next month, next year, or even a decade from now"라는 예시를 든다.
  - 사이트 설명상 편지는 "encrypted and safe"이고, "Regular backups"를 한다. 발송 전에는 열람, 수정, 삭제가 가능하다고 적혀 있다.
  - 암호화 방식은 밝히지 않는다(미확인). 최소 기간도 명시되어 있지 않다.
- **참고할 점**
  - 장기 보관 서비스는 "신뢰"를 직접 말해 주어야 한다. 암호화, 백업, 제3자 미공유를 FAQ로 안내한다.
- **적용 방식**
  - 하단에 **"편지는 이렇게 보관됩니다"**라는 짧은 신뢰 섹션을 둔다. 4단계의 개인정보 설계를 그대로 쉬운 말로 옮긴다.
  - FAQ에는 "서비스가 사라지면?"이라는 질문을 넣는다. 백업과 운영 정책으로 답한다.

### 3. LaterMe (iOS) — https://hunted.space/product/laterme
- **확인한 내용**
  - 태그라인은 "Write a letter to your future self. Seal it. Forget it. Read it."이다.
  - 제작자는 "the act of sealing a letter to feel significant" 하길 원했다고 말한다. 그래서 봉인은 전체 화면 어두운 애니메이션, 햅틱, 왁스 실링으로 연출된다.
  - "Once it's sealed, you can't read it or edit it." 봉인 후에는 열람도 수정도 할 수 없다.
- **참고할 점**
  - **봉인을 의식(ritual)처럼** 만든다. 봉인이 끝나면 다시 볼 수 없다는 제약이 오히려 감성을 만든다.
- **적용 방식**
  - "봉인 완료" 화면을 서비스의 정점으로 설계한다.
  - 봉인 후에는 본문을 다시 보여 주지 않는다. 취소 정책은 확장 기능 단계에서 정한다.
  - 모바일에서는 `navigator.vibrate`를 지원하는 기기에서만 짧은 진동을 준다. 장식 효과일 뿐이고, 없어도 흐름에 문제가 없게 한다.

### 4. Dear Me – Letters to Self (iOS) — https://apps.apple.com/us/app/dear-me-letters-to-self/id6621263790
- **확인한 내용**
  - 편지를 봉투 디자인으로 감싸고 드래그 동작으로 보낸다.
  - 편지는 **"30 to 90 days"** 사이 무작위 시점에 도착한다. 나중에 날짜를 직접 정하는 "Special Day" 옵션이 추가되었다.
- **참고할 점**
  - 실제 서비스 가운데 **최소 30일 대기**를 기본으로 둔 사례다. 최소 기간을 정할 때 근거로 쓴다.
- **적용 방식**
  - 최소 발송 기간은 30일로 하자는 초기 의견을 유지한다. 근거는 아래 "조사에서 나온 시사점"에 정리했다.

### 5. Write A Letter – Wax & Paper (iOS) — https://mwm.ai/apps/write-a-letter-wax-paper/6791075364
- **확인한 내용**
  - "Fold your letter into an envelope you choose." 편지를 고른 봉투에 접어 넣고, 이니셜이 새겨진 왁스 실을 누른다.
  - 발송 시점은 1개월, 6개월, 1년 중에서 고른다.
  - 받는 사람은 비공개 링크로 봉투를 먼저 보고, 실을 깨서 편지를 연다.
- **참고할 점**
  - 보내는 쪽은 **접기 → 봉투 → 봉인**, 받는 쪽은 **봉투 → 실 깨기 → 편지** 순서다. 두 경험이 대칭을 이룬다.
- **적용 방식**
  - MVP에서는 보내는 쪽 연출(접기 → 봉투 → 봉인)만 만든다.
  - 도착 메일에서 "편지 열어보기" 링크를 누르면 개봉 연출이 나오는 웹 페이지는 확장 기능 후보로 둔다. 이렇게 하면 메일 본문에 편지 원문을 넣지 않을 수도 있어서 개인정보 측면에서도 이점이 있다.

## B. 오프라인 느린 우체통

### 6. 국내 최초 느린 우체통(영종대교 기념관)과 위키백과 — https://en.wikipedia.org/wiki/Slow_mailbox
- **확인한 내용**
  - "Delayed mailing service"로, 보통 약 1년 뒤에 배달한다.
  - 한국 최초의 느린 우체통은 2009년 영종대교 기념관에 설치되었다(Korea TV 인용).
- **참고할 점**
  - 원조 개념의 기본 단위가 **1년**이다.
- **적용 방식**
  - 발송 시점 프리셋의 기본 선택값을 **1년 뒤**로 둔다.

### 7. 강릉 경포해변 "추억의 느린 우체통" — https://m.go.seoul.co.kr/news/2014/12/05/20141205029011
- **확인한 내용**
  - 해변 중앙 통로에 "빨간색 대형 우체통"이 있고, 관광명소 사진이 새겨진 엽서를 준다. 엽서는 1년 뒤 원하는 주소로 배달된다.
  - 기사 시점까지 엽서 약 2만 5천 장이 모였다.
- **참고할 점**
  - 상징 요소는 **빨간 우체통**과 **그 장소가 담긴 엽서**다.
  - 기사로는 우체통의 형태나 안내 문구까지는 확인하지 못했다(미확인).
- **적용 방식**
  - 빨간색은 넓은 배경이 아니라 **우체통과 우표 같은 작은 포인트**에만 쓴다.
  - 편지지 상단에 "작성한 날, 작성한 곳"을 소인처럼 찍는 연출로 엽서의 '장소성'을 옮긴다. 위치 정보는 받지 않고, 날짜만 쓴다.

### 8. 부산 초량 "유치환의 우체통" — https://m.go.seoul.co.kr/news/2013/05/16/20130516014019
- **확인한 내용**
  - "추억과 그리움이 있는 빨간 우체통"이며, 넣은 편지는 6개월 뒤 배달된다(개소 당시 기준).
  - 건물은 최초의 사진기 '옵티큐라'를 닮았다. 2층에 편지 쓰기 공간이 있고, 시인의 작품과 연결된 장소다.
  - 참고로 다른 기사([주간경향 2015](https://weekly.khan.co.kr/article/201505121112141))는 이곳을 1년 배달 우체통 목록에 넣었다. 운영 기간이 바뀌었을 수 있다.
- **참고할 점**
  - 문구 톤은 "추억", "그리움", "시인의 길"처럼 **문학적이고 차분하다**.
  - 같은 기사에서 느린 우체통을 "초고속 사회"에 대한 반작용으로 설명한다(주간경향).
- **적용 방식**
  - 카피 원칙은 "빠르게, 쉽게" 대신 **"천천히, 기다려 주세요"**다. 짧은 서정 문장을 쓴다.
  - 예: "이 편지는 지금부터 천천히 걸어갑니다." 직접 쓴 문장이며 기사 문구를 인용한 것이 아니다.

## C. 감성 웹 디자인

### 9. Miranda – Paper Portfolio (Awwwards Site of the Day, 2021-11-18) — https://www.awwwards.com/sites/miranda-paper-portfolio
- **확인한 내용**
  - Awwwards에 등록된 팔레트는 `#000`과 `#987654`, 태그는 Typography, Illustration, Copy Design, GSAP, Three.js다.
  - "editorial aesthetic"과 책장에서 착안한 가로 슬라이더 내비게이션을 쓴다. 실제 사이트는 https://niccolomiranda.com
  - 실제 사이트의 종이 질감이나 폰트는 텍스트 추출로 확인할 수 없었다(미확인).
- **참고할 점**
  - 검정과 **바랜 갈색 한 가지**만으로 종이와 인쇄물 느낌을 낸다.
  - 큰 디스플레이 타이포를 짧게 끊어 쌓는 방식이다(예: "The / pixel / perfect / artisan").
- **적용 방식**
  - 색은 2~3개로 제한한다.
  - 랜딩 헤드라인은 큰 명조체를 짧게 끊어 세로로 쌓는다.
  - WebGL과 Three.js는 쓰지 않는다. 공유 호스팅과 모바일 성능을 고려해 CSS와 SVG로만 구현한다.

### 10. Minimalistic Portfolio (Awwwards Honorable Mention, 2025-09-15) — https://www.awwwards.com/sites/minimalistic-portfolio
- **확인한 내용**
  - 서체 하나, 크기 두 가지, 색 세 가지만 쓴다. "no visual clutter, just calm, smooth design"
  - 태그는 Clean, Minimal, Single page, Typography다.
- **참고할 점**
  - **제약으로 차분함을 만든다.** 장식을 더하는 대신 줄인다.
- **적용 방식**
  - UI 요소의 크기 단계를 최소화한다. 본문, 보조, 제목 3단계만 쓴다.
  - 편지 작성 화면에서는 입력란 테두리를 없애고 종이 한 장만 남긴다.

### 11. Awwwards "paper" 태그 모음 — https://www.awwwards.com/websites/paper/
- **확인한 내용**
  - Paper 테마 수상작 목록을 확인했다(Miranda, Twigs Paper, Violet Papers 등).
  - 개별 사이트의 세부 디자인은 위 9번을 빼고 열어 보지 않았다(미확인).
- **적용 방식**
  - 4단계 이후 화면 디자인을 다듬을 때 이 목록에서 추가 레퍼런스를 고른다.

## D. 인터랙션 (접기, 봉투, 봉인)

### 12. CSS로 봉투 만들기 — https://dev.to/superoverflow/make-a-slide-open-envelope-oem
- **확인한 내용**
  - 크기가 0인 div에 투명한 `border-top`을 주어 봉투 덮개(삼각형)를 만든다.
  - `transform: rotateX(45deg)`로 덮개를 연다. 애니메이션은 Framer Motion으로 구현했다.
- **적용 방식**
  - 덮개는 border 삼각형이나 SVG로 만들고, 닫는 동작은 `rotateX(180deg → 0)`으로 표현한다.
  - React와 Framer Motion 없이 **CSS transition과 소량의 바닐라 JS**만 쓴다. 지금 프로젝트가 프레임워크 없는 PHP이기 때문이다.

### 13. Mad Magazine fold-in (순수 CSS 접기) — https://thomaspark.co/2020/06/the-mad-magazine-fold-in-effect-in-css/
- **확인한 내용**
  - 한 장을 여러 패널로 나누고 `transform-origin: left`를 경첩으로 삼는다.
  - `transform-style: preserve-3d`, `backface-visibility: hidden`을 쓰고, `translateZ`로 겹침(z-fighting) 문제를 피한다.
  - 모바일 Safari 확대 상태에서 버그가 보고되었고, `prefers-reduced-motion`은 다루지 않는다.
- **적용 방식**
  - 편지지 접기에 이 패널 분할 방식을 쓴다. 3등분한 뒤 아래 1/3, 위 1/3 순서로 접는다.
  - `prefers-reduced-motion: reduce`일 때는 접기를 **페이드로 대체**한다. 저자가 다루지 않은 부분을 우리가 보완한다.

### 참고했지만 직접 확인하지 못한 것
- CodePen 봉투 예제(codepen.io/Carl-the-styleful/pen/pvjrMoY 등)와 Codrops "3D Folding Layout" 튜토리얼은 **403으로 열리지 않았다.** 검색 요약만 보았으므로 레퍼런스에서 뺐다.
- Dribbble과 Behance는 검색 결과나 페이지 본문을 가져오지 못했다. JS 렌더링 페이지이기 때문이다.

---

## 조사에서 나온 시사점

| 항목 | 근거 | 우리 서비스 결정(안) |
|---|---|---|
| 최소 발송 기간 | Dear Me는 최소 30일, FutureMe 프리셋은 6개월부터, 오프라인 우체통은 6개월~1년 | **최소 30일**(한 달 미만은 리마인더 영역). 프리셋은 100일, 6개월, **1년(기본)**, 3년, 5년 |
| 봉인의 의미 | LaterMe, Wax & Paper | 봉인은 되돌릴 수 없는 의식으로 다룬다. 봉인 후에는 본문을 다시 보여 주지 않는다 |
| 상징색 | 경포, 유치환 우체통의 빨간 우체통 | 빨강은 포인트로만 쓰고, 바탕은 종이색 |
| 문구 톤 | 오프라인 우체통 기사의 "추억, 그리움", FutureMe의 가벼운 톤 | 차분한 존댓말 서정체. 이모지는 쓰지 않는다 |
| 신뢰 | FuturePost의 암호화, 백업 안내 | 보관 방식을 투명하게 안내하는 섹션 |
| 기술 | Awwwards 수상작은 WebGL 위주, CSS 접기 기법 | CSS 3D와 SVG만 쓴다. reduced-motion을 반드시 지원한다 |
