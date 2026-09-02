(function (window, document) {
  'use strict';

  function api() { return window.PeraCurrency; }
  function selectors() { return document.querySelectorAll('[data-pera-currency-selector]'); }
  function effectiveCode() {
    var currency = api();
    if (!currency) return 'USD';
    return typeof currency.effectiveSelected === 'function' ? currency.effectiveSelected() : currency.selected();
  }
  function close(selector, restoreFocus) {
    var trigger = selector.querySelector('[data-pera-currency-trigger]');
    selector.classList.remove('is-open');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus && trigger) trigger.focus();
  }
  function sync() {
    var code = effectiveCode();
    Array.prototype.forEach.call(selectors(), function (selector) {
      var trigger = selector.querySelector('[data-pera-currency-trigger]');
      var current = selector.querySelector('[data-pera-currency-code]');
      if (current) current.textContent = code;
      if (trigger) trigger.setAttribute('aria-label', (selector.getAttribute('data-pera-currency-label') || 'Currency') + ': ' + code);
      Array.prototype.forEach.call(selector.querySelectorAll('[data-pera-currency-option]'), function (option) {
        var active = option.getAttribute('data-pera-currency-option') === code;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-selected', String(active));
      });
    });
  }
  function init() {
    if (!api()) return;
    Array.prototype.forEach.call(selectors(), function (selector) {
      var trigger = selector.querySelector('[data-pera-currency-trigger]');
      if (trigger) trigger.addEventListener('click', function () {
        var open = !selector.classList.contains('is-open');
        Array.prototype.forEach.call(selectors(), function (other) { if (other !== selector) close(other); });
        selector.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', String(open));
        if (open) {
          var active = selector.querySelector('[data-pera-currency-option].is-active');
          if (active) active.focus();
        }
      });
      Array.prototype.forEach.call(selector.querySelectorAll('[data-pera-currency-option]'), function (option) {
        option.addEventListener('click', function () {
          api().setSelected(option.getAttribute('data-pera-currency-option'));
          close(selector, !!trigger);
        });
      });
      selector.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { close(selector, true); }
      });
    });
    document.addEventListener('click', function (event) {
      Array.prototype.forEach.call(selectors(), function (selector) {
        if (!selector.contains(event.target)) close(selector);
      });
    });
    window.addEventListener('pera:currency-change', sync);
    sync();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}(window, document));
