/**
 * Policy form — department multi-select (Select2 + checkboxes)
 * Must load AFTER jQuery + select2.
 */
(function ($) {
    'use strict';

    if (!($ && $.fn && $.fn.select2)) {
        return;
    }

    function initPolicyDeptSelect() {
        var $sel = $('#circDeptSelect');
        var form = document.getElementById('policyForm');
        if (!$sel.length || !form) {
            return;
        }

        if ($sel.data('select2')) {
            try { $sel.select2('destroy'); } catch (e) { /* ignore */ }
        }

        function currentVals() {
            return ($sel.val() || []).map(String);
        }

        function isSelected(id) {
            return currentVals().indexOf(String(id)) !== -1;
        }

        function fmtResult(item) {
            if (!item.id) {
                return item.text;
            }
            var checked = isSelected(item.id);
            var isAll = String(item.id) === 'all';
            var $row = $(
                '<span class="circ-dept-opt' + (isAll ? ' circ-dept-opt-all' : '') + '">' +
                '<span class="circ-dept-check' + (checked ? ' is-checked' : '') + '" aria-hidden="true"></span>' +
                '<span class="circ-dept-opt-text"></span>' +
                '</span>'
            );
            $row.find('.circ-dept-opt-text').text(item.text);
            $row.attr('data-dept-id', String(item.id));
            return $row;
        }

        function fmtSelection(item) {
            if (!item.id) {
                return item.text;
            }
            if (String(item.id) === 'all') {
                return $('<span class="circ-dept-chip-all"><i class="fa-solid fa-check-double"></i> All Departments</span>');
            }
            return item.text;
        }

        function syncDropdownChecks() {
            var vals = currentVals();
            $('.select2-results__option').each(function () {
                var $li = $(this);
                var data = $li.data('data');
                var id = data && data.id != null ? String(data.id) : null;
                if (!id) {
                    var $mark = $li.find('[data-dept-id]').first();
                    if ($mark.length) {
                        id = String($mark.attr('data-dept-id'));
                    }
                }
                if (!id) {
                    return;
                }
                $li.find('.circ-dept-check').toggleClass('is-checked', vals.indexOf(id) !== -1);
            });
        }

        try {
            $sel.select2({
                width: '100%',
                placeholder: $sel.data('placeholder') || 'Search & select departments…',
                allowClear: false,
                closeOnSelect: false,
                dropdownParent: $(document.body),
                templateResult: fmtResult,
                templateSelection: fmtSelection,
                language: {
                    noResults: function () { return 'No matching department'; },
                    searching: function () { return 'Searching…'; }
                }
            });
        } catch (err) {
            return;
        }

        $sel.on('select2:open', function () {
            setTimeout(function () {
                $('.select2-dropdown').last().addClass('circ-dept-dropdown');
                syncDropdownChecks();
            }, 0);
        });

        function setAll() {
            $sel.val(['all']).trigger('change');
        }

        function clearAll() {
            $sel.val(null).trigger('change');
        }

        $sel.off('change.policyDept select2:select.policyDept select2:unselect.policyDept')
            .on('change.policyDept select2:select.policyDept select2:unselect.policyDept', function (ev) {
                var vals = currentVals();
                if (vals.length) {
                    var hasAll = vals.indexOf('all') !== -1;
                    var others = vals.filter(function (v) { return v !== 'all'; });
                    if (hasAll && others.length) {
                        var picked = ev && ev.params && ev.params.data ? String(ev.params.data.id) : '';
                        if (picked === 'all') {
                            $sel.val(['all']).trigger('change.select2');
                            vals = ['all'];
                        } else {
                            $sel.val(others).trigger('change.select2');
                            vals = others;
                        }
                    }
                }
                $('.circ-dept-select-wrap').toggleClass('has-value', vals.length > 0);
                syncDropdownChecks();
            });

        $('#circSelectAllBtn, #circSelectAllLink').off('click.policyDept').on('click.policyDept', function (e) {
            e.preventDefault();
            setAll();
            $sel.select2('close');
        });
        $('#circClearDeptBtn').off('click.policyDept').on('click.policyDept', function (e) {
            e.preventDefault();
            clearAll();
        });

        $('.circ-dept-select-wrap').toggleClass('has-value', currentVals().length > 0);

        $(form).off('submit.policyDept').on('submit.policyDept', function (e) {
            var title = document.getElementById('policyTitle');
            var pdf = document.getElementById('policyPdf');
            if (title) {
                var t = String(title.value || '').trim();
                if (t === '') {
                    e.preventDefault();
                    title.focus();
                    alert('Please enter Policy Title.');
                    return false;
                }
                title.value = t;
            }
            var vals = currentVals();
            if (!vals.length) {
                e.preventDefault();
                alert('Select All Departments or at least one department.');
                $sel.select2('open');
                return false;
            }
            if (pdf && pdf.files && pdf.files[0] && pdf.files[0].size > 10 * 1024 * 1024) {
                e.preventDefault();
                alert('PDF must be under 10 MB. Please compress or re-scan.');
                return false;
            }
        });
    }

    $(function () {
        initPolicyDeptSelect();
    });
})(window.jQuery);
