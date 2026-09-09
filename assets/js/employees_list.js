/**
 * employees/index.php - Server-side DataTable
 */
(function () {
    if (window.toastr) {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3500
        };
        if (window.EMP_TOAST_MSG) {
            toastr.success(window.EMP_TOAST_MSG);
        }
    }

    if (!(window.jQuery && jQuery.fn.DataTable && document.getElementById('employeesTable'))) {
        return;
    }

    var isAll = !!window.EMP_IS_ALL;
    // Column indexes for non-orderable PDF/Action (dynamically configured for active vs exit lists)
    var actionCols = window.EMP_ACTION_COLS || (isAll ? [9, 10] : [8, 9]);

    var table = jQuery('#employeesTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        ajax: {
            url: window.EMP_AJAX_URL,
            type: 'POST'
        },
        columnDefs: [
            { orderable: false, targets: actionCols },
            { searchable: false, targets: [0].concat(actionCols) }
        ],
        language: {
            processing: '<div class="dt-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading employees...</div>',
            search: 'Search:',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ employees',
            infoEmpty: 'No employees',
            zeroRecords: 'No matching employees found',
            paginate: { previous: 'Prev', next: 'Next' }
        },
        createdRow: function (row) {
            jQuery(row).addClass('emp-row');
            var href = jQuery(row).find('.emp-name-link').attr('href');
            if (href) {
                jQuery(row).attr('data-href', href);
            }
        }
    });

    jQuery('#employeesTable tbody').on('click', 'tr.emp-row', function (e) {
        if (jQuery(e.target).closest('a, button').length) return;
        var href = jQuery(this).attr('data-href');
        if (href) window.location.href = href;
    });
})();
    