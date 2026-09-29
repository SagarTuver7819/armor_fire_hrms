(function () {
    'use strict';

    var startBtn = document.getElementById('recStartBtn');
    var intro = document.getElementById('recIntro');
    var shell = document.getElementById('recFormShell');
    var form = document.getElementById('recApplyForm');
    var prevBtn = document.getElementById('recPrevBtn');
    var nextBtn = document.getElementById('recNextBtn');
    var submitBtn = document.getElementById('recSubmitBtn');
    var stepBtns = Array.prototype.slice.call(document.querySelectorAll('.rec-step'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.rec-panel'));
    var eduRows = document.getElementById('eduRows');
    var expRows = document.getElementById('expRows');
    var addEduBtn = document.getElementById('addEduBtn');
    var addExpBtn = document.getElementById('addExpBtn');
    var step = 1;
    var maxStep = 5;
    var eduIndex = 0;
    var expIndex = 0;

    function showStep(n) {
        step = Math.max(1, Math.min(maxStep, n));
        panels.forEach(function (p) {
            var id = parseInt(p.getAttribute('data-panel'), 10);
            var on = id === step;
            p.hidden = !on;
            p.classList.toggle('is-active', on);
        });
        stepBtns.forEach(function (b) {
            var id = parseInt(b.getAttribute('data-step'), 10);
            b.classList.toggle('is-active', id === step);
            b.classList.toggle('is-done', id < step);
        });
        if (prevBtn) prevBtn.hidden = step <= 1;
        if (nextBtn) nextBtn.hidden = step >= maxStep;
        if (submitBtn) submitBtn.hidden = step < maxStep;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function fieldWrap(el) {
        return el ? el.closest('.rec-field') : null;
    }

    function markInvalid(el, bad) {
        var wrap = fieldWrap(el);
        if (wrap) wrap.classList.toggle('is-invalid', !!bad);
    }

    function validateStep(n) {
        var panel = document.querySelector('.rec-panel[data-panel="' + n + '"]');
        if (!panel) return true;
        var ok = true;
        var required = panel.querySelectorAll('[required]');
        required.forEach(function (el) {
            var empty = false;
            if (el.type === 'checkbox') {
                empty = !el.checked;
            } else {
                empty = !String(el.value || '').trim();
            }
            markInvalid(el, empty);
            if (empty) ok = false;
        });

        if (n === 2) {
            var mobile = document.getElementById('mobile');
            var email = document.getElementById('email');
            if (mobile) {
                var m = String(mobile.value || '').replace(/\D+/g, '');
                var badM = m.length < 10;
                markInvalid(mobile, badM);
                if (badM) ok = false;
            }
            if (email && email.value) {
                var badE = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
                markInvalid(email, badE);
                if (badE) ok = false;
            }
        }

        if (n === 5) {
            var bank = document.getElementById('bank_statement');
            var slip = document.getElementById('salary_slip');
            var hasFile = (bank && bank.files && bank.files.length) || (slip && slip.files && slip.files.length);
            if (!hasFile) {
                markInvalid(bank, true);
                markInvalid(slip, true);
                ok = false;
                alert('Please upload last 3 months bank statement OR salary slip.');
            } else {
                markInvalid(bank, false);
                markInvalid(slip, false);
            }
        }

        if (!ok) {
            var first = panel.querySelector('.is-invalid input, .is-invalid select, .is-invalid textarea, .is-invalid');
            if (first && first.focus) first.focus();
        }
        return ok;
    }

    function eduTemplate(i) {
        return (
            '<div class="rec-card" data-edu="' + i + '">' +
            '<div class="rec-card-top"><span>Education #' + (i + 1) + '</span>' +
            '<button type="button" class="rec-card-remove" data-remove-edu>Remove</button></div>' +
            '<div class="rec-grid">' +
            '<div class="rec-field"><label>Degree / Course</label>' +
            '<input type="text" name="edu[' + i + '][degree]" placeholder="e.g. B.E. / B.Com / Diploma"></div>' +
            '<div class="rec-field"><label>Institution / University</label>' +
            '<input type="text" name="edu[' + i + '][institution]" placeholder="College / University name"></div>' +
            '<div class="rec-field"><label>Specialization</label>' +
            '<input type="text" name="edu[' + i + '][specialization]" placeholder="Optional"></div>' +
            '<div class="rec-field"><label>Year of Passing</label>' +
            '<input type="text" name="edu[' + i + '][year]" placeholder="e.g. 2022" inputmode="numeric"></div>' +
            '<div class="rec-field"><label>% / CGPA</label>' +
            '<input type="text" name="edu[' + i + '][percentage]" placeholder="e.g. 72% / 7.8"></div>' +
            '</div></div>'
        );
    }

    function expTemplate(i) {
        return (
            '<div class="rec-card" data-exp="' + i + '">' +
            '<div class="rec-card-top"><span>Experience #' + (i + 1) + '</span>' +
            '<button type="button" class="rec-card-remove" data-remove-exp>Remove</button></div>' +
            '<div class="rec-grid">' +
            '<div class="rec-field"><label>Company Name</label>' +
            '<input type="text" name="exp[' + i + '][company]" placeholder="Company name"></div>' +
            '<div class="rec-field"><label>Designation</label>' +
            '<input type="text" name="exp[' + i + '][designation]" placeholder="Your role"></div>' +
            '<div class="rec-field"><label>From</label>' +
            '<input type="text" name="exp[' + i + '][from]" placeholder="e.g. Jan 2021"></div>' +
            '<div class="rec-field"><label>To</label>' +
            '<input type="text" name="exp[' + i + '][to]" placeholder="e.g. Dec 2023"></div>' +
            '<div class="rec-field"><label>Last Salary (₹ / month)</label>' +
            '<input type="number" name="exp[' + i + '][salary]" min="0" step="0.01"></div>' +
            '<div class="rec-field rec-span-2"><label>Key Responsibilities</label>' +
            '<textarea name="exp[' + i + '][responsibilities]" rows="2" placeholder="Brief summary"></textarea></div>' +
            '<div class="rec-check-row">' +
            '<label class="rec-check">' +
            '<input type="checkbox" name="exp[' + i + '][current]" value="1">' +
            '<span>Currently working here</span></label></div>' +
            '</div></div>'
        );
    }

    function addEdu() {
        if (!eduRows) return;
        eduRows.insertAdjacentHTML('beforeend', eduTemplate(eduIndex++));
    }

    function addExp() {
        if (!expRows) return;
        expRows.insertAdjacentHTML('beforeend', expTemplate(expIndex++));
    }

    if (startBtn) {
        startBtn.addEventListener('click', function () {
            if (intro) intro.hidden = true;
            if (shell) shell.hidden = false;
            showStep(1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (!validateStep(step)) return;
            showStep(step + 1);
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            showStep(step - 1);
        });
    }

    stepBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = parseInt(btn.getAttribute('data-step'), 10);
            if (target < step) {
                showStep(target);
                return;
            }
            for (var i = step; i < target; i++) {
                if (!validateStep(i)) {
                    showStep(i);
                    return;
                }
            }
            showStep(target);
        });
    });

    if (addEduBtn) addEduBtn.addEventListener('click', addEdu);
    if (addExpBtn) addExpBtn.addEventListener('click', addExp);

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t && t.matches('[data-remove-edu]')) {
            var card = t.closest('.rec-card');
            if (card) card.remove();
        }
        if (t && t.matches('[data-remove-exp]')) {
            var card2 = t.closest('.rec-card');
            if (card2) card2.remove();
        }
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            if (!validateStep(step)) {
                e.preventDefault();
                return;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
            }
        });
    }

    // If opened with error, jump straight to form
    if (shell && !shell.hidden) {
        showStep(1);
    } else if (window.location.search.indexOf('err=') !== -1) {
        if (intro) intro.hidden = true;
        if (shell) shell.hidden = false;
        showStep(1);
    }

    addEdu();
    addExp();
})();
