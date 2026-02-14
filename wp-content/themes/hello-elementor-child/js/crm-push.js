(function () {
  'use strict';

  if (!window.peraCrmPush) {
    return;
  }

  const config = window.peraCrmPush;
  const SW_URL = config.swUrl || '/peracrm-sw.js';
  const card = document.querySelector('[data-crm-push-card]');
  if (!card) {
    return;
  }

  const statusEl = card.querySelector('[data-crm-push-status]');
  const swStatusEl = card.querySelector('[data-crm-push-sw-status]');
  const cronHealthEl = card.querySelector('[data-crm-push-cron-health]');
  const digestResultEl = card.querySelector('[data-crm-push-digest-result]');
  const diagnosticsEl = card.querySelector('[data-crm-push-diagnostics]');
  const enableBtn = card.querySelector('[data-crm-push-enable]');
  const disableBtn = card.querySelector('[data-crm-push-disable]');
  const runDigestBtn = card.querySelector('[data-crm-push-run-digest]');
  const refreshDiagnosticsBtn = card.querySelector('[data-crm-push-refresh-diagnostics]');

  const STATUS_VARIANTS = ['pill--outline', 'pill--green', 'pill--red'];

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function setStatus(text, tone) {
    if (statusEl) {
      statusEl.textContent = text;
      statusEl.classList.add('pill');
      STATUS_VARIANTS.forEach(function (variant) {
        statusEl.classList.remove(variant);
      });
      statusEl.classList.add(tone || 'pill--outline');
    }
  }

  function setButtons(enabled) {
    if (enableBtn) {
      enableBtn.disabled = enabled;
    }
    if (disableBtn) {
      disableBtn.disabled = !enabled;
    }
  }

  async function requestJson(url, options) {
    const response = await fetch(url, Object.assign({
      headers: {
        'X-WP-Nonce': config.restNonce || ''
      },
      credentials: 'same-origin'
    }, options || {}));

    if (!response.ok) {
      throw new Error('Request failed: ' + response.status);
    }

    return response.json();
  }

  async function postJson(url, body) {
    return requestJson(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.restNonce || ''
      },
      credentials: 'same-origin',
      body: JSON.stringify(body || {})
    });
  }

  function supportsPushSetup() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  async function getRegistration() {
    return navigator.serviceWorker.register(SW_URL);
  }

  async function getCurrentSubscription() {
    if (!supportsPushSetup()) {
      return null;
    }

    const registration = await getRegistration();
    return registration.pushManager.getSubscription();
  }

  function renderCronHealth() {
    if (!cronHealthEl) {
      return;
    }

    const cron = config.debug && config.debug.cron ? config.debug.cron : {};
    const next = cron.next_scheduled_local || 'not scheduled';
    const wpCronState = cron.disable_wp_cron ? 'disabled' : 'enabled';
    cronHealthEl.textContent = 'Digest cron: next ' + next + ' · DISABLE_WP_CRON is ' + wpCronState + '.';
  }

  async function renderServiceWorkerStatus() {
    if (!swStatusEl || !('serviceWorker' in navigator)) {
      return;
    }

    const targetSwPath = SW_URL.replace(window.location.origin, '');
    let registration = null;

    if (navigator.serviceWorker.getRegistrations) {
      const registrations = await navigator.serviceWorker.getRegistrations();
      registration = registrations.find(function (item) {
        return item && item.active && item.active.scriptURL && item.active.scriptURL.indexOf(targetSwPath) !== -1;
      }) || null;
    }

    if (!registration) {
      registration = await navigator.serviceWorker.getRegistration();
    }

    const controller = navigator.serviceWorker.controller;
    const scope = registration && registration.scope ? registration.scope : 'none';
    const active = registration && registration.active ? 'yes' : 'no';
    const controlled = controller ? 'yes' : 'no';
    let hint = '';
    if (registration && !controller) {
      hint = ' Reload once to activate within scope (/).';
    }

    swStatusEl.textContent = 'Service worker: registered ' + (registration ? 'yes' : 'no') + ', active ' + active + ', controlled ' + controlled + ', scope ' + scope + '.' + hint;
  }

  function renderDiagnosticsSummary(debug) {
    if (!diagnosticsEl) {
      return;
    }

    const subs = debug && typeof debug.subs_count !== 'undefined' ? debug.subs_count : 'n/a';
    const lastDigest = debug && debug.last_digest_meta ? debug.last_digest_meta : 'none';
    const missingReasons = Array.isArray(debug && debug.missingReasons) ? debug.missingReasons : [];
    const missingText = missingReasons.length > 0 ? ' missing(' + missingReasons.join('; ') + ')' : '';
    const cron = debug && debug.cron ? debug.cron : {};
    const next = cron.next_scheduled_local || 'not scheduled';
    const logs = Array.isArray(debug && debug.push_log_recent) ? debug.push_log_recent : [];
    const statuses = logs.map(function (row) {
      return String(row.status_code || 0);
    });
    diagnosticsEl.hidden = false;
    diagnosticsEl.textContent = 'Diagnostics: subs ' + subs + ', last digest meta ' + lastDigest + ', cron next ' + next + ', last status codes [' + statuses.join(', ') + '].' + missingText;
  }

  async function refreshDiagnostics() {
    if (!refreshDiagnosticsBtn || !config.debugUrl) {
      return;
    }

    refreshDiagnosticsBtn.disabled = true;
    if (diagnosticsEl) {
      diagnosticsEl.hidden = false;
      diagnosticsEl.textContent = 'Refreshing diagnostics…';
    }

    try {
      const response = await requestJson(config.debugUrl, { method: 'GET' });
      const debug = response.debug || {};
      config.debug = config.debug || {};
      config.debug.cron = debug.cron || {};
      renderCronHealth();
      renderDiagnosticsSummary(debug);
    } catch (error) {
      if (diagnosticsEl) {
        diagnosticsEl.hidden = false;
        diagnosticsEl.textContent = 'Unable to load push diagnostics right now.';
      }
    } finally {
      refreshDiagnosticsBtn.disabled = false;
    }
  }

  function showDigestButton() {
    if (!runDigestBtn) {
      return;
    }

    if (config.canRunDigest) {
      runDigestBtn.hidden = false;
    }
    if (refreshDiagnosticsBtn) {
      refreshDiagnosticsBtn.hidden = false;
    }
  }

  async function runDigestNow() {
    if (!runDigestBtn || !digestResultEl) {
      return;
    }

    runDigestBtn.disabled = true;
    digestResultEl.hidden = false;
    digestResultEl.textContent = 'Running digest…';

    try {
      const response = await postJson(config.digestRunUrl, {});
      const summary = response.summary || {};
      const skipped = summary.skipped || {};
      const sendReason = summary.last_send_error_reason ? ' Reason: ' + summary.last_send_error_reason : '';
      digestResultEl.textContent = 'Digest window ' + (summary.window_key || 'n/a') + ': attempted ' + (summary.pushes_attempted || 0) + ', sent ' + (summary.pushes_sent || 0) + ', rows ' + (summary.rows_considered || 0) + ', skipped(no subs ' + (skipped.no_subs || 0) + ', deduped ' + (skipped.deduped || 0) + ', send error ' + (skipped.send_error || 0) + ', table missing ' + (skipped.table_missing || 0) + ').' + sendReason;
      if (response.cron) {
        config.debug = config.debug || {};
        config.debug.cron = response.cron;
        renderCronHealth();
      }
      await refreshDiagnostics();
    } catch (error) {
      const missing = Array.isArray(config.missingReasons) && config.missingReasons.length > 0 ? ' Missing: ' + config.missingReasons.join(', ') + '.' : '';
      digestResultEl.textContent = 'Unable to run digest right now.' + missing;
    } finally {
      runDigestBtn.disabled = false;
    }
  }

  async function refreshState() {
    try {
      const subscription = await getCurrentSubscription();
      const permission = 'Notification' in window ? Notification.permission : 'default';

      if (!supportsPushSetup()) {
        setStatus('Push is not supported in this browser on this device.', 'pill--red');
        setButtons(false);
        renderCronHealth();
        return;
      }

      if (permission === 'denied') {
        setStatus('Notifications blocked – enable in site settings.', 'pill--red');
        setButtons(false);
        renderCronHealth();
        return;
      }

      if (subscription) {
        setStatus('Push notifications are enabled on this device.', 'pill--green');
        setButtons(true);
      } else {
        setStatus('Push notifications are currently disabled on this device.', 'pill--outline');
        setButtons(false);
      }

      if (config.isConfigured === false && Array.isArray(config.missingReasons) && config.missingReasons.length > 0) {
        setStatus('Push server config missing: ' + config.missingReasons.join(', ') + '.', 'pill--red');
        if (diagnosticsEl) { diagnosticsEl.hidden = false; diagnosticsEl.textContent = 'Missing configuration: ' + config.missingReasons.join(', ') + '.'; }
      }

      renderCronHealth();
      await renderServiceWorkerStatus();
      await refreshDiagnostics();
    } catch (error) {
      setStatus('Push setup is unavailable in this browser session.', 'pill--red');
      setButtons(false);
      renderCronHealth();
    }
  }

  async function enablePush() {
    if (!supportsPushSetup()) {
      setStatus('Push is not supported in this browser on this device.', 'pill--red');
      return;
    }

    const permission = await Notification.requestPermission();
    if (permission === 'denied') {
      setStatus('Notifications blocked – enable in site settings.', 'pill--red');
      return;
    }

    if (permission !== 'granted') {
      setStatus('Notification permission was dismissed. Tap enable to try again.', 'pill--outline');
      return;
    }

    const registration = await getRegistration();
    await navigator.serviceWorker.ready;

    if (!config.publicKey) {
      setStatus('Push keys are not configured. Please contact an administrator.', 'pill--red');
      return;
    }

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(config.publicKey)
      });
    }

    await postJson(config.subscribeUrl, subscription.toJSON());
    setStatus('Push notifications enabled on this device.', 'pill--green');
    setButtons(true);
    await renderServiceWorkerStatus();
    await refreshDiagnostics();
  }

  async function disablePush() {
    if (!supportsPushSetup()) {
      setStatus('Push is not supported in this browser on this device.', 'pill--red');
      return;
    }

    const registration = await getRegistration();
    const subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
      setStatus('No active push subscription on this device.', 'pill--outline');
      setButtons(false);
      return;
    }

    await postJson(config.unsubscribeUrl, { endpoint: subscription.endpoint });
    await subscription.unsubscribe();

    setStatus('Push notifications disabled on this device.', 'pill--outline');
    setButtons(false);
    await renderServiceWorkerStatus();
    await refreshDiagnostics();
  }

  if (enableBtn) {
    enableBtn.addEventListener('click', function () {
      enablePush().catch(function () {
        setStatus('Unable to enable push notifications right now.', 'pill--red');
      });
    });
  }

  if (disableBtn) {
    disableBtn.addEventListener('click', function () {
      disablePush().catch(function () {
        setStatus('Unable to disable push notifications right now.', 'pill--red');
      });
    });
  }

  if (runDigestBtn) {
    runDigestBtn.addEventListener('click', function () {
      runDigestNow();
    });
  }

  if (refreshDiagnosticsBtn) {
    refreshDiagnosticsBtn.addEventListener('click', function () {
      refreshDiagnostics();
    });
  }

  showDigestButton();
  refreshState();
})();
