const fs = require('fs'); const vm = require('vm');
const fixtures = JSON.parse(fs.readFileSync(__dirname + '/fixtures/golden.json'));
const document = { cookie: '', querySelectorAll: () => [] }; const window = { document, location: { protocol: 'https:' }, localStorage: { getItem: () => 'USD', setItem: () => {} }, dispatchEvent: () => {}, CustomEvent: function () {} };
const context = { window, document, CustomEvent: window.CustomEvent, Number, Math }; vm.createContext(context);
for (const f of fixtures) { window.PeraCurrencyConfig = { storageKey: 'pera_currency', state: 'fresh', snapshotId: 'fixture', supported: { USD: { symbol: '$', rounding: 1 }, EUR: { symbol: '€', rounding: 1000 }, GBP: { symbol: '£', rounding: 1000 } }, rates: { USD: 1, EUR: f.currency === 'EUR' ? f.rate : .86, GBP: f.currency === 'GBP' ? f.rate : .75 }, rounding: { USD: 1, EUR: 1000, GBP: 1000 } }; vm.runInContext(fs.readFileSync(__dirname + '/../assets/js/currency.js', 'utf8'), context); if (window.PeraCurrency.format(f.usd, f.currency) !== f.expected) throw new Error('fixture failed: ' + JSON.stringify(f)); }
if (window.PeraCurrency.convert(-1, 'USD') !== null || window.PeraCurrency.convert(1, 'CAD') !== null) throw new Error('invalid input accepted');
console.log('JavaScript currency tests passed');
