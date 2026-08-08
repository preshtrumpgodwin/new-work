/**
 * Zetaphase EduCloud — shared UI helpers (assets/js/ui.js)
 * Toasts, a themed confirm dialog (replacement for native confirm()),
 * and a small empty-state renderer. Pure vanilla JS, no build step,
 * uses the existing CSS custom properties (--bg-secondary, --border-color,
 * --text-primary, --text-secondary, --brand-color) so it automatically
 * matches whichever school's theme/light-dark mode is active.
 *
 * Usage:
 *   <script src="assets/js/ui.js"></script>
 *   ZUI.toast('Saved successfully', 'success');
 *   const ok = await ZUI.confirm('Delete this record?', { danger: true });
 *   if (ok) { ...proceed... }
 *
 * Opt-in, zero-risk adoption for existing pages:
 *   Add data-confirm="Are you sure?" to any <button> or <form> and this
 *   file will intercept the click/submit, show the themed dialog, and
 *   only let the action through if the user confirms — a drop-in
 *   replacement for onclick="return confirm('...')" wherever a page
 *   is updated to use it. Existing native confirm() calls are untouched
 *   and continue to work exactly as before until a page opts in.
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------
    // Toasts
    // ---------------------------------------------------------------
    let toastContainer = null;

    function ensureToastContainer() {
        if (toastContainer && document.body.contains(toastContainer)) return toastContainer;
        toastContainer = document.createElement('div');
        toastContainer.className = 'zui-toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        toastContainer.setAttribute('aria-atomic', 'true');
        document.body.appendChild(toastContainer);
        return toastContainer;
    }

    const TOAST_ICONS = {
        success: '<path d="M20 6 9 17l-5-5"/>',
        error: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'
    };

    /**
     * @param {string} message
     * @param {'success'|'error'|'warning'|'info'} type
     * @param {number} durationMs  auto-dismiss after this long (0 = sticky)
     */
    function toast(message, type, durationMs) {
        type = TOAST_ICONS[type] ? type : 'info';
        durationMs = typeof durationMs === 'number' ? durationMs : 4500;

        const container = ensureToastContainer();
        const el = document.createElement('div');
        el.className = 'zui-toast zui-toast-' + type;
        el.innerHTML =
            '<svg class="zui-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            TOAST_ICONS[type] +
            '</svg>' +
            '<span class="zui-toast-msg"></span>' +
            '<button type="button" class="zui-toast-close" aria-label="Dismiss">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
            '</button>';
        el.querySelector('.zui-toast-msg').textContent = message;

        function dismiss() {
            el.classList.add('zui-toast-out');
            setTimeout(() => el.remove(), 200);
        }
        el.querySelector('.zui-toast-close').addEventListener('click', dismiss);
        container.appendChild(el);
        requestAnimationFrame(() => el.classList.add('zui-toast-in'));

        if (durationMs > 0) setTimeout(dismiss, durationMs);
        return dismiss;
    }

    // ---------------------------------------------------------------
    // Themed confirm dialog (Promise-based)
    // ---------------------------------------------------------------
    function confirmDialog(message, opts) {
        opts = opts || {};
        const title = opts.title || 'Please confirm';
        const confirmLabel = opts.confirmLabel || 'Confirm';
        const cancelLabel = opts.cancelLabel || 'Cancel';
        const danger = !!opts.danger;

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'zui-dialog-overlay';
            overlay.innerHTML =
                '<div class="zui-dialog" role="alertdialog" aria-modal="true">' +
                '  <div class="zui-dialog-title"></div>' +
                '  <div class="zui-dialog-msg"></div>' +
                '  <div class="zui-dialog-actions">' +
                '    <button type="button" class="zui-dialog-btn zui-dialog-cancel"></button>' +
                '    <button type="button" class="zui-dialog-btn ' + (danger ? 'zui-dialog-danger' : 'zui-dialog-confirm') + '"></button>' +
                '  </div>' +
                '</div>';

            overlay.querySelector('.zui-dialog-title').textContent = title;
            overlay.querySelector('.zui-dialog-msg').textContent = message;
            overlay.querySelector('.zui-dialog-cancel').textContent = cancelLabel;
            overlay.querySelector('.zui-dialog-confirm, .zui-dialog-danger').textContent = confirmLabel;

            function close(result) {
                overlay.classList.add('zui-dialog-out');
                setTimeout(() => overlay.remove(), 150);
                document.removeEventListener('keydown', onKey);
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') close(false);
                if (e.key === 'Enter') close(true);
            }

            overlay.querySelector('.zui-dialog-cancel').addEventListener('click', () => close(false));
            overlay.querySelector('.zui-dialog-confirm, .zui-dialog-danger').addEventListener('click', () => close(true));
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
            document.addEventListener('keydown', onKey);

            document.body.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('zui-dialog-in'));
            overlay.querySelector('.zui-dialog-confirm, .zui-dialog-danger').focus();
        });
    }

    // Opt-in auto-wiring for data-confirm="..." on buttons/forms
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-confirm]');
        if (!el || el.__zuiConfirmed) return;
        e.preventDefault();
        e.stopPropagation();
        const msg = el.getAttribute('data-confirm');
        const danger = el.hasAttribute('data-confirm-danger');
        confirmDialog(msg, { danger: danger }).then((ok) => {
            if (!ok) return;
            el.__zuiConfirmed = true;
            if (el.tagName === 'BUTTON' && el.form) {
                el.form.requestSubmit ? el.form.requestSubmit(el) : el.form.submit();
            } else if (el.tagName === 'A') {
                window.location.href = el.href;
            } else {
                el.click();
            }
            el.__zuiConfirmed = false;
        });
    }, true);

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.hasAttribute('data-confirm') || form.__zuiConfirmed) return;
        e.preventDefault();
        const msg = form.getAttribute('data-confirm');
        const danger = form.hasAttribute('data-confirm-danger');
        confirmDialog(msg, { danger: danger }).then((ok) => {
            if (!ok) return;
            form.__zuiConfirmed = true;
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    }, true);

    // ---------------------------------------------------------------
    // Empty-state renderer
    // ---------------------------------------------------------------
    function emptyState(el, opts) {
        opts = opts || {};
        const icon = opts.icon || 'inbox';
        const title = opts.title || 'Nothing here yet';
        const message = opts.message || '';
        el.innerHTML =
            '<div class="zui-empty-state">' +
            '  <i data-lucide="' + icon + '" class="zui-empty-icon"></i>' +
            '  <div class="zui-empty-title"></div>' +
            (message ? '  <div class="zui-empty-msg"></div>' : '') +
            '</div>';
        el.querySelector('.zui-empty-title').textContent = title;
        if (message) el.querySelector('.zui-empty-msg').textContent = message;
        if (window.lucide) window.lucide.createIcons();
    }

    window.ZUI = { toast: toast, confirm: confirmDialog, emptyState: emptyState };
})();
