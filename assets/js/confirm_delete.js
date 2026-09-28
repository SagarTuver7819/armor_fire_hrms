/**
 * Theme delete confirmation modal (Armor Fire)
 * - .btn-delete[href] → navigate after confirm
 * - window.ConfirmDelete.ask(message, onConfirm, options)
 */
(function () {
    'use strict';

    function ensureModal() {
        var modal = document.getElementById('confirmDeleteModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'confirmDeleteModal';
        modal.className = 'confirm-modal';
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-labelledby', 'confirmDeleteTitle');
        modal.innerHTML =
            '<div class="confirm-modal-backdrop"></div>' +
            '<div class="confirm-modal-box">' +
            '  <div class="confirm-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
            '  <h3 id="confirmDeleteTitle">Are you sure?</h3>' +
            '  <p class="confirm-modal-msg">Are you sure you want to delete this record?</p>' +
            '  <div class="confirm-modal-actions">' +
            '    <button type="button" class="btn-secondary confirm-cancel">Cancel</button>' +
            '    <button type="button" class="btn-primary confirm-yes"><i class="fa-solid fa-trash-can"></i> Yes, Delete</button>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(modal);
        return modal;
    }

    var modal = ensureModal();
    var titleEl = modal.querySelector('#confirmDeleteTitle');
    var msgEl = modal.querySelector('.confirm-modal-msg');
    var yesBtn = modal.querySelector('.confirm-yes');
    var cancelBtn = modal.querySelector('.confirm-cancel');
    var backdrop = modal.querySelector('.confirm-modal-backdrop');
    var pendingHref = null;
    var pendingCb = null;
    var defaultTitle = 'Are you sure?';
    var defaultYesHtml = '<i class="fa-solid fa-trash-can"></i> Yes, Delete';

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('confirm-modal-open');
        pendingHref = null;
        pendingCb = null;
        if (titleEl) titleEl.textContent = defaultTitle;
        if (yesBtn) yesBtn.innerHTML = defaultYesHtml;
        var iconEl = modal.querySelector('.confirm-modal-icon i');
        if (iconEl) iconEl.className = 'fa-solid fa-triangle-exclamation';
    }

    function openModal(opts) {
        opts = opts || {};
        pendingHref = opts.href || null;
        pendingCb = typeof opts.onConfirm === 'function' ? opts.onConfirm : null;

        if (titleEl) titleEl.textContent = opts.title || defaultTitle;
        if (msgEl) msgEl.textContent = opts.message || 'Are you sure you want to delete this record?';
        if (yesBtn) {
            if (opts.yesHtml) {
                yesBtn.innerHTML = opts.yesHtml;
            } else {
                yesBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> ' + (opts.yesLabel || 'Yes, Delete');
            }
        }
        var iconEl = modal.querySelector('.confirm-modal-icon i');
        if (iconEl) {
            iconEl.className = opts.iconClass || 'fa-solid fa-triangle-exclamation';
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('confirm-modal-open');
        if (yesBtn) yesBtn.focus();
    }

    window.ConfirmDelete = {
        ask: function (message, onConfirm, options) {
            options = options || {};
            openModal({
                message: message || 'You Want To Delete This Row?',
                onConfirm: onConfirm,
                title: options.title || 'Delete Row?',
                yesLabel: options.yesLabel || 'Yes, Delete',
                yesHtml: options.yesHtml || null,
                iconClass: options.iconClass || null,
                href: null
            });
        }
    };

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-delete');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var name = btn.getAttribute('data-name') || 'this record';
        var href = btn.getAttribute('href');
        if (!href) return;

        openModal({
            href: href,
            message: 'Are you sure you want to delete "' + name + '"? This action cannot be undone.',
            title: 'Are you sure?',
            yesLabel: 'Yes, Delete'
        });
    }, true);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('a.logout-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var href = btn.getAttribute('href');
        if (!href) return;

        openModal({
            href: href,
            title: 'Logout?',
            message: 'Are you sure you want to Logout?',
            yesHtml: '<i class="fa-solid fa-right-from-bracket"></i> Yes, Logout',
            iconClass: 'fa-solid fa-right-from-bracket'
        });
    }, true);

    if (yesBtn) {
        yesBtn.addEventListener('click', function () {
            var href = pendingHref;
            var cb = pendingCb;
            closeModal();
            if (href) {
                window.location.href = href;
            } else if (typeof cb === 'function') {
                cb();
            }
        });
    }
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
