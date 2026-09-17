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

    $(function () {
        // Run after global Select2 init (employee_form.js loads before select2_init.js).
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
    });
})(window.jQuery);
