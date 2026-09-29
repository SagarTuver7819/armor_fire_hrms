/**
 * Related documents — thumbnail click → popup (image / PDF)
 */
(function () {
    'use strict';

    var box = document.getElementById('relatedDocLightbox');
    if (!box) return;

    var titleEl = document.getElementById('relatedDocLightboxTitle');
    var bodyEl = document.getElementById('relatedDocLightboxBody');
    var closeBtn = document.getElementById('relatedDocLightboxClose');

    function closeLightbox() {
        box.hidden = true;
        document.body.classList.remove('related-doc-lightbox-open');
        if (bodyEl) bodyEl.innerHTML = '';
    }

    function openLightbox(url, type, title) {
        if (!url || !bodyEl) return;
        if (titleEl) titleEl.textContent = title || 'Document';
        bodyEl.innerHTML = '';
        if (type === 'image') {
            var img = document.createElement('img');
            img.className = 'related-doc-lightbox-img';
            img.src = url;
            img.alt = title || 'Document';
            bodyEl.appendChild(img);
        } else {
            var frame = document.createElement('iframe');
            frame.className = 'related-doc-lightbox-frame';
            frame.src = url;
            frame.title = title || 'PDF';
            bodyEl.appendChild(frame);
        }
        box.hidden = false;
        document.body.classList.add('related-doc-lightbox-open');
    }

    document.addEventListener('click', function (e) {
        var thumb = e.target.closest('.related-doc-thumb[data-doc-url]');
        if (!thumb) return;
        e.preventDefault();
        openLightbox(
            thumb.getAttribute('data-doc-url') || '',
            thumb.getAttribute('data-doc-type') || 'pdf',
            thumb.getAttribute('data-doc-title') || 'Document'
        );
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var thumb = e.target.closest('.related-doc-thumb[data-doc-url]');
        if (!thumb) return;
        e.preventDefault();
        openLightbox(
            thumb.getAttribute('data-doc-url') || '',
            thumb.getAttribute('data-doc-type') || 'pdf',
            thumb.getAttribute('data-doc-title') || 'Document'
        );
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeLightbox);
    }
    box.addEventListener('click', function (e) {
        if (e.target === box) closeLightbox();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !box.hidden) closeLightbox();
    });
})();
