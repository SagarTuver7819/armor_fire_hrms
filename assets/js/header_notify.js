/**
 * Header bell — circular notifications panel
 */
(function () {
    'use strict';

    var wrap = document.getElementById('headerNotify');
    var btn = document.getElementById('headerBellBtn');
    var panel = document.getElementById('headerNotifyPanel');
    if (!wrap || !btn || !panel) {
        return;
    }

    function closePanel() {
        panel.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        wrap.classList.remove('is-open');
    }

    function openPanel() {
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        wrap.classList.add('is-open');
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) {
            closePanel();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePanel();
        }
    });
})();
