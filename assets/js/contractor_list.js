/**
 * Contractor generic DataTable (#contractorTable)
 */
(function () {
    if (window.toastr) {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3500
        };
        if (window.C_TOAST_MSG) {
            toastr.success(window.C_TOAST_MSG);
        }
    }

    if (!(window.jQuery && jQuery.fn.DataTable && document.getElementById('contractorTable'))) {
        return;
    }

    var actionCol = typeof window.C_ACTION_COL === 'number' ? window.C_ACTION_COL : 0;

    jQuery('#contractorTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        ajax: {
            url: window.C_AJAX_URL,
            type: 'POST',
            data: function (d) {
                if (typeof window.C_AJAX_EXTRA === 'function') {
                    var extra = window.C_AJAX_EXTRA() || {};
                    Object.keys(extra).forEach(function (k) {
                        d[k] = extra[k];
                    });
                }
            }
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
