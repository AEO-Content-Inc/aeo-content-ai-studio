/**
 * AEO Content AI Studio - Google Connect
 *
 * Opens a popup to account.aeocontent.ai for Google sign-in.
 * Listens for a postMessage with the site_token on success.
 */
(function () {
    'use strict';

    var btn = document.getElementById('aeo-google-btn');
    if (!btn) return;

    var popup = null;
    var pollTimer = null;

    btn.addEventListener('click', function () {
        var w = 500;
        var h = 650;
        var left = Math.round((screen.width - w) / 2);
        var top = Math.round((screen.height - h) / 2);

        popup = window.open(
            aeocasGoogle.connectUrl,
            'aeocas_google',
            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
            ',toolbar=no,menubar=no,scrollbars=yes'
        );

        setStatus(aeocasGoogle.i18n.waiting, 'loading');

        // Detect popup closed without completing.
        pollTimer = setInterval(function () {
            if (popup && popup.closed) {
                clearInterval(pollTimer);
                var el = document.getElementById('aeo-google-status');
                if (el && el.className.indexOf('success') === -1) {
                    el.style.display = 'none';
                }
            }
        }, 500);
    });

    // Listen for postMessage from the platform popup.
    window.addEventListener('message', function (event) {
        if (event.origin !== aeocasGoogle.accountOrigin) return;

        var data = event.data;
        if (!data || data.type !== 'aeocas_connected') return;

        if (pollTimer) clearInterval(pollTimer);
        if (popup && !popup.closed) popup.close();

        setStatus(aeocasGoogle.i18n.connecting, 'loading');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', aeocasGoogle.ajaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            var resp;
            try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = {}; }

            if (resp.success) {
                setStatus(aeocasGoogle.i18n.success, 'success');
                setTimeout(function () { window.location.reload(); }, 600);
            } else {
                var msg = (resp.data && resp.data.message) || aeocasGoogle.i18n.error;
                setStatus(msg, 'error');
            }
        };
        xhr.onerror = function () {
            setStatus(aeocasGoogle.i18n.error, 'error');
        };

        xhr.send(
            'action=aeocas_google_connect' +
            '&nonce=' + encodeURIComponent(aeocasGoogle.nonce) +
            '&site_token=' + encodeURIComponent(data.site_token)
        );
    });

    function setStatus(text, type) {
        var el = document.getElementById('aeo-google-status');
        if (!el) return;
        el.textContent = text;
        el.className = 'aeo-google-status aeo-google-status-' + type;
        el.style.display = 'inline-block';
    }
})();
