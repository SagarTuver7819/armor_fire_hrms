/**
 * Department-wise employee form
 * Shift master + pay type code generate + duplicate code check on blur
 */
(function ($) {
    'use strict';

    function toastWarn(msg) {
        if (window.toastr) {
            toastr.options = toastr.options || {};
            toastr.options.closeButton = true;
            toastr.options.progressBar = true;
            toastr.options.positionClass = 'toast-top-right';
            toastr.options.timeOut = 4500;
            toastr.warning(msg);
            return;
        }
        window.alert(msg);
    }

    function applyShift() {
        var $select = $('#shiftSelect');
        if (!$select.length) {
            return;
        }
        var $opt = $select.find('option:selected');
        var typeHidden = document.getElementById('shiftType');
        var typeDisplay = document.getElementById('shiftTypeDisplay');
        var timeInput = document.getElementById('shiftTime');

        if (!$opt.length || !$opt.val()) {
            if (typeHidden) {
                typeHidden.value = '';
            }
            if (typeDisplay) {
                typeDisplay.value = '';
            }
            if (timeInput) {
                timeInput.value = '';
            }
            return;
        }

        var type = $opt.attr('data-type') || 'Day';
        var time = $opt.attr('data-time') || '';
        if (typeHidden) {
            typeHidden.value = type;
        }
        if (typeDisplay) {
            typeDisplay.value = type;
        }
        if (timeInput) {
            timeInput.value = time;
        }
    }

    function bindShiftSelect() {
        var $select = $('#shiftSelect');
        if (!$select.length) {
            return;
        }
        $select.off('.empShift');
        $select.on('change.empShift select2:select.empShift', applyShift);
        applyShift();
    }

    function fetchCode() {
        var nextUrl = window.EMP_NEXT_CODE_URL;
        var payType = document.getElementById('payType');
        var empCode = document.getElementById('employeeCode');
        if (!nextUrl || !payType || !empCode) {
            return;
        }
        var type = payType.value || 'Salary';
        fetch(nextUrl + '?pay_type=' + encodeURIComponent(type), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.code) {
                    empCode.value = data.code;
                    empCode.classList.remove('is-invalid-code');
                    lastCheckedCode = String(data.code).toUpperCase();
                }
            })
            .catch(function () { /* ignore */ });
    }

    function toggleMainContractor() {
        var mainWrap = document.getElementById('mainContractorWrap');
        var payType = document.getElementById('payType');
        if (!mainWrap || !payType) {
            return;
        }
        mainWrap.style.display = payType.value === 'Jobwork' ? '' : 'none';
    }

    var lastCheckedCode = '';
    var checkTimer = null;

    function checkEmployeeCode() {
        var empCode = document.getElementById('employeeCode');
        var checkUrl = window.EMP_CHECK_CODE_URL;
        if (!empCode || !checkUrl) {
            return;
        }
        var code = String(empCode.value || '').trim().toUpperCase();
        empCode.value = code;
        if (code === '' || code === lastCheckedCode) {
            return;
        }
        lastCheckedCode = code;

        var excludeId = parseInt(window.EMP_ID || '0', 10) || 0;
        var url = checkUrl
            + '?code=' + encodeURIComponent(code)
            + '&exclude_id=' + encodeURIComponent(String(excludeId));

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.exists) {
                    empCode.classList.remove('is-invalid-code');
                    return;
                }
                // Keep all form data — only warn
                empCode.classList.add('is-invalid-code');
                var name = data.employee_name ? (' for "' + data.employee_name + '"') : '';
                var inactive = data.active === false ? ' (inactive/deleted record)' : '';
                toastWarn('Employee code ' + code + ' already exists' + name + inactive + '. Change the code or click refresh for next available.');
            })
            .catch(function () { /* ignore network errors on blur */ });
    }

    function bindCodeDuplicateCheck() {
        var empCode = document.getElementById('employeeCode');
        if (!empCode) {
            return;
        }
        empCode.addEventListener('blur', function () {
            checkEmployeeCode();
        });
        empCode.addEventListener('input', function () {
            empCode.classList.remove('is-invalid-code');
            if (checkTimer) {
                clearTimeout(checkTimer);
            }
            checkTimer = setTimeout(function () {
                lastCheckedCode = '';
            }, 300);
        });
    }

    function toggleMaritalRemark(fromUser) {
        var status = document.getElementById('maritalStatus');
        var wrap = document.getElementById('maritalRemarkWrap');
        var remark = document.getElementById('maritalRemark');
        if (!status || !wrap || !remark) {
            return;
        }
        var isOther = String(status.value || '') === 'Other';
        wrap.classList.toggle('is-open', isOther);
        if (!isOther) {
            remark.value = '';
        } else if (fromUser) {
            setTimeout(function () { remark.focus(); }, 0);
        }
    }

    function bindMaritalStatus() {
        var status = document.getElementById('maritalStatus');
        if (!status) {
            return;
        }
        // Avoid Select2 so Other → Remark works reliably (same on Add + Edit)
        if (window.jQuery && jQuery.fn.select2 && jQuery(status).hasClass('select2-hidden-accessible')) {
            try {
                jQuery(status).select2('destroy');
            } catch (e) { /* ignore */ }
        }
        status.classList.add('no-select2');
        status.onchange = function () { toggleMaritalRemark(true); };
        if (window.jQuery) {
            jQuery(status).off('change.marital select2:select.marital select2:clear.marital')
                .on('change.marital select2:select.marital select2:clear.marital', function () {
                    toggleMaritalRemark(true);
                });
        }
        toggleMaritalRemark(false);
    }

    function bindPhotoPreview() {
        var input = document.getElementById('photoFile');
        var preview = document.getElementById('photoPreview');
        if (!input || !preview) {
            return;
        }
        input.addEventListener('change', function () {
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }
            if (!/^image\/(jpeg|png|webp)$/i.test(file.type) && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
                toastWarn('Photo must be JPG, PNG, or WEBP.');
                input.value = '';
                return;
            }
            var url = URL.createObjectURL(file);
            preview.innerHTML = '<img src="' + url + '" alt="Photo preview">';
        });
    }

    function bindFamilyMembers() {
        var countInput = document.getElementById('familyMemberCount');
        var list = document.getElementById('familyMembersList');
        var tpl = document.getElementById('familyMemberTpl');
        if (!countInput || !list || !tpl) {
            return;
        }

        function readRows() {
            var data = [];
            list.querySelectorAll('.family-member-card').forEach(function (card) {
                data.push({
                    name: (card.querySelector('input[name="family_name[]"]') || {}).value || '',
                    relation: (card.querySelector('input[name="family_relation[]"]') || {}).value || '',
                    occupation: (card.querySelector('input[name="family_occupation[]"]') || {}).value || ''
                });
            });
            return data;
        }

        function render(count) {
            count = parseInt(count, 10);
            if (isNaN(count) || count < 0) count = 0;
            if (count > 10) count = 10;
            countInput.value = String(count);

            var prev = readRows();
            list.innerHTML = '';
            for (var i = 0; i < count; i++) {
                var html = tpl.innerHTML
                    .replace(/__INDEX__/g, String(i))
                    .replace(/__NUM__/g, String(i + 1));
                var wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                var card = wrap.firstElementChild;
                if (prev[i]) {
                    var n = card.querySelector('input[name="family_name[]"]');
                    var r = card.querySelector('input[name="family_relation[]"]');
                    var o = card.querySelector('input[name="family_occupation[]"]');
                    if (n) n.value = prev[i].name;
                    if (r) r.value = prev[i].relation;
                    if (o) o.value = prev[i].occupation;
                }
                list.appendChild(card);
            }
        }

        countInput.addEventListener('input', function () {
            render(countInput.value);
        });
        countInput.addEventListener('change', function () {
            render(countInput.value);
        });
    }

    /**
     * Employee PF = 12% of basic (ceiling ₹15,000).
     * Fills when PF = Yes and Decided Salary changes.
     */
    function pfTwelvePercentAmount(salary) {
        var s = parseFloat(salary, 10);
        if (!isFinite(s) || s <= 0) {
            return 0;
        }
        var wage = Math.min(15000, s);
        return Math.round(wage * 0.12 * 100) / 100;
    }

    function isPfYes() {
        var checked = document.querySelector('input[name="pf_deduction"]:checked');
        return checked && String(checked.value) === 'Yes';
    }

    function applyPfContributions(force) {
        var empInp = document.getElementById('pfEmployeeContribution');
        var salaryInp = document.getElementById('decidedSalary');
        if (!empInp || !salaryInp) {
            return;
        }
        if (!isPfYes()) {
            return;
        }
        var amt = pfTwelvePercentAmount(salaryInp.value);
        if (amt <= 0) {
            return;
        }
        var amtStr = amt.toFixed(2);
        if (force || empInp.value === '' || empInp.dataset.auto === '1') {
            empInp.value = amtStr;
            empInp.dataset.auto = '1';
        }
    }

    function bindPfContributions() {
        var empInp = document.getElementById('pfEmployeeContribution');
        var salaryInp = document.getElementById('decidedSalary');
        if (!empInp || !salaryInp) {
            return;
        }

        empInp.dataset.auto = empInp.value !== '' ? '0' : '1';

        empInp.addEventListener('input', function () {
            empInp.dataset.auto = '0';
        });

        salaryInp.addEventListener('input', function () {
            applyPfContributions(true);
        });
        salaryInp.addEventListener('change', function () {
            applyPfContributions(true);
        });

        document.querySelectorAll('input[name="pf_deduction"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (isPfYes()) {
                    applyPfContributions(true);
                }
            });
        });

        if (isPfYes()) {
            applyPfContributions(false);
        }
    }

    function toastError(msg) {
        if (window.toastr) {
            toastr.options = toastr.options || {};
            toastr.options.closeButton = true;
            toastr.options.progressBar = true;
            toastr.options.positionClass = 'toast-top-right';
            toastr.options.timeOut = 5000;
            toastr.error(msg);
            return;
        }
        window.alert(msg);
    }

    function bindBankAccountConfirm() {
        var form = document.getElementById('employeeForm') || document.querySelector('form.employee-form');
        var acc = document.getElementById('bankAccountNumber');
        var conf = document.getElementById('bankAccountNumberConfirm');
        var err = document.getElementById('bankAccountConfirmError');
        if (!form || !acc || !conf) {
            return;
        }

        function clearMismatch() {
            conf.classList.remove('is-invalid-code');
            acc.classList.remove('is-invalid-code');
            if (err) {
                err.hidden = true;
            }
        }

        function showMismatch() {
            conf.classList.add('is-invalid-code');
            acc.classList.add('is-invalid-code');
            if (err) {
                err.hidden = false;
            }
        }

        /** Validate only after confirm field is left (Tab / click away) */
        function validateOnBlur() {
            var a = String(acc.value || '').trim();
            var c = String(conf.value || '').trim();
            // Empty confirm — no message until user types something
            if (c === '') {
                clearMismatch();
                return true;
            }
            if (a !== c) {
                showMismatch();
                toastError('Bank Account Number and Confirm Account Number do not match.');
                return false;
            }
            clearMismatch();
            return true;
        }

        conf.addEventListener('blur', validateOnBlur);

        // While typing: hide error once values match again
        [acc, conf].forEach(function (el) {
            el.addEventListener('input', function () {
                var a = String(acc.value || '').trim();
                var c = String(conf.value || '').trim();
                if (c === '' || a === c) {
                    clearMismatch();
                }
            });
        });

        form.addEventListener('submit', function (e) {
            var a = String(acc.value || '').trim();
            var c = String(conf.value || '').trim();
            if (a === '' && c === '') {
                clearMismatch();
                return;
            }
            if (a !== c) {
                e.preventDefault();
                showMismatch();
                toastError('Bank Account Number and Confirm Account Number do not match.');
                conf.focus();
                return false;
            }
            clearMismatch();
        });
    }

    /** Exit date ↔ Status sync for Active / Exit Employees tabs */
    function bindEmpStatusExitClear() {
        var statusEl = document.getElementById('empStatusSelect');
        var exitEl = document.getElementById('dateOfExitInput');
        if (!statusEl || !exitEl) {
            return;
        }

        function parseExitYmd(raw) {
            var s = String(raw || '').trim();
            if (!s) return '';
            var m = s.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
            if (m) {
                var d = parseInt(m[1], 10);
                var mo = parseInt(m[2], 10);
                var y = parseInt(m[3], 10);
                if (mo < 1 || mo > 12 || d < 1 || d > 31) return '';
                return y + '-' + (mo < 10 ? '0' : '') + mo + '-' + (d < 10 ? '0' : '') + d;
            }
            m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return m ? m[0] : '';
        }

        function todayYmd() {
            var n = new Date();
            var m = n.getMonth() + 1;
            var d = n.getDate();
            return n.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (d < 10 ? '0' : '') + d;
        }

        function clearExitDate() {
            if (!String(exitEl.value || '').trim()) return;
            exitEl.value = '';
            if (exitEl._flatpickr) {
                try { exitEl._flatpickr.clear(); } catch (e) { /* ignore */ }
            }
        }

        function setStatusValue(val) {
            statusEl.value = String(val);
            if (window.jQuery) {
                window.jQuery(statusEl).val(String(val)).trigger('change.select2');
            }
        }

        // Exit Employees tab → Active again: clear exit date (other fields stay)
        function onStatusChange() {
            if (String(statusEl.value) === '1') {
                clearExitDate();
            }
        }

        // Exit date entered → Deactive when date is today/past (so it reflects in Exit tab)
        function onExitChange() {
            var ymd = parseExitYmd(exitEl.value);
            if (!ymd) return;
            if (ymd <= todayYmd()) {
                setStatusValue('0');
            }
        }

        statusEl.addEventListener('change', onStatusChange);
        exitEl.addEventListener('change', onExitChange);
        exitEl.addEventListener('blur', onExitChange);
        if (window.jQuery) {
            window.jQuery(statusEl).on('change.select2 select2:select', onStatusChange);
        }
    }

    $(function () {
        // Run after global Select2 init (employee_form.js loads after select2_init.js).
        setTimeout(bindShiftSelect, 0);

        var btnGen = document.getElementById('btnGenCode');
        var payType = document.getElementById('payType');
        if (btnGen) {
            btnGen.addEventListener('click', fetchCode);
        }
        if (payType) {
            payType.addEventListener('change', toggleMainContractor);
            toggleMainContractor();
            if (window.EMP_IS_NEW) {
                payType.addEventListener('change', fetchCode);
            }
        }

        bindCodeDuplicateCheck();
        bindMaritalStatus();
        // Select2 may init after this file — re-apply so Other → Remark works on Add + Edit
        setTimeout(bindMaritalStatus, 100);
        setTimeout(bindMaritalStatus, 400);
        bindPhotoPreview();
        bindFamilyMembers();
        bindPfContributions();
        bindBankAccountConfirm();
        bindEmpStatusExitClear();
    });
})(window.jQuery);
