/* Pera Manage shell: same header/off-canvas class contract as the public site,
   without loading public analytics, consent, gallery or WhatsApp scripts. */
document.addEventListener('DOMContentLoaded', function () {
  var header = document.getElementById('site-header');
  var toggle = document.querySelector('.header-menu-toggle');
  var panel = document.getElementById('pera-offcanvas-menu');
  var close = document.querySelector('.offcanvas-close');
  var backdrop = document.querySelector('.offcanvas-backdrop');
  var lastFocus = null;
  var ticking = false;

  function syncHeader() {
    if (!header) return;
    var height = Math.round(header.getBoundingClientRect().height || header.offsetHeight || 0);
    if (height) {
      document.documentElement.style.setProperty('--header-height', height + 'px');
      document.documentElement.style.setProperty('--sticky-offset', height + 'px');
    }
    header.classList.toggle('is-scrolled', window.scrollY > 12);
    ticking = false;
  }
  function queueHeader() {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(syncHeader);
  }
  syncHeader();
  window.addEventListener('scroll', queueHeader, { passive: true });
  window.addEventListener('resize', queueHeader);

  if (!toggle || !panel || !close || !backdrop) return;
  function setOpen(open) {
    document.body.classList.toggle('is-nav-open', open);
    panel.inert = !open;
    panel.setAttribute('aria-hidden', String(!open));
    toggle.setAttribute('aria-expanded', String(open));
    if (open) {
      lastFocus = document.activeElement;
      close.focus();
    } else {
      (lastFocus && typeof lastFocus.focus === 'function' ? lastFocus : toggle).focus();
    }
  }
  setOpen(false);
  toggle.addEventListener('click', function () { setOpen(!document.body.classList.contains('is-nav-open')); });
  close.addEventListener('click', function () { setOpen(false); });
  backdrop.addEventListener('click', function () { setOpen(false); });
  panel.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () { setOpen(false); });
  });
  document.addEventListener('keydown', function (event) {
    if (!document.body.classList.contains('is-nav-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      setOpen(false);
    }
    if (event.key === 'Tab') {
      var items = Array.prototype.slice.call(panel.querySelectorAll('a[href], button:not([disabled])'));
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
});
