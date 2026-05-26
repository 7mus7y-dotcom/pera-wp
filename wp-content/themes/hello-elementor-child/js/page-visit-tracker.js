(function () {
  if (!window.peraVisitTracker || window.__peraVisitTracked) {
    return;
  }

  function shouldSuppressDuplicate() {
    try {
      if (!window.sessionStorage) {
        return false;
      }

      var ttlMs = 15000;
      var pageKey = window.location.pathname + window.location.search;
      var key = 'peraVisitTracked:' + pageKey;
      var now = Date.now();
      var previous = Number(window.sessionStorage.getItem(key) || 0);

      if (previous && now - previous < ttlMs) {
        return true;
      }

      window.sessionStorage.setItem(key, String(now));
    } catch (e) {}

    return false;
  }

  function sendVisit() {
    if (window.__peraVisitTracked || shouldSuppressDuplicate()) {
      return;
    }
    window.__peraVisitTracked = true;

    var payload = new URLSearchParams();
    payload.append('action', window.peraVisitTracker.action || 'pera_track_page_visit');
    payload.append('page_path', window.location.pathname || '/');
    payload.append('page_url', window.location.href || '');
    payload.append('page_title', document.title || '');
    payload.append('post_id', String(window.peraVisitTracker.postId || 0));
    payload.append('post_type', window.peraVisitTracker.postType || '');
    payload.append('referer', document.referrer || '');
    payload.append('referrer', document.referrer || '');

    var endpoint = window.peraVisitTracker.ajaxUrl;

    if (navigator.sendBeacon) {
      var queued = navigator.sendBeacon(endpoint, payload);
      if (queued) {
        return;
      }
    }

    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: payload.toString(),
      keepalive: true
    }).catch(function () {});
  }

  if (document.visibilityState === 'prerender') {
    document.addEventListener('visibilitychange', function onVisible() {
      if (document.visibilityState === 'visible') {
        document.removeEventListener('visibilitychange', onVisible);
        sendVisit();
      }
    });
  } else {
    sendVisit();
  }
})();
