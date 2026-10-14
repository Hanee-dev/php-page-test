(function () {
    'use strict';

    var nav = document.querySelector('.main-nav');
    var toggle = document.querySelector('.nav-toggle');
    var header = document.getElementById('site-header');

    // 모바일 메뉴 토글
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open);
        });
    }

    // 스크롤 시 헤더 스타일
    if (header) {
        var scrollClass = 'is-scrolled';
        function onScroll() {
            if (window.scrollY > 20) {
                header.classList.add(scrollClass);
            } else {
                header.classList.remove(scrollClass);
            }
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();
