(function () {
  'use strict';

  if (!window.peraCrmPush) {
    return;
  }

  const config = window.peraCrmPush;
  const card = document.querySelector('[data-crm-push-card]');
  if (!card) {
    return;
  }

  const statusEl = card.querySelector('[data-crm-push-status]');
  const swStatusEl = card.querySelector('[data-crm-push-sw-status]');
  const cronHealthEl = card.querySelector('[data-crm-push-cron-health]');
  const digestResultEl = card.querySelector('[data-crm-push-digest-result]');
  const enableBtn = card.querySelector('[data-crm-push-enable]');
  const disableBtn = card.querySelector('[data-crm-push-disable]');
  const runDigestBtn = card.querySelector('[data-crm-push-run-digest]');

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

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || ''
      },
      credentials: 'same-origin',
      body: JSON.stringify(body || {})
    });

    if (!response.ok) {
      throw new Error('Request failed: ' + response.status);
    }

    return response.json();
  }

  function supportsPushSetup() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  async function getRegistration() {
    return navigator.serviceWorker.register(config.swUrl || '/peracrm-sw.js');
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

    const registration = await navigator.serviceWorker.getRegistration(config.swUrl || '/peracrm-sw.js');
    const controller = navigator.serviceWorker.controller;
    const scope = registration && registration.scope ? registration.scope : 'none';
    const active = registration && registration.active ? 'yes' : 'no';
    swStatusEl.textContent = 'Service worker: registered ' + (registration ? 'yes' : 'no') + ', active ' + active + ', controlled ' + (controller ? 'yes' : 'no') + ', scope ' + scope + '.';
  }

  function showDigestButton() {
    if (!runDigestBtn) {
      return;
    }

    if (config.canRunDigest) {
      runDigestBtn.hidden = false;
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
      digestResultEl.textContent = 'Digest window ' + (summary.window_key || 'n/a') + ': attempted ' + (summary.pushes_attempted || 0) + ', sent ' + (summary.pushes_sent || 0) + ', rows ' + (summary.rows_considered || 0) + '.';
    } catch (error) {
      digestResultEl.textContent = 'Unable to run digest right now.';
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

      renderCronHealth();
      await renderServiceWorkerStatus();
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

    if (!config.vapidPublicKey) {
      setStatus('Push keys are not configured. Please contact an administrator.', 'pill--red');
      return;
    }

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(config.vapidPublicKey)
      });
    }

    await postJson(config.subscribeUrl, subscription.toJSON());
    setStatus('Push notifications enabled on this device.', 'pill--green');
    setButtons(true);
    await renderServiceWorkerStatus();
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

  showDigestButton();
  refreshState();
})();
