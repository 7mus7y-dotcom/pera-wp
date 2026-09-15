(function () {
  'use strict';
  var config = window.peraCrmWhatsApp || {};
  var root = document.querySelector('[data-peracrm-whatsapp-conversation]');
  if (!root || !config.restUrl) return;
  var list = root.querySelector('[data-wa-messages]');
  var feedback = root.querySelector('[data-wa-feedback]');
  var form = root.querySelector('[data-wa-composer]');
  var hasRendered = false;
  var nearBottomTolerance = 48;
  var endpoint = config.restUrl.replace(/\/$/, '') + '/clients/' + encodeURIComponent(root.dataset.clientId) + '/messages';
  function request(options) {
    options = options || {};
    options.credentials = 'same-origin';
    options.headers = Object.assign({'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json'}, options.headers || {});
    return fetch(endpoint, options).then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'Request failed.'); return data; }); });
  }
  function render(data, forceLatest) {
    var wasNearBottom = list.scrollHeight - list.scrollTop - list.clientHeight <= nearBottomTolerance;
    list.textContent = '';
    (data.messages || []).forEach(function (message) {
      var item = document.createElement('article');
      item.className = 'crm-whatsapp__message crm-whatsapp__message--' + (message.direction === 'outbound' ? 'outbound' : 'inbound');
      var body = document.createElement('p'); body.textContent = message.message_body || '';
      var meta = document.createElement('small'); meta.textContent = (message.direction || '') + ' · ' + (message.message_status || '') + ' · ' + (message.meta_timestamp || message.created_at || '');
      item.appendChild(body); item.appendChild(meta); list.appendChild(item);
    });
    if (!list.children.length) list.textContent = 'No WhatsApp messages yet.';
    if (forceLatest || !hasRendered || wasNearBottom) list.scrollTop = list.scrollHeight;
    hasRendered = true;
  }
  function refresh(forceLatest) { return request().then(function (data) { render(data, !!forceLatest); }).catch(function (error) { feedback.textContent = error.message; }); }
  form.addEventListener('submit', function (event) {
    event.preventDefault(); var input = form.querySelector('[name="message"]'); feedback.textContent = 'Sending…';
    request({method: 'POST', body: JSON.stringify({message: input.value})}).then(function () { input.value = ''; feedback.textContent = 'Sent.'; return refresh(true); }).catch(function (error) { feedback.textContent = error.message; });
  });
  root.querySelector('[data-wa-refresh]').addEventListener('click', function () { refresh(false); });
  refresh(true); window.setInterval(function () { refresh(false); }, 10000);
}());
