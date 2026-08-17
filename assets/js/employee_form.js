/**
 * Department-wise employee form
 * Shift master + pay type code generate
 */
(function () {
    var select = document.getElementById('shiftSelect');
    var typeHidden = document.getElementById('shiftType');
    var typeDisplay = document.getElementById('shiftTypeDisplay');
    var timeInput = document.getElementById('shiftTime');

    function applyShift() {
        if (!select) return;
        var opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) {
            if (typeHidden) typeHidden.value = 'Day';
            if (typeDisplay) typeDisplay.value = 'Day';
            if (timeInput) timeInput.value = '';
            return;
        }
        var type = opt.getAttribute('data-type') || 'Day';
        var time = opt.getAttribute('data-time') || '';
        if (typeHidden) typeHidden.value = type;
        if (typeDisplay) typeDisplay.value = type;
        if (timeInput) timeInput.value = time;
    }

    if (select) {
        select.addEventListener('change', applyShift);
        if (select.value) {
            applyShift();
        }
    }

    var payType = document.getElementById('payType');
    var empCode = document.getElementById('employeeCode');
    var btnGen = document.getElementById('btnGenCode');
    var nextUrl = window.EMP_NEXT_CODE_URL;

    function fetchCode() {
        if (!nextUrl || !payType || !empCode) return;
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

    if (btnGen) {
        btnGen.addEventListener('click', fetchCode);
    }
    if (payType && window.EMP_IS_NEW) {
        payType.addEventListener('change', fetchCode);
    }
})();
