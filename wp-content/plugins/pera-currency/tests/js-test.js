const fs = require('fs');
const vm = require('vm');

const fixtures = JSON.parse(fs.readFileSync(__dirname + '/fixtures/golden.json'));
const ranges = JSON.parse(fs.readFileSync(__dirname + '/fixtures/ranges.json'));
const source = fs.readFileSync(__dirname + '/../assets/js/currency.js', 'utf8');

function loadRuntime(options = {}) {
  const nodes = options.nodes || [];
  const listeners = {};
  const storage = { value: options.selected || 'USD', writes: [] };
  const timers = new Map();
  let nextTimer = 1;
  const body = { children: [], appendChild(node) { node.parentNode = this; this.children.push(node); }, removeChild(node) { this.children.splice(this.children.indexOf(node), 1); node.parentNode = null; } };
  function makeElement() {
    const classes = new Set();
    const children = {};
    return {
      parentNode: null, attributes: {},
      set className(value) { classes.clear(); value.split(/\s+/).filter(Boolean).forEach((name) => classes.add(name)); },
      get className() { return Array.from(classes).join(' '); },
      classList: { add: (...names) => names.forEach((name) => classes.add(name)), remove: (...names) => names.forEach((name) => classes.delete(name)), contains: (name) => classes.has(name) },
      setAttribute(name, value) { this.attributes[name] = value; },
      set innerHTML(value) { this._innerHTML = value; children['.pera-currency-toast__title'] = { textContent: '' }; children['.pera-currency-toast__body'] = { textContent: '' }; },
      querySelector(selector) { return children[selector] || null; }
    };
  }
  const document = {
    cookie: '', body,
    readyState: options.readyState || 'complete',
    querySelectorAll: () => nodes,
    querySelector: (selector) => selector === '.pera-currency-toast' ? (body.children[0] || null) : null,
    createElement: () => makeElement(),
    addEventListener: (name, callback) => { listeners[name] = callback; }
  };
  const window = {
    document,
    location: { protocol: 'https:' },
    localStorage: {
      getItem: () => storage.value,
      setItem: (key, value) => { storage.value = value; storage.writes.push([key, value]); }
    },
    setTimeout: (callback, delay) => { const id = nextTimer++; timers.set(id, { callback, delay }); return id; },
    clearTimeout: (id) => timers.delete(id),
    dispatchEvent: () => {},
    CustomEvent: function () {},
    PeraCurrencyConfig: {
      storageKey: 'pera_currency',
      state: options.state || 'fresh',
      snapshotId: 'fixture',
      supported: {
        USD: { symbol: '$', rounding: 1 },
        EUR: { symbol: '€', rounding: 1000 },
        GBP: { symbol: '£', rounding: 1000 }
      },
      rates: options.rates || { USD: 1, EUR: .86, GBP: .75 },
      rounding: { USD: 1, EUR: 1000, GBP: 1000 },
      toast: {
        USD: { title: 'Currency changed to USD', body: 'Prices are now displayed in US dollars.' },
        EUR: { title: 'Currency changed to EUR', body: 'Prices are now displayed in euros.' },
        GBP: { title: 'Currency changed to GBP', body: 'Prices are now displayed in British pounds.' }
      }
    }
  };
  const context = { window, document, CustomEvent: window.CustomEvent, Number, Math };
  vm.createContext(context);
  vm.runInContext(source, context);
  return { api: window.PeraCurrency, listeners, storage, body, timers };
}

for (const fixture of fixtures) {
  const rates = {
    USD: 1,
    EUR: fixture.currency === 'EUR' ? fixture.rate : .86,
    GBP: fixture.currency === 'GBP' ? fixture.rate : .75
  };
  const { api } = loadRuntime({ rates });
  if (api.format(fixture.usd, fixture.currency) !== fixture.expected) {
    throw new Error('fixture failed: ' + JSON.stringify(fixture));
  }
}

const { api } = loadRuntime();
for (const fixture of ranges) {
  const max = Object.prototype.hasOwnProperty.call(fixture, 'max') ? fixture.max : undefined;
  const result = api.formatRange(fixture.min, max, 'EUR');
  if (result.valid !== fixture.valid || (fixture.valid && (result.min !== fixture.minText || result.max !== fixture.maxText))) {
    throw new Error('range fixture failed: ' + fixture.name + ': ' + JSON.stringify(result));
  }
}

if (api.convert(-1, 'USD') !== null || api.convert(1, 'CAD') !== null) {
  throw new Error('invalid input accepted');
}

const unavailableFx = loadRuntime({ selected: 'EUR', state: 'expired', rates: { USD: 1 } });
if (unavailableFx.api.selected() !== 'EUR' || unavailableFx.api.effectiveSelected() !== 'USD') {
  throw new Error('effective selection did not preserve the requested preference while falling back to USD');
}

const inputApi = loadRuntime({ rates: { USD: 1, EUR: .86, GBP: .75 } }).api;
if (inputApi.convertInputFromUsd(300000, 'EUR').amount !== 258000 || inputApi.formatInput(300000, 'EUR') !== '€258,000') {
  throw new Error('filter input USD-to-EUR conversion failed');
}
if (inputApi.convertInputToUsd(258000, 'EUR', 'min').amount !== 300000 || inputApi.convertInputToUsd(337501, 'GBP', 'max').amount !== 450002) {
  throw new Error('filter input inverse boundary conversion failed');
}
let canonicalUsd = 300000;
for (const code of ['USD', 'EUR', 'GBP', 'USD']) {
  inputApi.convertInputFromUsd(canonicalUsd, code);
}
if (canonicalUsd !== 300000) throw new Error('display currency switching mutated canonical USD');

const node = {
  textContent: '$450,000',
  attributes: { 'data-usd-min': '450000' },
  getAttribute(name) { return this.attributes[name] ?? null; },
  setAttribute(name, value) { this.attributes[name] = value; }
};
loadRuntime({ selected: 'EUR', nodes: [node] });
if (node.textContent !== '€387,000' || node.attributes.dir !== 'ltr') {
  throw new Error('persisted currency was not rehydrated at startup');
}

const deferredNode = Object.assign({}, node, { textContent: '$450,000', attributes: { 'data-usd-min': '450000' } });
const deferred = loadRuntime({ selected: 'GBP', nodes: [deferredNode], readyState: 'loading' });
if (deferredNode.textContent !== '$450,000' || typeof deferred.listeners.DOMContentLoaded !== 'function') {
  throw new Error('startup render was not deferred while the DOM was loading');
}
deferred.listeners.DOMContentLoaded();
if (deferredNode.textContent !== '£338,000') {
  throw new Error('persisted currency was not rehydrated after DOMContentLoaded');
}

const toastRuntime = loadRuntime();
if (toastRuntime.body.children.length !== 0) throw new Error('initial hydration displayed a currency toast');
toastRuntime.api.setSelected('EUR');
const toast = toastRuntime.body.children[0];
if (!toast || toast.attributes.role !== 'status' || toast.attributes['aria-live'] !== 'polite' || toast.attributes['aria-atomic'] !== 'true') throw new Error('toast status semantics are missing');
if (toast.querySelector('.pera-currency-toast__title').textContent !== 'Currency changed to EUR') throw new Error('USD to EUR confirmation copy is incorrect');
const firstHideTimer = Array.from(toastRuntime.timers.entries()).find((entry) => entry[1].delay === 5000)[0];
toastRuntime.api.setSelected('GBP');
if (toastRuntime.body.children.length !== 1 || toastRuntime.body.children[0] !== toast) throw new Error('rapid changes stacked currency toasts');
if (toast.querySelector('.pera-currency-toast__title').textContent !== 'Currency changed to GBP' || toastRuntime.timers.has(firstHideTimer)) throw new Error('rapid change did not update the toast and reset its timer');
toastRuntime.api.setSelected('GBP');
if (toastRuntime.body.children.length !== 1 || toastRuntime.timers.size !== 1) throw new Error('selecting the active currency restarted the toast');
const hideTimer = Array.from(toastRuntime.timers.values()).find((timer) => timer.delay === 5000);
hideTimer.callback();
const removeTimer = Array.from(toastRuntime.timers.values()).find((timer) => timer.delay === 250);
if (!removeTimer || toast.classList.contains('is-visible')) throw new Error('toast did not begin fading after five seconds');
removeTimer.callback();
if (toastRuntime.body.children.length !== 0) throw new Error('toast was not removed after fading');

console.log('JavaScript currency tests passed');
