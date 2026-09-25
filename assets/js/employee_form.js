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

    function bindSameAsPermanentAddress() {
        var perm = document.getElementById('permanentAddress');
        var present = document.getElementById('presentAddress');
        var chk = document.getElementById('sameAsPermanentAddr');
        if (!perm || !present || !chk) {
            return;
        }

        function syncFromPermanent() {
            if (!chk.checked) {
                return;
            }
            present.value = perm.value;
        }

        function refreshCheckedState() {
            var p = (perm.value || '').trim();
            var c = (present.value || '').trim();
            if (p !== '' && p === c) {
                chk.checked = true;
            }
        }

        chk.addEventListener('change', function () {
            if (chk.checked) {
                syncFromPermanent();
                present.readOnly = true;
                present.classList.add('is-synced-addr');
            } else {
                present.readOnly = false;
                present.classList.remove('is-synced-addr');
            }
        });

        perm.addEventListener('input', syncFromPermanent);
        present.addEventListener('input', function () {
            if (chk.checked && present.value !== perm.value) {
                chk.checked = false;
                present.readOnly = false;
                present.classList.remove('is-synced-addr');
            }
        });

        refreshCheckedState();
        if (chk.checked) {
            present.readOnly = true;
            present.classList.add('is-synced-addr');
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
        bindSameAsPermanentAddress();
        bindSalaryRevise();
        bindOnFieldAssign();
    });

    function bindOnFieldAssign() {
        if (!window.jQuery) {
            return;
        }
        var $ = window.jQuery;
        var $dept = $('#empDepartmentId');
        var $wrap = $('#onFieldAssignWrap');
        var $state = $('#assignedStateId');
        var $loc = $('#assignedLocationId');
        if (!$dept.length || !$wrap.length || !$state.length || !$loc.length) {
            return;
        }
        var locUrl = window.LOC_BY_STATE_URL || '';
        var initialLoc = String(window.ASSIGNED_LOC_SELECTED || $loc.attr('data-selected') || '0');
        var selectedLoc = initialLoc;
        var suppressStateChange = false;
        var initDone = false;

        function isOnFieldSelected() {
            var onField = String($dept.find('option:selected').attr('data-on-field') || '');
            return onField === '1';
        }

        function refreshSelect2($el, placeholder) {
            if ($el.hasClass('select2-hidden-accessible') && $el.data('select2')) {
                try { $el.select2('destroy'); } catch (e) { /* ignore */ }
            }
            if ($.fn.select2) {
                $el.select2({
                    width: '100%',
                    placeholder: placeholder || 'Select',
                    allowClear: true
                });
            }
        }

        function fillLocations(rows, keepId) {
            var html = '<option value="0">— Select location —</option>';
            var keep = String(keepId || '0');
            var found = false;
            (rows || []).forEach(function (r) {
                var id = String(r.id);
                var isSel = (keep !== '0' && id === keep);
                if (isSel) found = true;
                html += '<option value="' + id + '"' + (isSel ? ' selected' : '') + '>'
                    + $('<div/>').text(r.name || '').html() + '</option>';
            });
            $loc.html(html);
            if (found) {
                $loc.val(keep);
            } else {
                $loc.val('0');
            }
            refreshSelect2($loc, '— Select location —');
            // Select2 needs explicit val + trigger after rebuild
            if (found) {
                $loc.val(keep).trigger('change.select2');
            }
        }

        function loadLocations(keepId) {
            var stateId = parseInt($state.val(), 10) || 0;
            var keep = String(keepId || '0');
            if (!locUrl || stateId <= 0) {
                fillLocations([], '0');
                return;
            }
            $.getJSON(locUrl, { state_id: stateId })
                .done(function (rows) {
                    fillLocations(Array.isArray(rows) ? rows : [], keep);
                })
                .fail(function () {
                    fillLocations([], '0');
                });
        }

        function toggleWrap(isInit) {
            var show = isOnFieldSelected();
            $wrap.toggle(show);
            if (!show) {
                suppressStateChange = true;
                $state.val('0');
                refreshSelect2($state, '— Select state —');
                fillLocations([], '0');
                suppressStateChange = false;
                return;
            }
            suppressStateChange = true;
            refreshSelect2($state, '— Select state —');
            suppressStateChange = false;
            // Keep saved location on first open / edit load
            loadLocations(isInit ? selectedLoc : '0');
        }

        $dept.on('change', function () {
            selectedLoc = '0';
            initialLoc = '0';
            toggleWrap(false);
        });
        $state.on('change', function () {
            if (suppressStateChange) {
                return;
            }
            // User changed state → reset location (not during init)
            selectedLoc = '0';
            if (isOnFieldSelected()) {
                loadLocations('0');
            }
        });

        // Wait for global Select2 init, then restore saved location
        setTimeout(function () {
            if (!isOnFieldSelected()) {
                $wrap.hide();
                initDone = true;
                return;
            }
            selectedLoc = initialLoc;
            toggleWrap(true);
            // Second pass: ensure Select2 shows the saved city
            setTimeout(function () {
                if (isOnFieldSelected() && String(initialLoc) !== '0') {
                    selectedLoc = initialLoc;
                    loadLocations(initialLoc);
                }
                initDone = true;
            }, 250);
        }, 200);
    }

    function bindSalaryRevise() {
        var btn = document.getElementById('btnReviseSalary');
        var modal = document.getElementById('salaryReviseModal');
        var salaryInp = document.getElementById('decidedSalary');
        if (!btn || !modal || !salaryInp || !window.EMP_ID) {
            return;
        }

        var existDisp = document.getElementById('salExistDisp');
        var changeInp = document.getElementById('salChangeAmt');
        var effInp = document.getElementById('salEffectiveDate');
        var preview = document.getElementById('salNewPreview');
        var remarkInp = document.getElementById('salRemark');
        var okBtn = document.getElementById('salReviseOk');

        function money(n) {
            var x = parseFloat(n, 10);
            if (!isFinite(x)) x = 0;
            return x.toFixed(2);
        }

        function updatePreview() {
            var oldV = parseFloat(salaryInp.value, 10) || 0;
            var chg = parseFloat(changeInp.value, 10);
            if (!isFinite(chg)) {
                preview.value = '';
                return;
            }
            preview.value = money(oldV + chg);
        }

        function openModal() {
            existDisp.value = money(salaryInp.value || 0);
            changeInp.value = '';
            remarkInp.value = '';
            preview.value = '';
            var today = new Date();
            var dd = String(today.getDate()).padStart(2, '0');
            var mm = String(today.getMonth() + 1).padStart(2, '0');
            var yyyy = today.getFullYear();
            var todayStr = dd + '-' + mm + '-' + yyyy;
            modal.hidden = false;
            document.body.classList.add('confirm-modal-open');
            if (typeof window.ArmorInitDates === 'function') {
                window.ArmorInitDates(modal);
            }
            // Force flatpickr to show DD-MM-YYYY (re-open sync)
            if (effInp._flatpickr) {
                try {
                    effInp._flatpickr.setDate(today, true);
                } catch (e1) {
                    effInp.value = todayStr;
                }
            } else {
                effInp.value = todayStr;
            }
            setTimeout(function () { changeInp.focus(); }, 50);
        }

        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('confirm-modal-open');
        }

        function renderHistory(rows) {
            var body = document.getElementById('salaryHistoryBody');
            var empty = document.getElementById('salaryHistoryEmpty');
            var wrap = body ? body.closest('.salary-history-scroll') : null;
            if (!body) return;
            body.innerHTML = '';
            if (!rows || !rows.length) {
                if (empty) empty.hidden = false;
                if (wrap) wrap.hidden = true;
                return;
            }
            if (empty) empty.hidden = true;
            if (wrap) wrap.hidden = false;
            rows.forEach(function (h) {
                var tr = document.createElement('tr');
                var cls = h.is_increase ? 'is-up' : 'is-down';
                var note = h.remarks || '—';
                var by = h.changed_by || '—';
                tr.innerHTML =
                    '<td class="col-eff">' + (h.effective_date || '') + '</td>' +
                    '<td class="col-amt">₹ ' + (h.old_salary || '0.00') + '</td>' +
                    '<td class="col-chg ' + cls + '">' + (h.change_signed || h.change_amount || '') + '</td>' +
                    '<td class="col-amt"><strong>₹ ' + (h.new_salary || '0.00') + '</strong></td>' +
                    '<td class="col-note"></td>' +
                    '<td class="col-by"></td>';
                tr.querySelector('.col-note').textContent = note;
                tr.querySelector('.col-by').textContent = by;
                body.appendChild(tr);
            });
        }

        btn.addEventListener('click', openModal);
        salaryInp.addEventListener('click', openModal);
        modal.querySelectorAll('[data-close-salary-modal]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        changeInp.addEventListener('input', updatePreview);
        changeInp.addEventListener('change', updatePreview);

        okBtn.addEventListener('click', function () {
            var chg = parseFloat(changeInp.value, 10);
            if (!isFinite(chg) || Math.abs(chg) < 0.001) {
                toastError('Enter vadharo / ghatado amount (e.g. 2000 or -500).');
                changeInp.focus();
                return;
            }
            if (!String(effInp.value || '').trim()) {
                toastError('Effective date is required.');
                effInp.focus();
                return;
            }
            okBtn.disabled = true;
            var fd = new FormData();
            fd.append('employee_id', String(window.EMP_ID));
            fd.append('change_amount', String(chg));
            fd.append('effective_date', String(effInp.value).trim());
            fd.append('remarks', String(remarkInp.value || '').trim());

            fetch(window.EMP_SALARY_REVISE_URL, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    okBtn.disabled = false;
                    if (!data || !data.ok) {
                        toastError((data && data.error) || 'Could not save salary revision.');
                        return;
                    }
                    salaryInp.value = money(data.current_salary);
                    applyPfContributions(true);
                    renderHistory(data.history || []);
                    closeModal();
                    if (window.toastr) {
                        toastr.success(data.message || 'Salary revised.');
                    }
                })
                .catch(function () {
                    okBtn.disabled = false;
                    toastError('Network error while saving salary.');
                });
        });
    }
})(window.jQuery);
