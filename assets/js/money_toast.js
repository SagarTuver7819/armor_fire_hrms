(function () {
    if (!window.toastr || !window.MONEY_TOAST_MSG) {
        return;
    }
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 3500
    };
    if (window.MONEY_TOAST_TYPE === 'error') {
        toastr.error(window.MONEY_TOAST_MSG);
    } else {
        toastr.success(window.MONEY_TOAST_MSG);
    }
})();
