/**
 * Department-wise employee form
 * Shift master + pay type code generate
 */
(function ($) {
    'use strict';

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
    });
})(window.jQuery);
