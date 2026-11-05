(function () {
    if (window.toastr) {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3500
        };
        if (window.SOP_TOAST_MSG) {
            toastr.success(window.SOP_TOAST_MSG);
        }
    }

    if (window.jQuery && jQuery.fn.DataTable && document.getElementById('sopTable')) {
        jQuery('#sopTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[2, 'desc']],
            columnDefs: [
                { orderable: false, targets: [0, 5, 6] },
                { searchable: false, targets: [0, 5, 6] }
            ],
            language: {
                search: 'Search:',
                lengthMenu: 'Show _MENU_',
                info: 'Showing _START_ to _END_ of _TOTAL_ SOP',
                infoEmpty: 'No SOP',
                zeroRecords: 'No matching SOP found',
                paginate: { previous: 'Prev', next: 'Next' }
            }
        });
    }

    document.querySelectorAll('.js-sop-share').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url') || '';
            var title = btn.getAttribute('data-title') || 'SOP';
            var text = title + '\n' + url;
            if (navigator.share) {
                navigator.share({ title: title, text: title, url: url }).catch(function () {});
                return;
            }
            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
        });
    });
})();
