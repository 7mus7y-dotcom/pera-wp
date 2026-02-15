(function () {
  var config = window.peraEnquiryNonce || {};
  var ajaxUrl = config.ajax_url || '';
  var action = config.action || 'pera_get_enquiry_nonces';
  var issuedAt = parseInt(config.issued_at || '0', 10);
  var maxAge = parseInt(config.max_age_seconds || '900', 10);
  var refreshing = false;

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

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    if (!formNeedsRefresh(form) || !pageIsStale() || refreshing) {
      return;
    }

    event.preventDefault();
    refreshing = true;

    refreshNonces()
      .then(function (data) {
        if (data) {
          applyNonce(form, 'sr_nonce', data.sr_nonce || '');
          applyNonce(form, 'fav_nonce', data.fav_nonce || '');
          applyNonce(form, 'pera_citizenship_nonce', data.pera_citizenship_nonce || '');
          issuedAt = parseInt(data.generated_at || issuedAt, 10);
        }
      })
      .catch(function () {
        // silent fallback to normal submit
      })
      .finally(function () {
        refreshing = false;
        form.submit();
      });
  }, true);
})();
