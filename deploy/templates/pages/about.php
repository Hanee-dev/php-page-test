<?php /** @var array $config */ ?>
<article class="doc">
    <p class="eyebrow">편지는 이렇게 보관됩니다</p>
    <h1 class="doc__title">당신의 편지가<br>기다리는 동안</h1>

    <section>
        <h2>아무도 읽을 수 없게 잠가 둡니다</h2>
        <p>편지의 제목과 내용, 받는 분의 이메일은 저장되기 전에 암호화돼요. 데이터베이스만으로는 내용을 읽을 수 없고, 운영자도 편지를 열어보지 않습니다.</p>
    </section>

    <section>
        <h2>봉인하면 되돌릴 수 없어요</h2>
        <p>확인 메일의 링크를 열어 봉인한 편지는 도착하는 날까지 수정하거나 다시 볼 수 없어요. 느린 우체통의 편지는 보내는 순간부터 미래의 것이니까요.</p>
    </section>

    <section>
        <h2>도착하면, 조용히 지워집니다</h2>
        <p>편지는 정한 날 오전 <?= (int) $config['letter']['deliver_hour'] ?>시 무렵 메일로 도착해요. 도착하고 <?= (int) $config['letter']['purge_delivered_days'] ?>일이 지나면 우체통에 남은 내용과 이메일은 지워집니다. 간직하고 싶다면 받은 메일을 보관해 주세요.</p>
    </section>

    <section class="faq">
        <h2>자주 묻는 질문</h2>
        <details>
            <summary>왜 한 달 뒤부터만 보낼 수 있나요?</summary>
            <p>느린 우체통은 알림 서비스가 아니라 편지를 위한 곳이에요. 적어도 한 달은 기다려야 쓴 날의 마음이 낯설고 반갑게 도착하더라고요.</p>
        </details>
        <details>
            <summary>확인 메일이 오지 않아요.</summary>
            <p>스팸함을 먼저 확인해 주세요. <?= (int) $config['letter']['verify_ttl_hours'] ?>시간 안에 봉인하지 않은 편지는 사라지니, 그 뒤에는 다시 써 주셔야 해요.</p>
        </details>
        <details>
            <summary>도착하는 날 이메일 주소가 바뀌어 있으면요?</summary>
            <p>아쉽지만 편지는 처음 적은 주소로만 갈 수 있어요. 오래 쓸 주소를 적어 주세요.</p>
        </details>
        <details>
            <summary>몇 시에 도착하나요?</summary>
            <p>한국 시간 기준으로 고른 날 오전 <?= (int) $config['letter']['deliver_hour'] ?>시 무렵이에요. 메일 서버 사정에 따라 조금 늦을 수 있어요.</p>
        </details>
    </section>
</article>
