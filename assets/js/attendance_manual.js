/**
 * Department-wise manual attendance helpers
 */
(function ($) {
    function shiftTimesFromSelect() {
        var $opt = $('#shiftSelect option:selected');
        return {
            inn: ($opt.data('in') || window.ATT_DEFAULT_IN || '').toString(),
            out: ($opt.data('out') || window.ATT_DEFAULT_OUT || '').toString()
        };
    }

    function applyShiftToRow($row, inn, out) {
        var status = $row.find('.row-status').val();
        if (status === 'Present' || status === 'Half Day') {
            if (inn) {
                $row.find('.row-in').val(inn);
            }
            if (out) {
                $row.find('.row-out').val(out);
            }
        }
    }

    $(function () {
        if (window.ATT_TOAST && typeof toastr !== 'undefined') {
            toastr.success(window.ATT_TOAST);
        }

        $('#checkAll').on('change', function () {
            $('.row-include').prop('checked', this.checked);
        });

        $('#btnApplyShift').on('click', function () {
            var t = shiftTimesFromSelect();
            $('.manual-att-row').each(function () {
                applyShiftToRow($(this), t.inn, t.out);
            });
            if (typeof toastr !== 'undefined') {
                toastr.info('Shift In/Out applied to Present / Half Day rows.');
            }
        });

        $('#btnMarkPresent').on('click', function () {
            var t = shiftTimesFromSelect();
            $('.manual-att-row').each(function () {
                var $row = $(this);
                $row.find('.row-include').prop('checked', true);
                $row.find('.row-status').val('Present');
                applyShiftToRow($row, t.inn, t.out);
            });
        });

        $('#manualAttTable').on('change', '.row-status', function () {
            var $row = $(this).closest('tr');
            var status = $(this).val();
            var t = shiftTimesFromSelect();
            if (status === 'Present' || status === 'Half Day') {
                if (!$row.find('.row-in').val()) {
                    $row.find('.row-in').val(t.inn);
                }
                if (!$row.find('.row-out').val()) {
                    $row.find('.row-out').val(t.out);
                }
            } else {
                $row.find('.row-in').val('');
                $row.find('.row-out').val('');
            }
        });

        $('#manualAttForm').on('submit', function () {
            var n = $('.row-include:checked').length;
            if (!n) {
                alert('Select at least one employee.');
                return false;
            }
            return confirm('Save manual attendance for ' + n + ' employee(s)?');
        });
    });
})(jQuery);
