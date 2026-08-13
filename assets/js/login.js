/**
 * Professional Login Interactions
 */
(function () {
    var form = document.getElementById('loginForm');
    var btn = document.getElementById('btnLogin');
    var toggle = document.getElementById('togglePass');
    var password = document.getElementById('password');
    var username = document.getElementById('username');

    // Show / hide password
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

    // Loading state on submit
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.classList.add('is-loading');
            var text = btn.querySelector('.lp-submit-text');
            var load = btn.querySelector('.lp-submit-load');
            if (text) text.hidden = true;
            if (load) load.hidden = false;
        });
    }

    // One-click demo credentials
    document.querySelectorAll('.lp-demo-btn').forEach(function (el) {
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

            el.style.transform = 'scale(0.97)';
            setTimeout(function () { el.style.transform = ''; }, 150);
        });
    });

    // Count-up for departments
    var counter = document.querySelector('[data-count]');
    if (counter) {
        var target = parseInt(counter.getAttribute('data-count'), 10) || 0;
        var current = 0;
        var step = Math.max(1, Math.ceil(target / 28));
        var timer = setInterval(function () {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            counter.textContent = String(current);
        }, 40);
    }

    // Soft focus class
    document.querySelectorAll('.lp-input input').forEach(function (input) {
        input.addEventListener('focus', function () {
            input.parentElement.classList.add('is-focused');
        });
        input.addEventListener('blur', function () {
            input.parentElement.classList.remove('is-focused');
        });
    });
})();
