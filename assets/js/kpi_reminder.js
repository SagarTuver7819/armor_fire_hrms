/**
 * Global KPI hour reminder — any employee page (shift-wise)
 * Skips when already on My KPI fill form (local reminder handles that page).
 */
(function () {
    'use strict';

    var cfg = window.KPI_REMINDER || null;
    if (!cfg || !cfg.dueUrl || !cfg.hourUrl) return;

    // Local KPI page already has its own hour modal
    if (document.getElementById('kpiFillForm') || document.getElementById('kpiHourModal')) {
        return;
    }

    var reminded = {};
    var activeSlot = null;
    var sheetId = 0;
    var busy = false;
    var modal = null;

    function storageKey(date, slot) {
        return 'kpi_remind_' + String(date || '') + '_' + String(slot);
    }

    function wasDismissed(date, slot) {
        try {
            var raw = sessionStorage.getItem(storageKey(date, slot));
            if (!raw) return false;
            var ts = parseInt(raw, 10) || 0;
            // Re-remind after 10 minutes if still pending
            return (Date.now() - ts) < 10 * 60 * 1000;
        } catch (e) {
            return !!reminded[slot];
        }
    }

    function markDismissed(date, slot) {
        reminded[slot] = true;
        try {
            sessionStorage.setItem(storageKey(date, slot), String(Date.now()));
        } catch (e) { /* ignore */ }
    }

    function ensureModal() {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.className = 'kpi-hour-modal';
        modal.id = 'kpiGlobalHourModal';
        modal.hidden = true;
        modal.innerHTML =
            '<div class="kpi-hour-modal-backdrop" data-kpi-g-close></div>' +
            '<div class="kpi-hour-modal-box" role="dialog" aria-modal="true">' +
            '  <div class="kpi-hour-modal-icon"><i class="fa-solid fa-clock"></i></div>' +
            '  <h3 id="kpiGTitle">Hour complete</h3>' +
            '  <p id="kpiGMsg">Aa hour nu activity fill kari Submit karo.</p>' +
            '  <textarea id="kpiGText" class="form-control" rows="3" placeholder="Enter work done in this hour…"></textarea>' +
            '  <div class="kpi-hour-modal-actions">' +
            '    <button type="button" class="btn-secondary" data-kpi-g-close>Later</button>' +
            '    <a class="btn-secondary" id="kpiGOpenPage" href="' + String(cfg.kpiUrl || '#') + '">Open KPI</a>' +
            '    <button type="button" class="btn-primary" id="kpiGSubmit"><i class="fa-solid fa-paper-plane"></i> Submit Hour</button>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(modal);

        modal.querySelectorAll('[data-kpi-g-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                if (activeSlot && sheetId) {
                    markDismissed(cfg.today || '', activeSlot.slot_index);
                }
                closeModal();
            });
        });

        var submitBtn = document.getElementById('kpiGSubmit');
        if (submitBtn) {
            submitBtn.addEventListener('click', submitHour);
        }
        return modal;
    }

    function openModal(slot) {
        ensureModal();
        activeSlot = slot;
        var title = document.getElementById('kpiGTitle');
        var msg = document.getElementById('kpiGMsg');
        var text = document.getElementById('kpiGText');
        if (title) title.textContent = 'Hour ' + (slot.label || (slot.time_from + ' – ' + slot.time_to)) + ' complete';
        if (msg) msg.textContent = 'Aa hour nu KPI activity fill kari Submit karo. (Shift-wise reminder)';
        if (text) {
            text.value = slot.activity_text || '';
            text.focus();
        }
        modal.hidden = false;
        document.body.classList.add('kpi-hour-modal-open');
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('kpi-hour-modal-open');
        activeSlot = null;
    }

    function submitHour() {
        if (!activeSlot || !sheetId || busy) return;
        var textEl = document.getElementById('kpiGText');
        var activity = textEl ? String(textEl.value || '').trim() : '';
        if (!activity) {
            alert('Please enter activity for this hour before Submit.');
            if (textEl) textEl.focus();
            return;
        }

        busy = true;
        var btn = document.getElementById('kpiGSubmit');
        if (btn) btn.disabled = true;

        var body = new FormData();
        body.append('sheet_id', String(sheetId));
        body.append('slot_index', String(activeSlot.slot_index));
        body.append('activity', activity);

        fetch(cfg.hourUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                busy = false;
                if (btn) btn.disabled = false;
                if (!data || !data.ok) {
                    alert((data && data.error) ? data.error : 'Hour submit failed.');
                    return;
                }
                markDismissed(cfg.today || '', activeSlot.slot_index);
                closeModal();
                // Check next due hour shortly
                setTimeout(checkDue, 800);
            })
            .catch(function () {
                busy = false;
                if (btn) btn.disabled = false;
                alert('Network error. Please try again.');
            });
    }

    function checkDue() {
        if (busy || (modal && !modal.hidden)) return;

        fetch(cfg.dueUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || data.submitted) return;
                sheetId = parseInt(data.sheet_id, 10) || 0;
                cfg.today = data.kpi_date || cfg.today;
                if (data.kpi_url) {
                    var link = document.getElementById('kpiGOpenPage');
                    if (link) link.href = data.kpi_url;
                }
                var slots = data.slots || [];
                for (var i = 0; i < slots.length; i++) {
                    var s = slots[i];
                    var idx = parseInt(s.slot_index, 10);
                    if (!idx || wasDismissed(cfg.today, idx) || reminded[idx]) continue;
                    openModal(s);
                    break;
                }
            })
            .catch(function () { /* silent */ });
    }

    // First check soon after load, then every 60s
    setTimeout(checkDue, 2500);
    setInterval(checkDue, 60 * 1000);
})();
