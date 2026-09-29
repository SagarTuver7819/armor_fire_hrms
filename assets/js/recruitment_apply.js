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
    var totalExpInput = document.getElementById('total_experience');
    var totalExpBox = document.getElementById('recExpTotalBox');
    var totalExpText = document.getElementById('recExpTotalText');
    var step = 1;
    var maxStep = 5;
    var eduIndex = 0;
    var expIndex = 0;
    var draft = window.REC_DRAFT || {};

    if (window.toastr) {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 4500,
            extendedTimeOut: 2000
        };
    }

    function toastError(msg) {
        if (window.toastr) {
            toastr.error(String(msg || 'Please check the form.'));
        } else {
            alert(msg);
        }
    }

    function todayYm() {
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        return d.getFullYear() + '-' + m;
    }

    function parseYm(value) {
        var v = String(value || '').trim();
        if (!/^\d{4}-\d{2}$/.test(v)) return null;
        var parts = v.split('-');
        var y = parseInt(parts[0], 10);
        var mo = parseInt(parts[1], 10);
        if (mo < 1 || mo > 12) return null;
        return { y: y, m: mo };
    }

    function monthsBetween(fromYm, toYm) {
        var from = parseYm(fromYm);
        var to = parseYm(toYm);
        if (!from || !to) return 0;
        var diff = (to.y - from.y) * 12 + (to.m - from.m);
        if (diff < 0) return 0;
        return diff === 0 ? 1 : diff;
    }

    function formatExperience(months) {
        months = Math.max(0, parseInt(months, 10) || 0);
        if (months <= 0) return 'Fresher / 0 months';
        var y = Math.floor(months / 12);
        var m = months % 12;
        var parts = [];
        if (y > 0) parts.push(y + (y === 1 ? ' year' : ' years'));
        if (m > 0) parts.push(m + (m === 1 ? ' month' : ' months'));
        return parts.join(' ') || '0 months';
    }

    function setBtnVisible(btn, visible) {
        if (!btn) return;
        if (visible) {
            btn.hidden = false;
            btn.removeAttribute('hidden');
            btn.style.display = '';
        } else {
            btn.hidden = true;
            btn.setAttribute('hidden', 'hidden');
            btn.style.display = 'none';
        }
    }

    function syncNavButtons() {
        setBtnVisible(prevBtn, step > 1);
        setBtnVisible(nextBtn, step < maxStep);
        setBtnVisible(submitBtn, step === maxStep);
    }

    function syncCurrentJob(card) {
        if (!card) return;
        var chk = card.querySelector('input[type="checkbox"][name*="[current]"]');
        var toInput = card.querySelector('input[name*="[to]"]');
        if (!chk || !toInput) return;
        if (chk.checked) {
            toInput.value = todayYm();
            toInput.disabled = true;
            toInput.setAttribute('data-auto-present', '1');
        } else {
            toInput.disabled = false;
            if (toInput.getAttribute('data-auto-present') === '1') {
                toInput.removeAttribute('data-auto-present');
            }
        }
    }

    function updateExperienceTotals() {
        if (!expRows) return;
        var cards = expRows.querySelectorAll('.rec-card[data-exp]');
        var totalMonths = 0;
        cards.forEach(function (card) {
            syncCurrentJob(card);
            var fromInput = card.querySelector('input[name*="[from]"]');
            var toInput = card.querySelector('input[name*="[to]"]');
            var durEl = card.querySelector('[data-exp-duration]');
            var from = fromInput ? fromInput.value : '';
            var to = toInput ? toInput.value : '';
            if (card.querySelector('input[type="checkbox"][name*="[current]"]:checked')) {
                to = todayYm();
            }
            var months = monthsBetween(from, to);
            totalMonths += months;
            if (durEl) {
                durEl.textContent = months > 0
                    ? ('Duration: ' + formatExperience(months))
                    : 'Duration: —';
            }
        });
        var label = formatExperience(totalMonths);
        if (totalExpInput) totalExpInput.value = totalMonths > 0 ? label : '';
        if (totalExpText) totalExpText.textContent = label;
        if (totalExpBox) totalExpBox.hidden = totalMonths <= 0;
    }

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
        syncNavButtons();
        if (step === 4) updateExperienceTotals();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function fieldWrap(el) {
        return el ? el.closest('.rec-field') : null;
    }

    function markInvalid(el, bad) {
        var wrap = fieldWrap(el);
        if (wrap) wrap.classList.toggle('is-invalid', !!bad);
    }

    function validateStep(n, silent) {
        var panel = document.querySelector('.rec-panel[data-panel="' + n + '"]');
        if (!panel) return true;
        var ok = true;
        var msg = '';
        var required = panel.querySelectorAll('[required]');
        required.forEach(function (el) {
            var empty = false;
            if (el.type === 'checkbox') {
                empty = !el.checked;
            } else {
                empty = !String(el.value || '').trim();
            }
            markInvalid(el, empty);
            if (empty) {
                ok = false;
                if (!msg) msg = 'Please fill all required fields.';
            }
        });

        if (n === 2) {
            var mobile = document.getElementById('mobile');
            var email = document.getElementById('email');
            if (mobile) {
                var m = String(mobile.value || '').replace(/\D+/g, '');
                var badM = m.length < 10;
                markInvalid(mobile, badM);
                if (badM) {
                    ok = false;
                    msg = 'Please enter a valid 10-digit mobile number.';
                }
            }
            if (email && email.value) {
                var badE = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
                markInvalid(email, badE);
                if (badE) {
                    ok = false;
                    msg = 'Please enter a valid email address.';
                }
            }
        }

        if (n === 4) {
            updateExperienceTotals();
            var cards = expRows ? expRows.querySelectorAll('.rec-card[data-exp]') : [];
            cards.forEach(function (card) {
                var company = card.querySelector('input[name*="[company]"]');
                var fromInput = card.querySelector('input[name*="[from]"]');
                var toInput = card.querySelector('input[name*="[to]"]');
                var hasCompany = company && String(company.value || '').trim() !== '';
                if (!hasCompany) return;
                var fromOk = fromInput && parseYm(fromInput.value);
                markInvalid(fromInput, !fromOk);
                if (!fromOk) {
                    ok = false;
                    msg = 'Please enter valid From / To month for each company.';
                }
                var isCurrent = !!card.querySelector('input[type="checkbox"][name*="[current]"]:checked');
                var toVal = isCurrent ? todayYm() : (toInput ? toInput.value : '');
                var toOk = parseYm(toVal);
                if (!isCurrent) {
                    markInvalid(toInput, !toOk);
                    if (!toOk) {
                        ok = false;
                        msg = 'Please enter valid From / To month for each company.';
                    }
                }
                if (fromOk && toOk && fromInput.value > toVal) {
                    markInvalid(toInput, true);
                    ok = false;
                    msg = 'To month cannot be before From month.';
                }
            });
        }

        if (n === 5) {
            var bank = document.getElementById('bank_statement');
            var slip = document.getElementById('salary_slip');
            var hasFile = (bank && bank.files && bank.files.length) || (slip && slip.files && slip.files.length);
            if (!hasFile) {
                markInvalid(bank, true);
                markInvalid(slip, true);
                ok = false;
                msg = 'Please upload last 3 months bank statement OR salary slip.';
            } else {
                markInvalid(bank, false);
                markInvalid(slip, false);
            }
        }

        if (!ok) {
            if (!silent) toastError(msg || 'Please check the form.');
            var first = panel.querySelector('.is-invalid input, .is-invalid select, .is-invalid textarea');
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
            '<input type="month" name="exp[' + i + '][from]" max="' + todayYm() + '">' +
            '<p class="rec-exp-duration" data-exp-duration>Duration: —</p></div>' +
            '<div class="rec-field"><label>To</label>' +
            '<input type="month" name="exp[' + i + '][to]" max="' + todayYm() + '"></div>' +
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

    function addEdu(data) {
        if (!eduRows) return;
        var i = eduIndex++;
        eduRows.insertAdjacentHTML('beforeend', eduTemplate(i));
        if (data) {
            var card = eduRows.querySelector('.rec-card[data-edu="' + i + '"]');
            if (!card) return;
            setVal(card, 'input[name*="[degree]"]', data.degree);
            setVal(card, 'input[name*="[institution]"]', data.institution);
            setVal(card, 'input[name*="[specialization]"]', data.specialization);
            setVal(card, 'input[name*="[year]"]', data.year);
            setVal(card, 'input[name*="[percentage]"]', data.percentage);
        }
    }

    function addExp(data) {
        if (!expRows) return;
        var i = expIndex++;
        expRows.insertAdjacentHTML('beforeend', expTemplate(i));
        if (data) {
            var card = expRows.querySelector('.rec-card[data-exp="' + i + '"]');
            if (!card) return;
            setVal(card, 'input[name*="[company]"]', data.company);
            setVal(card, 'input[name*="[designation]"]', data.designation);
            setVal(card, 'input[name*="[from]"]', data.from);
            setVal(card, 'input[name*="[to]"]', data.to);
            setVal(card, 'input[name*="[salary]"]', data.salary);
            setVal(card, 'textarea[name*="[responsibilities]"]', data.responsibilities);
            var chk = card.querySelector('input[type="checkbox"][name*="[current]"]');
            if (chk && (data.current === '1' || data.current === 1 || data.current === true)) {
                chk.checked = true;
            }
        }
        updateExperienceTotals();
    }

    function setVal(root, sel, value) {
        if (value === undefined || value === null) return;
        var el = root.querySelector(sel);
        if (el) el.value = String(value);
    }

    function setFormValue(name, value) {
        if (value === undefined || value === null) return;
        var el = form ? form.querySelector('[name="' + name + '"]') : null;
        if (!el) return;
        if (el.type === 'checkbox') {
            el.checked = !!value && value !== '0';
        } else {
            el.value = String(value);
        }
    }

    function restoreDraft() {
        var fields = draft.fields;
        if (!fields || typeof fields !== 'object') return;

        [
            'department_name', 'position_name', 'full_name', 'mobile', 'alt_mobile', 'email',
            'dob', 'age_years', 'gender', 'marital_status', 'address', 'city', 'state_name', 'pincode',
            'aadhaar_no', 'pan_no', 'bank_name', 'bank_account', 'bank_ifsc',
            'current_salary', 'expected_salary', 'notice_period', 'total_experience'
        ].forEach(function (key) {
            if (fields[key] !== undefined) setFormValue(key, fields[key]);
        });

        if (eduRows) eduRows.innerHTML = '';
        eduIndex = 0;
        var edu = fields.edu;
        if (edu && typeof edu === 'object') {
            Object.keys(edu).forEach(function (k) {
                addEdu(edu[k]);
            });
        }
        if (!eduRows || !eduRows.children.length) addEdu();

        if (expRows) expRows.innerHTML = '';
        expIndex = 0;
        var exp = fields.exp;
        if (exp && typeof exp === 'object') {
            Object.keys(exp).forEach(function (k) {
                addExp(exp[k]);
            });
        }
        if (!expRows || !expRows.children.length) addExp();

        updateExperienceTotals();
    }

    if (startBtn) {
        startBtn.addEventListener('click', function () {
            if (intro) intro.hidden = true;
            if (shell) {
                shell.hidden = false;
                shell.removeAttribute('hidden');
            }
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
            // Keep all filled data — only change visible step
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

    if (addEduBtn) addEduBtn.addEventListener('click', function () { addEdu(); });
    if (addExpBtn) addExpBtn.addEventListener('click', function () { addExp(); });

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t && t.matches('[data-remove-edu]')) {
            var card = t.closest('.rec-card');
            if (card) card.remove();
        }
        if (t && t.matches('[data-remove-exp]')) {
            var card2 = t.closest('.rec-card');
            if (card2) card2.remove();
            updateExperienceTotals();
        }
    });

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !expRows || !expRows.contains(t)) return;
        if (t.matches('input[type="month"], input[type="checkbox"][name*="[current]"]')) {
            updateExperienceTotals();
        }
    });

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t || !expRows || !expRows.contains(t)) return;
        if (t.matches('input[type="month"]')) {
            updateExperienceTotals();
        }
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            updateExperienceTotals();
            if (expRows) {
                expRows.querySelectorAll('input[name*="[to]"][disabled]').forEach(function (el) {
                    el.disabled = false;
                    if (!el.value) el.value = todayYm();
                });
            }
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

    function calcAgeFromDob() {
        var dobEl = document.getElementById('dob');
        var ageEl = document.getElementById('age_years');
        if (!dobEl || !ageEl) return;
        var v = String(dobEl.value || '');
        if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) {
            ageEl.value = '';
            return;
        }
        var parts = v.split('-');
        var birth = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        var today = new Date();
        var age = today.getFullYear() - birth.getFullYear();
        var m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        ageEl.value = age >= 0 && age < 120 ? String(age) : '';
    }

    var dobInput = document.getElementById('dob');
    if (dobInput) {
        dobInput.addEventListener('change', calcAgeFromDob);
        dobInput.addEventListener('input', calcAgeFromDob);
    }

    // Init rows / restore
    if (draft.fields) {
        if (intro) intro.hidden = true;
        if (shell) {
            shell.hidden = false;
            shell.removeAttribute('hidden');
        }
        restoreDraft();
        calcAgeFromDob();
        showStep(parseInt(draft.step, 10) || 5);
        if (draft.error) {
            setTimeout(function () { toastError(draft.error); }, 250);
        }
    } else {
        addEdu();
        addExp();
        syncNavButtons();
        if (draft.error) {
            if (intro) intro.hidden = true;
            if (shell) {
                shell.hidden = false;
                shell.removeAttribute('hidden');
            }
            showStep(1);
            setTimeout(function () { toastError(draft.error); }, 250);
        }
    }
})();
