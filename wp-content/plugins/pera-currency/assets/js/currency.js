(function (window, document) {
  'use strict';
  var config = window.PeraCurrencyConfig || {};
  var supported = config.supported || { USD: { symbol: '$', rounding: 1 } };
  function validAmount(value) { return (typeof value === 'number' || /^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/.test(value)) && Number.isFinite(Number(value)) && Number(value) >= 0; }
  function validCode(code) { return typeof code === 'string' && Object.prototype.hasOwnProperty.call(supported, code.toUpperCase()); }
  function selected() { var value = 'USD'; try { value = window.localStorage.getItem(config.storageKey) || 'USD'; } catch (e) {} return validCode(value) ? value.toUpperCase() : 'USD'; }
  function setSelected(code) { code = validCode(code) ? code.toUpperCase() : 'USD'; try { window.localStorage.setItem(config.storageKey, code); } catch (e) {} var secure = window.location.protocol === 'https:' ? '; Secure' : ''; document.cookie = config.storageKey + '=' + encodeURIComponent(code) + '; Path=/; Max-Age=31536000; SameSite=Lax' + secure; render(document); window.dispatchEvent(new CustomEvent('pera:currency-change', { detail: { currency: code } })); return code; }
  function rate(code) { code = validCode(code) ? code.toUpperCase() : ''; if (code === 'USD') return 1; var value = config.rates && Number(config.rates[code]); return Number.isFinite(value) && value > 0 && config.state !== 'expired' ? value : null; }
  function roundHalfUp(value, increment) { return Math.floor((value / increment) + 0.5 + Number.EPSILON) * increment; }
  function convert(amount, code) { if (!validAmount(amount) || !validCode(code || selected())) return null; var requested = (code || selected()).toUpperCase(), fx = rate(requested), fallback = fx === null; var currency = fallback ? 'USD' : requested, raw = Number(amount) * (fallback ? 1 : fx), increment = Number((config.rounding || {})[currency] || supported[currency].rounding); return { amount: roundHalfUp(raw, increment), raw: raw, currency: currency, requestedCurrency: requested, fallback: fallback, snapshotId: currency === 'USD' ? null : config.snapshotId }; }
  function format(amount, code) { var result = convert(amount, code); return result ? supported[result.currency].symbol + Math.trunc(result.amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''; }
  function formatRange(min, max, code) { var hasMax = max !== null && max !== '' && typeof max !== 'undefined', a = convert(min, code), b = hasMax ? convert(max, code) : null; if (!a || (hasMax && !b) || (hasMax && Number(max) < Number(min))) return { min: '', max: '', currency: 'USD', snapshotId: null, fallback: true, valid: false }; if (b && b.amount === a.amount) b = null; return { min: format(min, code), max: b ? format(max, code) : '', currency: a.currency, snapshotId: a.snapshotId, fallback: a.fallback, valid: true }; }
  function render(root) { Array.prototype.forEach.call((root || document).querySelectorAll('[data-pera-money]'), function (node) { var result = formatRange(node.getAttribute('data-usd-min'), node.getAttribute('data-usd-max'), selected()); if (!result.valid) return; node.textContent = result.max ? result.min + '\u2013' + result.max : result.min; node.setAttribute('dir', 'ltr'); }); }
  window.PeraCurrency = { supported: function () { return supported; }, selected: selected, setSelected: setSelected, getRate: rate, convert: convert, format: format, formatRange: formatRange, render: render };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { render(document); });
  else render(document);
}(window, document));
