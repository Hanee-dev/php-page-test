<?php
/**
 * 개인정보처리방침 초안 — 운영 전 사업자 정보·위탁 현황을 확인해 보완할 것
 * @var array $config
 */
$letter = $config['letter'];
?>
<article class="doc">
    <p class="eyebrow">개인정보처리방침</p>
    <h1 class="doc__title"><?= e($config['app']['name']) ?>의<br>개인정보 처리</h1>

    <section>
        <h2>1. 수집하는 항목과 목적</h2>
        <ul>
            <li><strong>받는 분의 이메일 주소</strong> — 확인 메일과 편지 발송</li>
            <li><strong>편지 제목과 내용</strong> — 정한 날 편지를 전달</li>
            <li><strong>접속 IP의 해시값</strong> — 스팸·남용 방지를 위한 요청 제한 (원래 IP는 저장하지 않음)</li>
        </ul>
        <p>회원가입은 없으며, 위 항목 외의 정보는 수집하지 않습니다.</p>
    </section>

    <section>
        <h2>2. 보관 기간</h2>
        <ul>
            <li>확인되지 않은 편지: 작성 후 <?= (int) $letter['verify_ttl_hours'] ?>시간 뒤 만료, <?= (int) $letter['purge_expired_days'] ?>일 이내 삭제</li>
            <li>봉인된 편지: 도착일까지 암호화하여 보관</li>
            <li>도착한 편지: 발송 후 <?= (int) $letter['purge_delivered_days'] ?>일이 지나면 제목·내용·이메일 삭제</li>
        </ul>
    </section>

    <section>
        <h2>3. 안전성 확보 조치</h2>
        <p>편지 제목·내용과 이메일은 저장 전에 암호화하며, 암호화 키는 데이터베이스와 분리하여 관리합니다. 전송 구간은 HTTPS로 보호합니다.</p>
    </section>

    <section>
        <h2>4. 제3자 제공</h2>
        <p>수집한 정보를 제3자에게 제공하지 않습니다.</p>
    </section>

    <section>
        <h2>5. 문의</h2>
        <p>개인정보 관련 문의와 삭제 요청은 <a href="mailto:<?= e($config['app']['contact_email']) ?>"><?= e($config['app']['contact_email']) ?></a>로 보내 주세요.</p>
    </section>
</article>
