(function () {
  'use strict';

  function openWhatsappWindow(url) {
    if (!url) {
      return null;
    }

    var popup = window.open('', '_blank', 'noopener');

    if (popup) {
      try {
        popup.opener = null;
      } catch (e) {
        // noop
      }
    }

    return popup;
  }

  function navigateToWhatsapp(url, popup) {
    if (!url) {
      return;
    }

    if (popup && !popup.closed) {
      popup.location = url;
      return;
    }

    window.open(url, '_blank', 'noopener');
  }

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  onReady(function () {
    var button = document.getElementById('floating-whatsapp');
    if (!button) {
      return;
    }

    var clickLocked = false;

    button.addEventListener('click', function (event) {
      if (clickLocked) {
        event.preventDefault();
        return;
      }

      var config = window.peraWhatsappLog || {};
      var whatsappUrl = button.getAttribute('data-whatsapp-url') || button.getAttribute('href') || '';
      if (!whatsappUrl) {
        return;
      }

      event.preventDefault();
      clickLocked = true;

      var popup = openWhatsappWindow(whatsappUrl);
      var hasOpened = false;

      var finish = function () {
        if (hasOpened) {
          return;
        }

        hasOpened = true;
        navigateToWhatsapp(whatsappUrl, popup);

        window.setTimeout(function () {
          clickLocked = false;
        }, 300);
      };

      var fallbackTimer = window.setTimeout(finish, 500);

      if (!config.ajax_url || !config.action || !config.nonce) {
        finish();
        return;
      }

      var payload = new URLSearchParams();
      payload.append('action', config.action);
      payload.append('nonce', config.nonce);
      payload.append('page_type', button.getAttribute('data-page-type') || 'generic');
      payload.append('post_id', button.getAttribute('data-post-id') || '0');
      payload.append('post_title', button.getAttribute('data-post-title') || '');
      payload.append('page_url', button.getAttribute('data-page-url') || window.location.href);
      payload.append('message_text', button.getAttribute('data-message-text') || '');
      payload.append('referrer', document.referrer || '');
      payload.append('user_agent', window.navigator.userAgent || '');

      fetch(config.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: payload.toString()
      })
        .catch(function () {
          return null;
        })
        .finally(function () {
          window.clearTimeout(fallbackTimer);
          finish();
        });
    });
  });
})();
