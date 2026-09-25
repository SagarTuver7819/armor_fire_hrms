/**
 * Login page — Admin / Employee
 */
(function () {
    var form = document.getElementById('loginForm');
    var btn = document.getElementById('btnLogin');
    var username = document.getElementById('username');

    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.classList.add('is-loading');
            var text = btn.querySelector('.login-btn-text');
            var load = btn.querySelector('.login-btn-load');
            if (text) text.hidden = true;
            if (load) load.hidden = false;
        });
    }

    if (username && !username.value) {
        setTimeout(function () { username.focus(); }, 500);
    }
})();
