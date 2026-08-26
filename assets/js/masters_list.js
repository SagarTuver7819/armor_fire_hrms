/**
 * Masters list - Server-side DataTable
 */
(function () {
    if (window.toastr) {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3500
        };
        if (window.MASTER_TOAST_MSG) {
            if (window.MASTER_TOAST_TYPE === 'error') {
                toastr.error(window.MASTER_TOAST_MSG);
            } else {
                toastr.success(window.MASTER_TOAST_MSG);
            }
        }
    }

    if (!(window.jQuery && jQuery.fn.DataTable && document.getElementById('mastersTable'))) {
        return;
    }

    var actionCol = typeof window.MASTER_ACTION_COL === 'number' ? window.MASTER_ACTION_COL : 0;
    var label = window.MASTER_LABEL || 'record';

    jQuery('#mastersTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        ajax: {
            url: window.MASTER_AJAX_URL,
            type: 'POST'
        },
        columnDefs: [
            { orderable: false, targets: [0, actionCol] },
            { searchable: false, targets: [0, actionCol] }
        ],
        language: {
            processing: '<div class="dt-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>',
            search: 'Search:',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_',
            infoEmpty: 'No records',
            zeroRecords: 'No matching records found',
            paginate: { previous: 'Prev', next: 'Next' }
        }
    });
})();
