/**
 * AlagApp Toast Notification System
 * Replaces browser alert() calls with polished in-app notifications.
 *
 * Usage:
 *   window.showToast('Message', 'success' | 'error' | 'info' | 'warning', durationMs?)
 *   window.showNotification(msg, type, durationMs?)   (alias)
 *
 * Also overrides window.alert() so legacy calls automatically become toasts.
 */
(function () {
    'use strict';

    if (window.__AlagAppToastLoaded) {
        return;
    }
    window.__AlagAppToastLoaded = true;

    // Inject styles once
    var style = document.createElement('style');
    style.textContent = [
        '#alag-toast-container{position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;max-width:380px;pointer-events:none;}',
        '.alag-toast{pointer-events:auto;min-width:280px;max-width:380px;background:#fff;border-left:5px solid #d03664;border-radius:10px;box-shadow:0 12px 30px rgba(0,0,0,.15);padding:14px 18px 14px 46px;color:#333;font-family:"Source Sans Pro",Inter,system-ui,sans-serif;font-size:.95rem;position:relative;opacity:0;transform:translateX(120%);transition:transform .35s ease,opacity .35s ease;}',
        '.alag-toast.show{opacity:1;transform:translateX(0);}',
        '.alag-toast.leave{opacity:0;transform:translateX(120%);}',
        '.alag-toast::before{content:"";position:absolute;left:14px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:50%;background:#d03664;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:14px;line-height:22px;text-align:center;}',
        '.alag-toast.success{border-left-color:#10B981;}',
        '.alag-toast.success::before{content:"\\2713";background:#10B981;}',
        '.alag-toast.error{border-left-color:#EF4444;}',
        '.alag-toast.error::before{content:"!";background:#EF4444;}',
        '.alag-toast.warning{border-left-color:#F59E0B;}',
        '.alag-toast.warning::before{content:"!";background:#F59E0B;}',
        '.alag-toast.info{border-left-color:#3B82F6;}',
        '.alag-toast.info::before{content:"i";background:#3B82F6;font-style:italic;}',
        '.alag-toast .alag-toast-close{position:absolute;top:6px;right:8px;background:transparent;border:none;color:#9ca3af;font-size:18px;cursor:pointer;line-height:1;}',
        '.alag-toast .alag-toast-close:hover{color:#374151;}',
        '.alag-toast .alag-toast-title{font-weight:600;margin-bottom:2px;color:#111827;}',
        '.alag-toast .alag-toast-body{color:#4b5563;white-space:pre-line;}'
    ].join('');
    document.head.appendChild(style);

    function ensureContainer() {
        var c = document.getElementById('alag-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'alag-toast-container';
            document.body.appendChild(c);
        }
        return c;
    }

    function typeFor(type) {
        var t = (type || '').toString().toLowerCase();
        if (t === 'success' || t === 'ok' || t === 'done') return 'success';
        if (t === 'error' || t === 'danger' || t === 'fail') return 'error';
        if (t === 'warning' || t === 'warn') return 'warning';
        return 'info';
    }

    function titleFor(type) {
        switch (type) {
            case 'success': return 'Success';
            case 'error':   return 'Error';
            case 'warning': return 'Warning';
            default:        return 'Notice';
        }
    }

    function showToast(message, type, durationMs) {
        if (!message && message !== 0) return;
        var t = typeFor(type);
        var container = ensureContainer();
        var toast = document.createElement('div');
        toast.className = 'alag-toast ' + t;

        var title = document.createElement('div');
        title.className = 'alag-toast-title';
        title.textContent = titleFor(t);

        var body = document.createElement('div');
        body.className = 'alag-toast-body';
        body.textContent = String(message);

        var close = document.createElement('button');
        close.className = 'alag-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () { dismiss(toast); });

        toast.appendChild(close);
        toast.appendChild(title);
        toast.appendChild(body);
        container.appendChild(toast);

        // animate in
        requestAnimationFrame(function () { toast.classList.add('show'); });

        var dur = typeof durationMs === 'number' ? durationMs : 4200;
        var timer = setTimeout(function () { dismiss(toast); }, dur);

        toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
        toast.addEventListener('mouseleave', function () { timer = setTimeout(function () { dismiss(toast); }, 1500); });

        return toast;
    }

    function dismiss(toast) {
        if (!toast || !toast.parentNode) return;
        toast.classList.add('leave');
        setTimeout(function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 350);
    }

    // Public API
    window.showToast = showToast;

    // Preserve any existing showNotification (e.g., admin-dashboard has its own)
    // but override with the toast version for a consistent look everywhere.
    window.showNotification = function (message, type, durationMs) {
        return showToast(message, type, durationMs);
    };

    // Override alert() so legacy alert('...') still works visually.
    var nativeAlert = window.alert;
    window.alert = function (msg) {
        try {
            showToast(msg, 'info');
        } catch (e) {
            if (typeof nativeAlert === 'function') nativeAlert(msg);
        }
    };
    window.nativeAlert = nativeAlert;
})();