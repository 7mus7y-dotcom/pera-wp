/* Administrator layout preview only; no scroll listeners or pricing calculations. */
(function () {
  'use strict';
  function init() {
    var bar = document.querySelector('[data-property-sticky-preview]');
    var hero = document.querySelector('.property-hero');
    var overview = document.getElementById('property-overview');
    var contact = document.getElementById('contact-form');
    var form = contact && contact.querySelector('.enquiry-cta-form');
    var header = document.getElementById('site-header');
    var floating = document.getElementById('floating-whatsapp');
    if (!bar || !hero || !overview || !form || !header || !window.IntersectionObserver || !window.ResizeObserver) return;
    var mobile = window.matchMedia('(max-width: 768px)');
    var slot = bar.querySelector('.property-sticky-preview__whatsapp');
    var home = document.createComment('Floating WhatsApp home');
    if (floating) floating.before(home);
    var headerBottom = 0;
    var observer;
    function update() {
      var pastIntro = Math.max(hero.getBoundingClientRect().bottom, overview.getBoundingClientRect().bottom) <= headerBottom;
      var rect = form.getBoundingClientRect();
      var formVisible = rect.bottom > headerBottom && rect.top < window.innerHeight;
      var blocked = document.body.matches('.is-nav-open, .is-lightbox-open, .pera-offer-video-modal-open') ||
        document.querySelector('.cookie-banner--visible, dialog[open], #site-header [aria-expanded="true"], #site-header .is-open');
      var visible = pastIntro && !formVisible && !blocked;
      // Move the original node: preserve its URL, translated label, tracking and listeners.
      if (floating) {
        if (visible && mobile.matches) {
          if (floating.parentNode !== slot) slot.appendChild(floating);
        } else if (floating.parentNode === slot) {
          home.after(floating);
        }
      }
      if (!visible && bar.contains(document.activeElement)) {
        header.querySelector('a, button')?.focus({ preventScroll: true });
      }
      bar.hidden = !visible;
      bar.inert = !visible;
    }
    function measure() {
      var rect = header.getBoundingClientRect();
      headerBottom = Math.max(0, rect.height + (parseFloat(getComputedStyle(header).top) || 0));
      bar.style.setProperty('--property-sticky-header', headerBottom + 'px');
      contact.style.scrollMarginTop = (headerBottom + 16) + 'px';
      if (observer) observer.disconnect();
      observer = new IntersectionObserver(update, { rootMargin: '-' + headerBottom + 'px 0px 0px 0px', threshold: 0 });
      [hero, overview, form].forEach(function (element) { observer.observe(element); });
      update();
    }
    new ResizeObserver(measure).observe(header);
    var stateObserver = new MutationObserver(update);
    stateObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    [header, document.getElementById('cookie-banner')].filter(Boolean).forEach(function (element) {
      stateObserver.observe(element, { attributes: true, subtree: true, attributeFilter: ['class', 'aria-expanded', 'open'] });
    });
    document.querySelectorAll('dialog').forEach(function (element) {
      stateObserver.observe(element, { attributes: true, attributeFilter: ['open'] });
    });
    mobile.addEventListener('change', measure);
    window.addEventListener('resize', measure);
    window.addEventListener('pageshow', measure);
    bar.querySelector('a[href="#contact-form"]').addEventListener('click', function (event) {
      event.preventDefault();
      // Keep the existing anchor target; focus the actual form for keyboard users.
      var target = form.querySelector('input:not([type="hidden"]):not([tabindex="-1"]), select, textarea');
      if (target) target.focus({ preventScroll: true });
      contact.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    });
    measure();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
