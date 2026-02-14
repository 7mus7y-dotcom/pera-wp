(function () {
  'use strict';

  if (!window.peraCrmPush || !('serviceWorker' in navigator) || !('PushManager' in window)) {
    return;
  }

  const config = window.peraCrmPush;
  const card = document.querySelector('[data-crm-push-card]');
  if (!card) {
    return;
  }

  const statusEl = card.querySelector('[data-crm-push-status]');
  const enableBtn = card.querySelector('[data-crm-push-enable]');
  const disableBtn = card.querySelector('[data-crm-push-disable]');

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

  function setStatus(text) {
    if (statusEl) {
      statusEl.textContent = text;
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

  async function getCurrentSubscription() {
    const registration = await navigator.serviceWorker.register(config.swUrl || '/peracrm-sw.js');
    return registration.pushManager.getSubscription();
  }

  async function refreshState() {
    try {
      const subscription = await getCurrentSubscription();
      const permission = Notification.permission;
      if (permission === 'denied') {
        setStatus('Push permission is blocked in your browser settings.');
        setButtons(false);
        return;
      }

      if (subscription) {
        setStatus('Push notifications are enabled on this device.');
        setButtons(true);
      } else {
        setStatus('Push notifications are currently disabled on this device.');
        setButtons(false);
      }
    } catch (error) {
      setStatus('Push setup is unavailable in this browser session.');
      setButtons(false);
    }
  }

  async function enablePush() {
    if (!config.vapidPublicKey) {
      setStatus('Push keys are not configured. Please contact an administrator.');
      return;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      setStatus('Push permission was not granted.');
      return;
    }

    const registration = await navigator.serviceWorker.register(config.swUrl || '/peracrm-sw.js');
    let subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(config.vapidPublicKey)
      });
    }

    await postJson(config.subscribeUrl, subscription.toJSON());
    setStatus('Push notifications enabled on this device.');
    setButtons(true);
  }

  async function disablePush() {
    const registration = await navigator.serviceWorker.register(config.swUrl || '/peracrm-sw.js');
    const subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
      setStatus('No active push subscription on this device.');
      setButtons(false);
      return;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();
    await postJson(config.unsubscribeUrl, { endpoint: endpoint });

    setStatus('Push notifications disabled on this device.');
    setButtons(false);
  }

  if (enableBtn) {
    enableBtn.addEventListener('click', function () {
      enablePush().catch(function () {
        setStatus('Unable to enable push notifications right now.');
      });
    });
  }

  if (disableBtn) {
    disableBtn.addEventListener('click', function () {
      disablePush().catch(function () {
        setStatus('Unable to disable push notifications right now.');
      });
    });
  }

  refreshState();
})();
