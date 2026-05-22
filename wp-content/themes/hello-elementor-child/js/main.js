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
    analyticsLoaded = true;

    if (!window.peraTrackingConfig || !window.peraTrackingConfig.gaMeasurementId) return;
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

  function trackWhatsAppLeadClicks() {
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!target || !target.closest) return;

      var link = target.closest('a[href]');
      if (!link) return;
      if (link.__peraMetaLeadTracked) return;

      var href = (link.getAttribute('href') || '').toLowerCase();
      if (!href) return;

      var isWhatsAppLink = href.indexOf('wa.me') !== -1 ||
        href.indexOf('api.whatsapp.com') !== -1 ||
        href.indexOf('whatsapp') !== -1;

      if (!isWhatsAppLink) return;
      if (typeof window.fbq !== 'function') return;

      link.__peraMetaLeadTracked = true;
      window.fbq('track', 'Lead', {
        content_name: 'WhatsApp Click',
        content_category: 'Citizenship Lead'
      });
    }, true);
  }

  trackWhatsAppLeadClicks();


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

  // If there is no cookie banner on this page, do nothing
  if (!banner) {
    return;
  }

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
    var prefs = getPrefs();
    if (prefs) {
      applyPrefs(prefs);
    }
    banner.classList.add('manage-open');  // open directly in detailed mode
    showBanner();
  };

  // Initial load: show banner if no prefs
  var existing = getPrefs();
  if (!existing) {
    // No preference stored yet → show banner
    showBanner();
  } else {
    // Already have prefs → apply silently (no banner)
    applyPrefs(existing);
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
