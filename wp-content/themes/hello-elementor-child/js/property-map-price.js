(function (window) {
  'use strict';

  const usd = (amount) => `$${Math.trunc(Number(amount)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;

  const format = (property, fromFormat) => {
    const min = Number(property && property.price_min) || 0;
    const max = Number(property && property.price_max) || min;
    const mode = property && ['from', 'single', 'range'].includes(property.price_mode) ? property.price_mode : 'single';
    if (min <= 0) return { prefix: '', money: '', suffix: '' };

    const currency = window.PeraCurrency;
    let money = '';
    if (currency && typeof currency.format === 'function' && typeof currency.formatRange === 'function') {
      if (mode === 'range' && max !== min) {
        const range = currency.formatRange(min, max);
        money = range && range.valid ? (range.max ? `${range.min}\u2013${range.max}` : range.min) : '';
      } else {
        money = currency.format(min);
      }
    }

    if (!money) {
      money = mode === 'range' && max !== min ? `${usd(min)}\u2013${usd(max)}` : usd(min);
    }

    const semantic = mode === 'from' ? String(fromFormat || 'From %s').split('%s') : ['', ''];
    return { prefix: semantic[0] || '', money, suffix: semantic.slice(1).join('%s') || '' };
  };

  const render = (root, property, fromFormat) => {
    if (!root) return;
    const value = format(property, fromFormat);
    const prefix = root.querySelector('[data-map-price-prefix]');
    const money = root.querySelector('[data-map-price-money]');
    const suffix = root.querySelector('[data-map-price-suffix]');
    if (prefix) {
      prefix.textContent = value.prefix;
      prefix.hidden = !value.prefix;
    }
    if (money) {
      money.textContent = value.money;
      money.setAttribute('dir', 'ltr');
    }
    if (suffix) {
      suffix.textContent = value.suffix;
      suffix.hidden = !value.suffix;
    }
  };

  window.PeraPropertyMapPrice = { format, render };
}(window));
