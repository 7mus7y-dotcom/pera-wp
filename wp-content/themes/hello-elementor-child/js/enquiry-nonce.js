(function () {
  var config = window.peraEnquiryNonce || {};
  var ajaxUrl = config.ajax_url || '';
  var action = config.action || 'pera_get_enquiry_nonces';
  var issuedAt = parseInt(config.issued_at || '0', 10);
  var maxAge = parseInt(config.max_age_seconds || '900', 10);
  var refreshing = false;
  var bypassAttr = 'data-pera-nonce-resubmit';
  var refreshErrorText = 'This page has expired. Please refresh and try again.';

  function formNeedsRefresh(form) {
    if (!form) return false;
    return !!(
      form.querySelector('input[name="sr_nonce"]') ||
      form.querySelector('input[name="fav_nonce"]') ||
      form.querySelector('input[name="pera_citizenship_nonce"]')
    );
  }

  function pageIsStale() {
    if (!issuedAt || !maxAge) return false;
    var now = Math.floor(Date.now() / 1000);
    return now - issuedAt > maxAge;
  }

  function applyNonce(form, name, value) {
    var field = form.querySelector('input[name="' + name + '"]');
    if (field && value) {
      field.value = value;
    }
  }

  async function refreshNonces() {
    if (!ajaxUrl) {
      return null;
    }

    var body = new URLSearchParams();
    body.set('action', action);

    var response = await fetch(ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      credentials: 'same-origin'
    });

    if (!response.ok) {
      return null;
    }

    var json = await response.json();
    if (!json || !json.success || !json.data) {
      return null;
    }

    return json.data;
  }

  function showRefreshError(form) {
    if (!form) return;

    var errorBox = form.querySelector('[data-sr-js-error]');
    if (!errorBox) {
      errorBox = document.createElement('div');
      errorBox.className = 'citizenship-alert citizenship-alert--error';
      errorBox.setAttribute('data-sr-js-error', '1');

      var footer = form.querySelector('.enquiry-cta-footer');
      if (footer && footer.parentNode) {
        footer.parentNode.insertBefore(errorBox, footer);
      } else {
        form.appendChild(errorBox);
      }
    }

    errorBox.textContent = refreshErrorText;
    errorBox.hidden = false;
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    if (form.hasAttribute(bypassAttr)) {
      form.removeAttribute(bypassAttr);
      return;
    }

    if (!formNeedsRefresh(form) || !pageIsStale() || refreshing) {
      return;
    }

    event.preventDefault();
    refreshing = true;
    var refreshSucceeded = false;

    refreshNonces()
      .then(function (data) {
        if (data) {
          applyNonce(form, 'sr_nonce', data.sr_nonce || '');
          applyNonce(form, 'fav_nonce', data.fav_nonce || '');
          applyNonce(form, 'pera_citizenship_nonce', data.pera_citizenship_nonce || '');
          issuedAt = parseInt(data.generated_at || issuedAt, 10);
          refreshSucceeded = true;
          return;
        }

        showRefreshError(form);
      })
      .catch(function () {
        showRefreshError(form);
      })
      .finally(function () {
        refreshing = false;

        if (!refreshSucceeded) {
          return;
        }

        if (typeof form.requestSubmit === 'function') {
          form.setAttribute(bypassAttr, '1');
          form.requestSubmit();
          return;
        }

        form.submit();
      });
  }, true);
})();
