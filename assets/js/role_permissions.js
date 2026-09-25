/**
 * Role permissions matrix — column/row select-all helpers
 */
(function ($) {
    'use strict';

    function syncRowAll($row) {
        var $checks = $row.find('.perm-check');
        var allOn = $checks.length > 0 && $checks.filter(':checked').length === $checks.length;
        $row.find('.perm-row-all').prop('checked', allOn);
    }

    function syncColAll(action) {
        var $checks = $('#permMatrixTable .perm-check[data-action="' + action + '"]');
        var allOn = $checks.length > 0 && $checks.filter(':checked').length === $checks.length;
        $('#permMatrixTable .perm-col-all[data-action="' + action + '"]').prop('checked', allOn);
    }

    function syncAllHeaders() {
        ['view', 'add', 'edit', 'delete'].forEach(syncColAll);
        $('#permMatrixTable tbody tr').each(function () {
            syncRowAll($(this));
        });
    }

    $(function () {
        if (!$('#permMatrixTable').length) {
            return;
        }

        syncAllHeaders();

        $('#permMatrixTable').on('change', '.perm-check', function () {
            var $row = $(this).closest('tr');
            var action = $(this).data('action');
            // Add/Edit/Delete imply View
            if (action !== 'view' && this.checked) {
                $row.find('.perm-check[data-action="view"]').prop('checked', true);
            }
            // Unchecking View clears others
            if (action === 'view' && !this.checked) {
                $row.find('.perm-check').prop('checked', false);
            }
            syncRowAll($row);
            syncColAll(action);
            if (action !== 'view') {
                syncColAll('view');
            }
        });

        $('#permMatrixTable').on('change', '.perm-row-all', function () {
            var on = this.checked;
            $(this).closest('tr').find('.perm-check').prop('checked', on);
            syncAllHeaders();
        });

        $('#permMatrixTable').on('change', '.perm-col-all', function () {
            var action = $(this).data('action');
            var on = this.checked;
            $('#permMatrixTable .perm-check[data-action="' + action + '"]').each(function () {
                $(this).prop('checked', on);
                if (on && action !== 'view') {
                    $(this).closest('tr').find('.perm-check[data-action="view"]').prop('checked', true);
                }
                if (!on && action === 'view') {
                    $(this).closest('tr').find('.perm-check').prop('checked', false);
                }
            });
            syncAllHeaders();
        });

        $('#permSelectAll').on('click', function () {
            $('#permMatrixTable .perm-check').prop('checked', true);
            syncAllHeaders();
        });

        $('#permClearAll').on('click', function () {
            $('#permMatrixTable .perm-check, #permMatrixTable .perm-row-all, #permMatrixTable .perm-col-all')
                .prop('checked', false);
        });
    });
})(jQuery);
