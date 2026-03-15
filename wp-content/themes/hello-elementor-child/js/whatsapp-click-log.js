(function () {
  'use strict';

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  function buildPayload(button, config) {
    var payload = new URLSearchParams();
    payload.append('action', config.action || '');
    payload.append('nonce', config.nonce || '');
    payload.append('page_type', button.getAttribute('data-page-type') || 'generic');
    payload.append('post_id', button.getAttribute('data-post-id') || '0');
    payload.append('post_title', button.getAttribute('data-post-title') || '');
    payload.append('page_url', button.getAttribute('data-page-url') || window.location.href);
    payload.append('message_text', button.getAttribute('data-message-text') || '');
    payload.append('referrer', document.referrer || '');
    payload.append('user_agent', window.navigator.userAgent || '');

    return payload;
  }

  function sendLog(config, payload) {
    if (!config.ajax_url || !config.action || !config.nonce) {
      return;
    }

    if (typeof navigator.sendBeacon === 'function') {
      navigator.sendBeacon(config.ajax_url, payload);
      return;
    }

    fetch(config.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: payload.toString()
    }).catch(function () {
      return null;
    });
  }

  onReady(function () {
    var button = document.getElementById('floating-whatsapp');
    if (!button) {
      return;
    }

    var lastClickAt = 0;

    button.addEventListener('click', function () {
      var now = Date.now();
      if (now - lastClickAt < 400) {
        return;
      }
      lastClickAt = now;

      var config = window.peraWhatsappLog || {};
      var payload = buildPayload(button, config);

      sendLog(config, payload);
    });
  });
})();
