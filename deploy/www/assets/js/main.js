/* 느린 우체통 — 밤하늘·봉인 연출. JS 가 없어도 모든 기능은 동작한다 */
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* 같은 시드면 같은 값을 내는 난수 (별 위치를 편지마다 고정) */
    function seededRandom(seed) {
        var h = 2166136261;
        for (var i = 0; i < seed.length; i++) {
            h ^= seed.charCodeAt(i);
            h = Math.imul(h, 16777619);
        }
        return function () {
            h += 0x6D2B79F5;
            var t = h;
            t = Math.imul(t ^ (t >>> 15), t | 1);
            t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    }

    /* 밤하늘: 배경 별 + 기다리는 편지 수만큼의 금빛 별 (최대 200) */
    var sky = document.querySelector('.sky');
    if (sky) {
        var letters = Math.min(parseInt(sky.getAttribute('data-stars') || '0', 10) || 0, 200);
        var random = seededRandom('slow-mail-sky');
        var fragment = document.createDocumentFragment();
        for (var i = 0; i < 70 + letters; i++) {
            var star = document.createElement('span');
            star.className = i < 70 ? 'star' : 'star star--letter';
            star.style.left = (random() * 100).toFixed(2) + '%';
            star.style.top = (random() * (i < 70 ? 100 : 55)).toFixed(2) + '%';
            star.style.animationDelay = (random() * 4).toFixed(2) + 's';
            var size = i < 70 ? 1 + random() * 1.5 : 2 + random() * 1.5;
            star.style.width = star.style.height = size.toFixed(1) + 'px';
            fragment.appendChild(star);
        }
        sky.appendChild(fragment);
    }

    /* 글자 수 (서버와 같이 코드포인트 기준) */
    var body = document.getElementById('body');
    var counter = document.querySelector('[data-count]');
    if (body && counter) {
        var updateCount = function () {
            counter.textContent = Array.from(body.value.replace(/\r\n/g, '\n')).length.toLocaleString('ko-KR');
        };
        body.addEventListener('input', updateCount);
        updateCount();
    }

    /* 도착일: '직접 고르기'일 때만 날짜 입력 표시 (:has 미지원 브라우저용 is-checked 도 같이) */
    var customBox = document.querySelector('[data-custom-date]');
    var presetInputs = document.querySelectorAll('input[name="preset"]');
    function syncPresets() {
        var custom = false;
        presetInputs.forEach(function (input) {
            input.closest('.preset').classList.toggle('is-checked', input.checked);
            if (input.checked && input.value === 'custom') {
                custom = true;
            }
        });
        if (customBox) {
            customBox.classList.toggle('is-open', custom);
            customBox.querySelector('input').required = custom;
        }
    }
    presetInputs.forEach(function (input) {
        input.addEventListener('change', syncPresets);
    });
    syncPresets();

    /* 연출이 끝난 뒤 실제로 제출 */
    function playThenSubmit(form, className, duration) {
        form.addEventListener('submit', function (event) {
            if (form.classList.contains(className)) {
                event.preventDefault();
                return;
            }
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }
            event.preventDefault();
            var target = form.closest('.scene') || form;
            target.classList.add(className);
            form.classList.add(className);
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
            }
            if (!reduceMotion && navigator.vibrate && className === 'is-sealing') {
                window.setTimeout(function () { navigator.vibrate(25); }, 1500);
            }
            window.setTimeout(function () { form.submit(); }, reduceMotion ? 150 : duration);
        });
    }

    var sealForm = document.querySelector('[data-seal-form]');
    if (sealForm) {
        playThenSubmit(sealForm, 'is-sealing', 2600);
    }
    var starForm = document.querySelector('[data-star-form]');
    if (starForm) {
        playThenSubmit(starForm, 'is-rising', 1200);
    }

    /* 봉인 완료: 내 별이 편지마다 정해진 자리로 날아와 자리 잡음 */
    var myStar = document.querySelector('[data-my-star]');
    if (myStar) {
        var r = seededRandom(myStar.getAttribute('data-seed') || '');
        myStar.style.left = (15 + r() * 70).toFixed(2) + '%';
        myStar.style.top = (8 + r() * 14).toFixed(2) + '%';
        if (!myStar.classList.contains('my-star--still')) {
            myStar.classList.add('is-arriving');
        }
    }

    /* 뒤로 가기(bfcache)로 돌아왔을 때 연출 상태 초기화 */
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) {
            return;
        }
        document.querySelectorAll('.is-sealing, .is-rising').forEach(function (el) {
            el.classList.remove('is-sealing', 'is-rising');
        });
        document.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = false;
        });
    });
})();
