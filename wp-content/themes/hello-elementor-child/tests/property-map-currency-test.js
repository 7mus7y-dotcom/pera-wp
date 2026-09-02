const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(`${__dirname}/../js/property-map-price.js`, 'utf8');
const window = {};
vm.runInNewContext(source, { window });
const price = window.PeraPropertyMapPrice;
const property = { price_min: 450000, price_max: 600000, price_mode: 'from' };

assert.deepStrictEqual({ ...price.format(property, 'From %s') }, { prefix: 'From ', money: '$450,000', suffix: '' });
assert.strictEqual(property.price_min, 450000);

let selected = 'EUR';
const rates = { USD: 1, EUR: 0.862, GBP: 0.75 };
const symbol = { USD: '$', EUR: '€', GBP: '£' };
const formatted = (amount) => `${symbol[selected]}${(selected === 'USD' ? amount : Math.floor((amount * rates[selected]) / 1000 + 0.5) * 1000).toLocaleString('en-US')}`;
window.PeraCurrency = {
  format: formatted,
  formatRange(min, max) { return { min: formatted(min), max: formatted(max), valid: true }; }
};

assert.strictEqual(price.format(property, 'From %s').money, '€388,000');
assert.strictEqual(property.price_min, 450000);
selected = 'GBP';
assert.strictEqual(price.format(property, 'From %s').money, '£338,000');
selected = 'USD';
assert.strictEqual(price.format(property, 'From %s').money, '$450,000');
assert.deepStrictEqual({ ...price.format(property, '%s من') }, { prefix: '', money: '$450,000', suffix: ' من' });
assert.deepStrictEqual(property, { price_min: 450000, price_max: 600000, price_mode: 'from' });

property.price_mode = 'single';
assert.deepStrictEqual({ ...price.format(property, 'From %s') }, { prefix: '', money: '$450,000', suffix: '' });
property.price_mode = 'range';
assert.strictEqual(price.format(property, 'From %s').money, '$450,000–$600,000');

delete window.PeraCurrency;
assert.strictEqual(price.format(property, 'From %s').money, '$450,000–$600,000');

const mapSource = fs.readFileSync(`${__dirname}/../js/property-map.js`, 'utf8');
assert(mapSource.includes("window.addEventListener('pera:currency-change'"));
assert(mapSource.includes('window.PeraCurrency.render(resultsEl)'));
assert(mapSource.includes("formData.get('min_price')"));
assert(mapSource.includes('priceMax < filters.minPrice'));
assert(mapSource.includes('priceMin > filters.maxPrice'));
assert(!mapSource.includes('history.pushState') && !mapSource.includes('history.replaceState'));

console.log('Property map currency tests passed.');
