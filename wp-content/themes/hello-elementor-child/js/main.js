document.addEventListener('DOMContentLoaded', function () {

  /* -----------------------------------------
     1. OFF-CANVAS NAV (open/close with body class)
     ----------------------------------------- */
  var body        = document.body;
  var menuToggle  = document.querySelector('.header-menu-toggle');
  var navBackdrop = document.querySelector('.offcanvas-backdrop');
  var navCloseBtn = document.querySelector('.offcanvas-close');

  // -----------------------------------------
  // Tracking helpers (GA4 + Meta Pixel)
  // -----------------------------------------
  var analyticsLoaded = false;
  var marketingLoaded = false;

  function loadScript(src, id) {
    if (id && document.getElementById(id)) return;
    var s = document.createElement('script');
    s.async = true;
    s.src = src;
    if (id) s.id = id;
    document.head.appendChild(s);
  }

  function initGA() {
    if (analyticsLoaded) return;

    // GA4 measurement ID is injected server-side via wp_add_inline_script.
    if (!window.peraTrackingConfig || !window.peraTrackingConfig.gaMeasurementId) {
      return;
    }

    analyticsLoaded = true;

    var gaId = window.peraTrackingConfig.gaMeasurementId;

    loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId), 'pera-ga4');

    window.dataLayer = window.dataLayer || [];
    function gtag(){ window.dataLayer.push(arguments); }
    window.gtag = window.gtag || gtag;

    window.gtag('js', new Date());
    window.gtag('config', gaId);
  }

  function initMetaPixel() {
    if (marketingLoaded) return;
    marketingLoaded = true;

    if (!window.peraTrackingConfig || !window.peraTrackingConfig.metaPixelId) return;
    var pixelId = window.peraTrackingConfig.metaPixelId;

    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');

    window.fbq('init', pixelId);
    window.fbq('track', 'PageView');
  }


  function getConfiguredWhatsAppNumber() {
    var config = window.peraFrontendConfig || {};
    var number = String(config.whatsappNumber || '').replace(/\D+/g, '');

    return number || '';
  }

  function isWhatsAppLink(url) {
    var host = (url.hostname || '').toLowerCase().replace(/^www\./, '');
    var path = (url.pathname || '').toLowerCase();

    return host === 'wa.me' ||
      (host === 'api.whatsapp.com' && path.indexOf('/send') === 0) ||
      (host === 'whatsapp.com' && path.indexOf('/send') === 0);
  }

  function rewriteWhatsAppHref(href, configuredNumber) {
    if (!href || !configuredNumber) return href;

    var url;
    try {
      url = new URL(href, window.location.href);
    } catch (e) {
      return href;
    }

    if (!isWhatsAppLink(url)) return href;

    if (url.hostname.toLowerCase().replace(/^www\./, '') === 'wa.me') {
      var pathParts = url.pathname.split('/');
      if (pathParts.length > 1 && /^\d+$/.test(pathParts[1] || '')) {
        pathParts[1] = configuredNumber;
        url.pathname = pathParts.join('/');
        return url.toString();
      }

      return href;
    }

    if (url.searchParams.has('phone')) {
      var currentPhone = String(url.searchParams.get('phone') || '').replace(/\D+/g, '');
      if (currentPhone) {
        url.searchParams.set('phone', configuredNumber);
        return url.toString();
      }
    }

    return href;
  }

  function rewriteLegacyWhatsAppLinks(root) {
    var configuredNumber = getConfiguredWhatsAppNumber();
    if (!configuredNumber || !root || !root.querySelectorAll) return;

    var links = Array.from(root.querySelectorAll('a[href]'));
    if (root.matches && root.matches('a[href]')) {
      links.unshift(root);
    }

    links.forEach(function (link) {
      if (link.getAttribute('data-pera-whatsapp-rewritten') === configuredNumber) return;

      var href = link.getAttribute('href') || '';
      var rewrittenHref = rewriteWhatsAppHref(href, configuredNumber);
      if (rewrittenHref && rewrittenHref !== href) {
        link.setAttribute('href', rewrittenHref);
        link.setAttribute('data-pera-whatsapp-rewritten', configuredNumber);
      }
    });
  }

  function trackWhatsAppDispatcher() {
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!target || !target.closest) return;

      var link = target.closest('a[data-whatsapp="1"][href]');
      if (!link) return;
      var now = Date.now();
      var lastClick = parseInt(link.getAttribute('data-pera-whatsapp-last-click') || '0', 10);
      if (!isNaN(lastClick) && now - lastClick < 400) return;
      link.setAttribute('data-pera-whatsapp-last-click', String(now));

      var href = link.getAttribute('href') || '';
      var metaParams = {
        whatsapp_type: link.getAttribute('data-whatsapp-type') || '',
        track_context: link.getAttribute('data-track-context') || '',
        track_intent: link.getAttribute('data-track-intent') || '',
        track_source: link.getAttribute('data-track-source') || '',
        link_url: href
      };

      if (typeof window.gtag === 'function') {
        window.gtag('event', link.getAttribute('data-track-ga4-event') || 'whatsapp_click', {
          whatsapp_type: metaParams.whatsapp_type,
          track_context: metaParams.track_context,
          track_intent: metaParams.track_intent,
          track_source: metaParams.track_source,
          page_location: window.location.href,
          page_title: document.title || '',
          link_url: href
        });
      }

      // Meta WhatsAppLead should only fire when fbq exists; fbq is loaded by the consent-controlled Meta Pixel path.
      if (typeof window.fbq === 'function') {
        window.fbq('trackCustom', 'WhatsAppLead', metaParams);
      }

      var logConfig = window.peraWhatsappLog || {};
      if (!logConfig.ajax_url || !logConfig.action || !logConfig.nonce) return;

      var payload = new URLSearchParams();
      payload.append('action', logConfig.action);
      payload.append('nonce', logConfig.nonce);
      payload.append('page_type', link.getAttribute('data-page-type') || 'generic');
      payload.append('post_id', link.getAttribute('data-post-id') || '0');
      payload.append('post_title', link.getAttribute('data-post-title') || '');
      payload.append('page_url', link.getAttribute('data-page-url') || window.location.href);
      payload.append('message_text', link.getAttribute('data-message-text') || '');
      payload.append('referrer', document.referrer || '');
      payload.append('user_agent', window.navigator.userAgent || '');
      payload.append('whatsapp_type', metaParams.whatsapp_type);
      payload.append('track_intent', metaParams.track_intent);
      payload.append('track_source', metaParams.track_source);
      payload.append('track_context', metaParams.track_context);
      payload.append('link_href', href);
      payload.append('event_source', 'whatsapp_cta');
      payload.append('crm_event', link.getAttribute('data-track-crm-event') || 'whatsapp_click');

      if (typeof navigator.sendBeacon === 'function') {
        var beaconBody = new Blob([payload.toString()], {
          type: 'application/x-www-form-urlencoded; charset=UTF-8'
        });
        navigator.sendBeacon(logConfig.ajax_url, beaconBody);
        return;
      }

      fetch(logConfig.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: payload.toString()
      }).catch(function () { return null; });
    }, true);
  }

  rewriteLegacyWhatsAppLinks(document);
  trackWhatsAppDispatcher();

  document.addEventListener('pera:track', function (event) {
    var detail = event && event.detail ? event.detail : {};
    var eventName = detail.event_name || '';
    if (!eventName || typeof window.gtag !== 'function') return;

    var payload = {};
    Object.keys(detail).forEach(function (key) {
      if (key !== 'event_name') {
        payload[key] = detail[key];
      }
    });

    window.gtag('event', eventName, payload);
  });


  function openNav() {
    body.classList.add('is-nav-open');
  }

  function closeNav() {
    body.classList.remove('is-nav-open');
  }

  // Toggle from burger button
  if (menuToggle) {
    menuToggle.addEventListener('click', function () {
      if (body.classList.contains('is-nav-open')) {
        closeNav();
      } else {
        openNav();
      }
    });
  }

  // Close when clicking backdrop
  if (navBackdrop) {
    navBackdrop.addEventListener('click', function () {
      closeNav();
    });
  }

  // Close from "X" button inside the panel
  if (navCloseBtn) {
    navCloseBtn.addEventListener('click', function () {
      closeNav();
    });
  }

  // Close with Esc key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeNav();
    }
  });

  /* -----------------------------------------
     2. Accordion for:
        - Offcanvas menu
        - Footer menus
     ----------------------------------------- */

  var accordionSelectors = [
    '.offcanvas-menu .menu-item-has-children > a',
    '.footer-links .menu-item-has-children > a'
  ];

  document.querySelectorAll(accordionSelectors.join(',')).forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();

      var li = this.parentElement;
      if (!li) return;

      li.classList.toggle('is-open');
    });
  });

  /* -----------------------------------------
     3. SVG SPRITE SHIM (external <use> -> inline #id)
     ----------------------------------------- */

  function rewriteSvgUses(root) {
    if (!root) return;

    var uses = root.querySelectorAll ? root.querySelectorAll('use') : [];
    if (!uses.length) return;

    uses.forEach(function (useEl) {
      var href = useEl.getAttribute('href') || useEl.getAttribute('xlink:href');
      if (!href || href.indexOf('icons.svg#') === -1) {
        return;
      }

      var parts = href.split('#');
      var id = parts[parts.length - 1];
      if (!id) return;

      // Rewrite external sprite refs to local fragments once sprite is inlined.
      useEl.setAttribute('href', '#' + id);
      useEl.setAttribute('xlink:href', '#' + id);
    });
  }

  rewriteSvgUses(document);

  if (window.MutationObserver) {
    var spriteObserver = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) return;

          if (node.tagName && node.tagName.toLowerCase() === 'use') {
            rewriteSvgUses(node.parentNode || node);
            return;
          }

          if (node.querySelectorAll) {
            rewriteSvgUses(node);
            rewriteLegacyWhatsAppLinks(node);
          }
        });
      });
    });

    spriteObserver.observe(document.body, { childList: true, subtree: true });
  }

  function parseSortableNumber(value) {
    var cleaned = String(value || '')
      .replace(/\s/g, '')
      .replace(/,/g, '')
      .replace(/[^\d.-]/g, '');

    return cleaned ? parseFloat(cleaned) : NaN;
  }

  function initSortableTables() {
    document.querySelectorAll('table.sortable').forEach(function (table) {
      if (table.dataset.sortableInitialized === 'true') return;

      var headers = table.querySelectorAll('thead tr:first-child th');
      var tbody = table.tBodies[0];
      if (!headers.length || !tbody) return;

      headers.forEach(function (th, index) {
        var type = th.dataset.type || 'text';

        th.tabIndex = th.tabIndex >= 0 ? th.tabIndex : 0;
        th.setAttribute('role', th.getAttribute('role') || 'button');
        th.setAttribute('aria-sort', 'none');

        function sort(direction) {
          headers.forEach(function (h) {
            if (h !== th) h.setAttribute('aria-sort', 'none');
          });

          th.setAttribute('aria-sort', direction);

          Array.from(tbody.rows)
            .sort(function (a, b) {
              var av = ((a.cells[index] && a.cells[index].textContent) || '').trim();
              var bv = ((b.cells[index] && b.cells[index].textContent) || '').trim();

              if (type !== 'text') {
                av = parseSortableNumber(av);
                bv = parseSortableNumber(bv);

                if (isNaN(av) && isNaN(bv)) return 0;
                if (isNaN(av)) return 1;
                if (isNaN(bv)) return -1;

                return direction === 'ascending' ? av - bv : bv - av;
              }

              return direction === 'ascending'
                ? av.localeCompare(bv)
                : bv.localeCompare(av);
            })
            .forEach(function (row) {
              tbody.appendChild(row);
            });
        }

        th.addEventListener('click', function () {
          sort(th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending');
        });

        th.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            sort(th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending');
          }

          if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
            sort(event.key === 'ArrowUp' ? 'ascending' : 'descending');
          }
        });
      });

      table.dataset.sortableInitialized = 'true';
    });
  }

  initSortableTables();

  /* -----------------------------------------
     4. COOKIE MONSTER – GOV.UK STYLE MINIMAL
     ----------------------------------------- */

  // Bump the version so old prefs don't hide the new banner
  var COOKIE_KEY = 'pera_cookie_pref_v2';
  var banner     = document.getElementById('cookie-banner');

  var btnAcceptAll = document.getElementById('cookie-accept-all');
  var btnReject    = document.getElementById('cookie-reject');
  var btnSave      = document.getElementById('cookie-save');   // may not exist (hidden in GOV layout)
  var btnManage    = document.getElementById('cookie-manage');
  var chkAnalytics = document.getElementById('cookie-analytics');
  var chkMarketing = document.getElementById('cookie-marketing');

  function getPrefs() {
    try {
      var raw = localStorage.getItem(COOKIE_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function savePrefs(prefs) {
    try {
      localStorage.setItem(COOKIE_KEY, JSON.stringify(prefs));
    } catch (e) {
      // storage blocked, ignore
    }
  }

  function applyPrefs(prefs) {
    if (!prefs) return;

    if (chkAnalytics && typeof prefs.analytics === 'boolean') {
      chkAnalytics.checked = prefs.analytics;
    }
    if (chkMarketing && typeof prefs.marketing === 'boolean') {
      chkMarketing.checked = prefs.marketing;
    }

    // Load tracking only when:
    // 1) user has consented, AND
    // 2) the page has a tracking config (VOP only for now)
    if (prefs.analytics && window.peraTrackingConfig) {
      initGA();
    }
    if (prefs.marketing && window.peraTrackingConfig) {
      initMetaPixel();
    }
  }



  function showBanner() {
    banner.classList.add('cookie-banner--visible');
  }

  function hideBanner() {
    banner.classList.remove('cookie-banner--visible');
    banner.classList.remove('manage-open');
  }

  // Global hook for “Cookie settings” link in footer
  window.peraOpenCookieSettings = function () {
    if (!banner) {
      return;
    }

    var prefs = getPrefs();
    if (prefs) {
      applyPrefs(prefs);
    }
    banner.classList.add('manage-open');  // open directly in detailed mode
    showBanner();
  };

  // Initial load: apply existing consent first.
  // gtag.js still loads only when analytics consent is true.
  var existing = getPrefs();
  if (existing) {
    applyPrefs(existing);
  }

  // If there is no cookie banner on this page, stop after applying saved consent.
  if (!banner) {
    return;
  }

  // Show banner only when there are no saved preferences.
  if (!existing) {
    showBanner();
  }

  // Button: Accept all cookies
  if (btnAcceptAll) {
    btnAcceptAll.addEventListener('click', function () {
      var prefs = {
        necessary: true,
        analytics: true,
        marketing: true,
        timestamp: Date.now()
      };
      savePrefs(prefs);
      applyPrefs(prefs);
      hideBanner();
    });
  }

  // Button: Reject optional cookies
  if (btnReject) {
    btnReject.addEventListener('click', function () {
      var prefs = {
        necessary: true,
        analytics: false,
        marketing: false,
        timestamp: Date.now()
      };
      savePrefs(prefs);
      applyPrefs(prefs);
      hideBanner();
    });
  }

  // Button: Save choices (if you ever show it in detailed view)
  if (btnSave) {
    btnSave.addEventListener('click', function () {
      var prefs = {
        necessary: true,
        analytics: !!(chkAnalytics && chkAnalytics.checked),
        marketing: !!(chkMarketing && chkMarketing.checked),
        timestamp: Date.now()
      };
      savePrefs(prefs);
      applyPrefs(prefs);
      hideBanner();
    });
  }

  // Button: Manage cookie settings → toggle expanded GOV-style panel
  if (btnManage) {
    btnManage.addEventListener('click', function () {
      banner.classList.toggle('manage-open');
    });
  }

  const header = document.getElementById('site-header');
  if (header) {
    const SCROLL_TRIGGER = 12;
    let last = null;
    let ticking = false;

    function syncHeaderOffsetVars() {
      var headerHeight = Math.max(0, Math.round(header.getBoundingClientRect().height || header.offsetHeight || 0));
      if (!headerHeight) {
        return;
      }

      document.documentElement.style.setProperty('--header-height', headerHeight + 'px');
      document.documentElement.style.setProperty('--sticky-offset', headerHeight + 'px');
    }

    function setHeaderState() {
      const isScrolled = window.scrollY > SCROLL_TRIGGER;
      if (isScrolled !== last) {
        header.classList.toggle('is-scrolled', isScrolled);
        last = isScrolled;
      }
    }

    function requestTick() {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(function () {
          syncHeaderOffsetVars();
          setHeaderState();
          ticking = false;
        });
      }
    }

    syncHeaderOffsetVars();
    setHeaderState();
    window.addEventListener('scroll', requestTick, { passive: true });
    window.addEventListener('resize', requestTick);
  }

});
