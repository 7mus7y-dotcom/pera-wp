/* Permanent property summary; observer-driven visibility with no scroll polling. */
(function () {
  'use strict';
  function init() {
    var bar = document.querySelector('[data-property-sticky-summary]');
    var contact = document.getElementById('contact-form');
    var form = contact && contact.querySelector('.enquiry-cta-form');
    var header = document.getElementById('site-header');
    var floating = document.getElementById('floating-whatsapp');
    if (!bar || !form || !header || !window.IntersectionObserver || !window.ResizeObserver) return;
    var trigger = document.createElement('span');
    trigger.setAttribute('aria-hidden', 'true');
    trigger.style.cssText = 'position:absolute;inset-inline-start:0;top:0;width:1px;height:1px;opacity:0;pointer-events:none;';
    document.body.appendChild(trigger);
    var mobile = window.matchMedia('(max-width: 768px)');
    var slot = bar.querySelector('.property-sticky-summary__whatsapp');
    var home = document.createComment('Floating WhatsApp home');
    if (floating) floating.before(home);
    var headerBottom = 0;
    var observer;
    function update() {
      var pastHeader = trigger.getBoundingClientRect().bottom <= 0;
      var rect = form.getBoundingClientRect();
      var formVisible = rect.bottom > headerBottom && rect.top < window.innerHeight;
      var blocked = document.body.matches('.is-nav-open, .is-lightbox-open, .pera-offer-video-modal-open') ||
        document.querySelector('.cookie-banner--visible, dialog[open], #site-header [aria-expanded="true"], #site-header .is-open');
      var visible = pastHeader && !formVisible && !blocked;
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
      trigger.style.top = headerBottom + 'px';
      contact.style.scrollMarginTop = (headerBottom + 16) + 'px';
      if (observer) observer.disconnect();
      observer = new IntersectionObserver(update, { threshold: 0 });
      [trigger, form].forEach(function (element) { observer.observe(element); });
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
