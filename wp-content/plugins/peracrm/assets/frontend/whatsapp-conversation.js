(function () {
  'use strict';
  var config = window.peraCrmWhatsApp || {};
  var root = document.querySelector('[data-peracrm-whatsapp-conversation]');
  if (!root || !config.restUrl) return;
  var list = root.querySelector('[data-wa-messages]');
  var feedback = root.querySelector('[data-wa-feedback]');
  var form = root.querySelector('[data-wa-composer]');
  var endpoint = config.restUrl.replace(/\/$/, '') + '/clients/' + encodeURIComponent(root.dataset.clientId) + '/messages';
  function request(options) {
    options = options || {};
    options.credentials = 'same-origin';
    options.headers = Object.assign({'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json'}, options.headers || {});
    return fetch(endpoint, options).then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'Request failed.'); return data; }); });
  }
  function render(data) {
    list.textContent = '';
    (data.messages || []).forEach(function (message) {
      var item = document.createElement('article');
      item.className = 'crm-whatsapp__message crm-whatsapp__message--' + (message.direction === 'outbound' ? 'outbound' : 'inbound');
      var body = document.createElement('p'); body.textContent = message.message_body || '';
      var meta = document.createElement('small'); meta.textContent = (message.direction || '') + ' · ' + (message.message_status || '') + ' · ' + (message.meta_timestamp || message.created_at || '');
      item.appendChild(body); item.appendChild(meta); list.appendChild(item);
    });
    if (!list.children.length) list.textContent = 'No WhatsApp messages yet.';
  }
  function refresh() { return request().then(render).catch(function (error) { feedback.textContent = error.message; }); }
  form.addEventListener('submit', function (event) {
    event.preventDefault(); var input = form.querySelector('[name="message"]'); feedback.textContent = 'Sending…';
    request({method: 'POST', body: JSON.stringify({message: input.value})}).then(function () { input.value = ''; feedback.textContent = 'Sent.'; return refresh(); }).catch(function (error) { feedback.textContent = error.message; });
  });
  root.querySelector('[data-wa-refresh]').addEventListener('click', refresh);
  refresh(); window.setInterval(refresh, 10000);
}());
