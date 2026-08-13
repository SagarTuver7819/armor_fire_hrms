/**
 * Admin / HR Login — interactions
 */
(function () {
    var form = document.getElementById('loginForm');
    var btn = document.getElementById('btnLogin');
    var toggle = document.getElementById('togglePass');
    var password = document.getElementById('password');
    var username = document.getElementById('username');

    if (toggle && password) {
        toggle.addEventListener('click', function () {
            var show = password.type === 'password';
            password.type = show ? 'text' : 'password';
            toggle.innerHTML = show
                ? '<i class="fa-solid fa-eye-slash"></i>'
                : '<i class="fa-solid fa-eye"></i>';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }

    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.classList.add('is-loading');
            var text = btn.querySelector('.login-btn-text');
            var load = btn.querySelector('.login-btn-load');
            if (text) text.hidden = true;
            if (load) load.hidden = false;
        });
    }

    document.querySelectorAll('.login-demo-chip').forEach(function (el) {
        el.addEventListener('click', function () {
            var user = el.getAttribute('data-user');
            var pass = el.getAttribute('data-pass');
            var role = el.getAttribute('data-role');

            if (username) {
                username.value = user;
                username.focus();
            }
            if (password) {
                password.value = pass;
            }

            var radio = document.querySelector('input[name="login_as"][value="' + role + '"]');
            if (radio) radio.checked = true;

            el.style.transform = 'scale(0.95)';
            setTimeout(function () { el.style.transform = ''; }, 180);
        });
    });

    if (username && !username.value) {
        setTimeout(function () { username.focus(); }, 500);
    }
})();
