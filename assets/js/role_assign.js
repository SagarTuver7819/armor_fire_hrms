/**
 * Role assign — Department filter + Select2 employee + auto username
 */
(function ($) {
    'use strict';

    var allEmpOptionsHtml = '';

    function rebuildEmployeeOptions(deptId, selectedId) {
        var $emp = $('#assignEmpId');
        if (!$emp.length || $emp.prop('disabled')) {
            return;
        }
        deptId = String(deptId || '');
        selectedId = String(selectedId || '');

        var $tmp = $('<select/>').html(allEmpOptionsHtml);
        var html = '<option value="">Select Employee</option>';
        $tmp.find('option').each(function () {
            var $opt = $(this);
            var val = String($opt.attr('value') || '');
            if (val === '') {
                return;
            }
            var optDept = String($opt.attr('data-dept') || $opt.data('dept') || '');
            if (deptId !== '' && optDept !== deptId) {
                return;
            }
            var selected = (val === selectedId) ? ' selected' : '';
            html += '<option value="' + val + '"'
                + ' data-code="' + ($opt.attr('data-code') || '') + '"'
                + ' data-dept="' + optDept + '"'
                + selected + '>'
                + $opt.text()
                + '</option>';
        });

        if ($.fn.select2 && $emp.hasClass('select2-hidden-accessible')) {
            $emp.select2('destroy');
        }
        $emp.html(html);
        if ($.fn.select2) {
            $emp.select2({ width: '100%', placeholder: 'Select Employee' });
        }
    }

    $(function () {
        if (!$('#roleAssignForm').length) {
            return;
        }

        var $emp = $('#assignEmpId');
        allEmpOptionsHtml = $emp.html();

        if ($.fn.select2) {
            $('#assignRoleId').select2({ width: '100%', minimumResultsForSearch: 8 });
            $('#assignDeptId').select2({ width: '100%', placeholder: 'Select Department' });
        }

        var initialDept = $('#assignDeptId').val() || '';
        var initialEmp = $emp.val() || '';
        rebuildEmployeeOptions(initialDept, initialEmp);

        $('#assignDeptId').on('change', function () {
            rebuildEmployeeOptions($(this).val() || '', '');
            $('#assignUsername').val('').data('auto', 1);
        });

        $(document).on('change', '#assignEmpId', function () {
            var $opt = $(this).find('option:selected');
            var code = $opt.data('code') || $opt.attr('data-code') || '';
            var $user = $('#assignUsername');
            if ($user.length && (!$user.val() || $user.data('auto') === 1)) {
                $user.val(String(code || '').toUpperCase()).data('auto', 1);
            }
            var already = ($opt.text() || '').indexOf('already has login') !== -1;
            var $pass = $('#assignPassword');
            var $req = $('#assignPassReq');
            var $hint = $('#assignPassHint');
            if ($pass.length && !$pass.closest('form').find('input[name="user_id"]').val()) {
                if (already) {
                    $pass.prop('required', false);
                    if ($req.length) $req.hide();
                    if ($hint.length) {
                        $hint.text('Employee already has login — password optional; role will be updated on save');
                    }
                } else {
                    $pass.prop('required', true);
                    if ($req.length) $req.show();
                    if ($hint.length) {
                        $hint.text('Required for new login');
                    }
                }
            }
        });

        $('#assignUsername').on('input', function () {
            $(this).data('auto', 0);
        });
    });
})(jQuery);
