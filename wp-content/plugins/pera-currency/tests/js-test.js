const fs = require('fs');
const vm = require('vm');

const fixtures = JSON.parse(fs.readFileSync(__dirname + '/fixtures/golden.json'));
const ranges = JSON.parse(fs.readFileSync(__dirname + '/fixtures/ranges.json'));
const source = fs.readFileSync(__dirname + '/../assets/js/currency.js', 'utf8');

function loadRuntime(options = {}) {
  const nodes = options.nodes || [];
  const listeners = {};
  const storage = { value: options.selected || 'USD', writes: [] };
  const document = {
    cookie: '',
    readyState: options.readyState || 'complete',
    querySelectorAll: () => nodes,
    addEventListener: (name, callback) => { listeners[name] = callback; }
  };
  const window = {
    document,
    location: { protocol: 'https:' },
    localStorage: {
      getItem: () => storage.value,
      setItem: (key, value) => { storage.value = value; storage.writes.push([key, value]); }
    },
    dispatchEvent: () => {},
    CustomEvent: function () {},
    PeraCurrencyConfig: {
      storageKey: 'pera_currency',
      state: 'fresh',
      snapshotId: 'fixture',
      supported: {
        USD: { symbol: '$', rounding: 1 },
        EUR: { symbol: '€', rounding: 1000 },
        GBP: { symbol: '£', rounding: 1000 }
      },
      rates: options.rates || { USD: 1, EUR: .86, GBP: .75 },
      rounding: { USD: 1, EUR: 1000, GBP: 1000 }
    }
  };
  const context = { window, document, CustomEvent: window.CustomEvent, Number, Math };
  vm.createContext(context);
  vm.runInContext(source, context);
  return { api: window.PeraCurrency, listeners, storage };
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

console.log('JavaScript currency tests passed');
