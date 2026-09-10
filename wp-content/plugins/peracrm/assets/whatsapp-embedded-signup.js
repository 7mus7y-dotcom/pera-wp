(function () {
  'use strict';

  var config = window.peracrmWhatsAppEmbeddedSignup;
  var panel = document.getElementById('peracrm-wa-embedded-signup');
  var launchButton = document.getElementById('peracrm-wa-embedded-signup-launch');
  if (!config || !panel || !launchButton) return;

  function show(key, value) {
    var target = panel.querySelector('[data-peracrm-wa-diagnostic="' + key + '"]');
    if (target) target.textContent = value === undefined || value === null || value === '' ? '—' : String(value);
  }

  function metaOrigin(origin) {
    try {
      var url = new URL(origin);
      return url.protocol === 'https:' && (url.hostname === 'facebook.com' || url.hostname.endsWith('.facebook.com'));
    } catch (error) {
      return false;
    }
  }

  function safeData(payload) {
    return payload && typeof payload === 'object' ? payload : {};
  }

  window.addEventListener('message', function (message) {
    if (!metaOrigin(message.origin)) return;
    var payload = message.data;
    if (typeof payload === 'string') {
      try { payload = JSON.parse(payload); } catch (error) { return; }
    }
    payload = safeData(payload);
    if (payload.type !== 'WA_EMBEDDED_SIGNUP') return;

    var eventData = safeData(payload.data);
    var eventType = payload.event || eventData.event || 'WA_EMBEDDED_SIGNUP';
    show('event', eventType);
    show('waba', eventData.waba_id || eventData.wabaId);
    show('phone', eventData.phone_number_id || eventData.phoneNumberId);
    show('business', eventData.business_id || eventData.businessId);

    var normalized = String(eventType).toUpperCase();
    if (normalized.indexOf('CANCEL') !== -1) show('outcome', 'Cancelled');
    else if (normalized.indexOf('ERROR') !== -1) show('outcome', 'Error');
    else if (normalized === 'FINISH' || normalized === 'COMPLETE') show('outcome', 'Completed');
  });

  function sendCode(code) {
    var body = new URLSearchParams();
    body.set('action', 'peracrm_whatsapp_embedded_signup_code');
    body.set('nonce', config.nonce);
    body.set('code', code);
    return window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) { return response.json(); });
  }

  if (!window.FB) {
    show('sdk', 'No');
    show('outcome', 'Facebook SDK failed to load');
    launchButton.disabled = true;
    return;
  }

  window.FB.init({ appId: config.appId, cookie: true, xfbml: false, version: config.graphVersion });
  show('sdk', 'Yes');

  launchButton.addEventListener('click', function () {
    show('launch', 'Opening');
    show('outcome', '—');
    window.FB.login(function (response) {
      var code = response && response.authResponse && response.authResponse.code;
      if (!code) {
        show('launch', 'Closed without authorization code');
        show('outcome', response && response.status ? response.status : 'Cancelled or not authorized');
        return;
      }
      show('launch', 'Authorization callback received');
      sendCode(code).then(function (result) {
        if (result && result.success && result.data && result.data.received) show('code', 'Yes');
        else throw new Error('Code validation failed');
      }).catch(function () {
        show('code', 'No');
        show('outcome', 'Authorization code validation failed');
      });
    }, {
      config_id: config.configurationId,
      response_type: 'code',
      override_default_response_type: true,
      extras: config.extras
    });
  });
}());
