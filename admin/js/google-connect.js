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

    var switchLink = document.getElementById('aeo-google-switch-account');
    var popup = null;
    var pollTimer = null;
    var isConnecting = false;
    var handledConnection = false;

    btn.addEventListener('click', function () {
        openConnectPopup(aeocasGoogle.connectUrl, aeocasGoogle.i18n.waiting);
    });

    if (switchLink) {
        switchLink.addEventListener('click', function (event) {
            event.preventDefault();
            openConnectPopup(aeocasGoogle.switchUrl || aeocasGoogle.connectUrl, aeocasGoogle.i18n.waitingSwitch || aeocasGoogle.i18n.waiting);
        });
    }

    function openConnectPopup(url, waitingText) {
        handledConnection = false;
        isConnecting = true;
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        var w = 500;
        var h = 650;
        var left = Math.round((screen.width - w) / 2);
        var top = Math.round((screen.height - h) / 2);

        popup = window.open(
            url,
            'aeocas_google',
            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
            ',toolbar=no,menubar=no,scrollbars=yes'
        );

        if (!popup) {
            isConnecting = false;
            setStatus(aeocasGoogle.i18n.error, 'error');
            return;
        }

        setStatus(waitingText, 'loading');

        // Detect popup closed without completing.
        pollTimer = setInterval(function () {
            if (popup && popup.closed) {
                clearInterval(pollTimer);
                pollTimer = null;
                popup = null;
                isConnecting = false;
                var el = document.getElementById('aeo-google-status');
                if (el && el.className.indexOf('success') === -1) {
                    el.style.display = 'none';
                }
            }
        }, 500);
    }

    // Listen for postMessage from the platform popup.
    window.addEventListener('message', function (event) {
        if (event.origin !== aeocasGoogle.accountOrigin) return;
        if (!isConnecting || handledConnection || !popup || popup.closed) return;

        var data = event.data;
        if (!data || data.type !== 'aeocas_connected') return;

        handledConnection = true;
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
        if (popup && !popup.closed) popup.close();
        popup = null;
        isConnecting = false;

        setStatus(aeocasGoogle.i18n.connecting, 'loading');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', aeocasGoogle.ajaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            var resp;
            try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = {}; }

            if (resp.success) {
                setStatus(aeocasGoogle.i18n.success, 'success');
                var redirectUrl = resp.data && resp.data.redirect_url;
                setTimeout(function () {
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        window.location.reload();
                    }
                }, 600);
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
