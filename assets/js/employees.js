/**
 * Employees page scripts
 * - DataTable (search + pagination + sort)
 * - Toaster messages
 * - Row click → details
 * - Delete confirm
 */
(function () {
    // Toastr defaults
    if (window.toastr) {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3500,
            newestOnTop: true
        };

        if (window.EMP_TOAST_MSG) {
            var type = window.EMP_TOAST_TYPE || 'success';
            if (type === 'error') {
                toastr.error(window.EMP_TOAST_MSG);
            } else if (type === 'warning') {
                toastr.warning(window.EMP_TOAST_MSG);
            } else if (type === 'info') {
                toastr.info(window.EMP_TOAST_MSG);
            } else {
                toastr.success(window.EMP_TOAST_MSG);
            }
        }
    }

    // DataTable
    if (window.jQuery && jQuery.fn.DataTable && document.getElementById('employeesTable')) {
        var table = jQuery('#employeesTable').DataTable({
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [7, 8] }, // PDF + Action
                { searchable: false, targets: [0, 7, 8] }
            ],
            language: {
                search: 'Search:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ employees',
                infoEmpty: 'No employees available',
                zeroRecords: 'No matching employees found',
                paginate: {
                    previous: 'Prev',
                    next: 'Next'
                }
            }
        });

        // Re-number Sr. column after sort/filter/page
        table.on('order.dt search.dt draw.dt', function () {
            table.column(0, { search: 'applied', order: 'applied', page: 'applied' })
                .nodes()
                .each(function (cell, i) {
                    cell.innerHTML = i + 1;
                });
        }).draw();

        // Row click → open details (ignore clicks on links/buttons)
        jQuery('#employeesTable tbody').on('click', 'tr.emp-row', function (e) {
            if (jQuery(e.target).closest('a, button').length) {
                return;
            }
            var href = jQuery(this).data('href');
            if (href) {
                window.location.href = href;
            }
        });
    }
})();
