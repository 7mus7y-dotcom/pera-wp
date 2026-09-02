(function (window, document) {
  'use strict';
  var config = window.PeraCurrencyConfig || {};
  var supported = config.supported || { USD: { symbol: '$', rounding: 1 } };
  var toastHideTimer = null, toastRemoveTimer = null;
  function validAmount(value) { return (typeof value === 'number' || /^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/.test(value)) && Number.isFinite(Number(value)) && Number(value) >= 0; }
  function validCode(code) { return typeof code === 'string' && Object.prototype.hasOwnProperty.call(supported, code.toUpperCase()); }
  function selected() { var value = 'USD'; try { value = window.localStorage.getItem(config.storageKey) || 'USD'; } catch (e) {} return validCode(value) ? value.toUpperCase() : 'USD'; }
  function effectiveSelected() { var requested = selected(); return requested === 'USD' || rate(requested) !== null ? requested : 'USD'; }
  function setSelected(code) { var previous = selected(); code = validCode(code) ? code.toUpperCase() : 'USD'; try { window.localStorage.setItem(config.storageKey, code); } catch (e) {} var secure = window.location.protocol === 'https:' ? '; Secure' : ''; document.cookie = config.storageKey + '=' + encodeURIComponent(code) + '; Path=/; Max-Age=31536000; SameSite=Lax' + secure; render(document); window.dispatchEvent(new CustomEvent('pera:currency-change', { detail: { currency: code } })); if (code !== previous && effectiveSelected() === code) showToast(code); return code; }
  function rate(code) { code = validCode(code) ? code.toUpperCase() : ''; if (code === 'USD') return 1; var value = config.rates && Number(config.rates[code]); return Number.isFinite(value) && value > 0 && config.state !== 'expired' ? value : null; }
  function roundHalfUp(value, increment) { return Math.floor((value / increment) + 0.5 + Number.EPSILON) * increment; }
  function convert(amount, code) { if (!validAmount(amount) || !validCode(code || selected())) return null; var requested = (code || selected()).toUpperCase(), fx = rate(requested), fallback = fx === null; var currency = fallback ? 'USD' : requested, raw = Number(amount) * (fallback ? 1 : fx), increment = Number((config.rounding || {})[currency] || supported[currency].rounding); return { amount: roundHalfUp(raw, increment), raw: raw, currency: currency, requestedCurrency: requested, fallback: fallback, snapshotId: currency === 'USD' ? null : config.snapshotId }; }
  function convertInputFromUsd(amount, code) { if (!validAmount(amount) || !validCode(code || selected())) return null; var requested = (code || selected()).toUpperCase(), fx = rate(requested), fallback = fx === null; return { amount: Math.round(Number(amount) * (fallback ? 1 : fx)), currency: fallback ? 'USD' : requested, fallback: fallback }; }
  function convertInputToUsd(amount, code, boundary) { if (!validAmount(amount) || !validCode(code || selected())) return null; var requested = (code || selected()).toUpperCase(), fx = rate(requested); if (fx === null) { requested = 'USD'; fx = 1; } var raw = Number(amount) / fx, rounded = boundary === 'min' ? Math.floor(raw) : (boundary === 'max' ? Math.ceil(raw) : Math.round(raw)); return { amount: rounded, raw: raw, currency: 'USD', sourceCurrency: requested, fallback: requested === 'USD' && (code || selected()).toUpperCase() !== 'USD' }; }
  function formatInput(amount, code) { var result = convertInputFromUsd(amount, code); return result ? supported[result.currency].symbol + Math.trunc(result.amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''; }
  function format(amount, code) { var result = convert(amount, code); return result ? supported[result.currency].symbol + Math.trunc(result.amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''; }
  function formatRange(min, max, code) { var hasMax = max !== null && max !== '' && typeof max !== 'undefined', a = convert(min, code), b = hasMax ? convert(max, code) : null; if (!a || (hasMax && !b) || (hasMax && Number(max) < Number(min))) return { min: '', max: '', currency: 'USD', snapshotId: null, fallback: true, valid: false }; if (b && b.amount === a.amount) b = null; return { min: format(min, code), max: b ? format(max, code) : '', currency: a.currency, snapshotId: a.snapshotId, fallback: a.fallback, valid: true }; }
  function render(root) { Array.prototype.forEach.call((root || document).querySelectorAll('[data-pera-money]'), function (node) { var result = formatRange(node.getAttribute('data-usd-min'), node.getAttribute('data-usd-max'), selected()); if (!result.valid) return; node.textContent = result.max ? result.min + '\u2013' + result.max : result.min; node.setAttribute('dir', 'ltr'); }); }
  function showToast(code) {
    var copy = config.toast && config.toast[code];
    if (!copy || !document.body) return;
    var toast = document.querySelector('.pera-currency-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'pera-currency-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      toast.setAttribute('aria-atomic', 'true');
      toast.innerHTML = '<strong class="pera-currency-toast__title"></strong><span class="pera-currency-toast__body"></span>';
      document.body.appendChild(toast);
    }
    window.clearTimeout(toastHideTimer);
    window.clearTimeout(toastRemoveTimer);
    toast.querySelector('.pera-currency-toast__title').textContent = copy.title;
    toast.querySelector('.pera-currency-toast__body').textContent = copy.body;
    toast.classList.remove('is-leaving');
    toast.classList.add('is-visible');
    toastHideTimer = window.setTimeout(function () {
      toast.classList.add('is-leaving');
      toast.classList.remove('is-visible');
      toastRemoveTimer = window.setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 250);
    }, 5000);
  }
  window.PeraCurrency = { supported: function () { return supported; }, selected: selected, effectiveSelected: effectiveSelected, setSelected: setSelected, getRate: rate, convert: convert, convertInputFromUsd: convertInputFromUsd, convertInputToUsd: convertInputToUsd, format: format, formatInput: formatInput, formatRange: formatRange, render: render };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { render(document); });
  else render(document);
}(window, document));
