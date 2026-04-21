(function () {
  if (!window.peraVisitTracker || window.__peraVisitTracked) {
    return;
  }

  function sendVisit() {
    if (window.__peraVisitTracked) {
      return;
    }
    window.__peraVisitTracked = true;

    var payload = new URLSearchParams();
    payload.append('action', window.peraVisitTracker.action || 'pera_track_page_visit');
    payload.append('nonce', window.peraVisitTracker.nonce || '');
    payload.append('page_path', window.location.pathname || window.peraVisitTracker.path || '/');
    payload.append('page_url', window.location.href || window.peraVisitTracker.pageUrl || '');
    payload.append('page_title', document.title || window.peraVisitTracker.pageTitle || '');
    payload.append('post_id', String(window.peraVisitTracker.postId || 0));
    payload.append('post_type', window.peraVisitTracker.postType || '');

    var endpoint = window.peraVisitTracker.ajaxUrl;

    if (navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, payload);
      return;
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
