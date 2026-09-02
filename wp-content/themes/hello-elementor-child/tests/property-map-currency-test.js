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
  selected: () => selected,
  format: formatted,
  formatRange(min, max) { return { min: formatted(min), max: formatted(max), valid: true }; },
  convertInputFromUsd(amount) { return { amount: Math.round(amount * rates[selected]), currency: selected }; },
  convertInputToUsd(amount, currency, boundary) {
    const raw = amount / rates[currency];
    return { amount: boundary === 'min' ? Math.floor(raw) : Math.ceil(raw), currency: 'USD' };
  },
  formatInput(amount) { return `${symbol[selected]}${Math.round(amount * rates[selected]).toLocaleString('en-US')}`; }
};

assert.strictEqual(price.format(property, 'From %s').money, '€388,000');
assert.strictEqual(property.price_min, 450000);
selected = 'GBP';
assert.strictEqual(price.format(property, 'From %s').money, '£338,000');
selected = 'USD';
assert.strictEqual(price.format(property, 'From %s').money, '$450,000');
assert.deepStrictEqual({ ...price.format(property, '%s من') }, { prefix: '', money: '$450,000', suffix: ' من' });
assert.deepStrictEqual(property, { price_min: 450000, price_max: 600000, price_mode: 'from' });

selected = 'USD';
assert.deepStrictEqual({ ...price.filterDisplayFromUsd('400000') }, { value: '400000', formatted: '$400,000', currency: 'USD' });
assert.strictEqual(price.filterCanonicalFromDisplay('400000', 'min'), '400000');
const canonical = '400000';
selected = 'EUR';
assert.strictEqual(price.filterDisplayFromUsd(canonical).value, '344800');
selected = 'GBP';
assert.strictEqual(price.filterDisplayFromUsd(canonical).value, '300000');
selected = 'USD';
assert.strictEqual(price.filterDisplayFromUsd(canonical).value, canonical);
assert.strictEqual(canonical, '400000');
selected = 'EUR';
assert.strictEqual(price.filterCanonicalFromDisplay('350000', 'min'), '406032');
assert.strictEqual(price.filterCanonicalFromDisplay('350000', 'max'), '406033');
selected = 'GBP';
assert.strictEqual(price.filterCanonicalFromDisplay('310001', 'min'), '413334');
assert.strictEqual(price.filterCanonicalFromDisplay('310001', 'max'), '413335');
assert.strictEqual(price.filterDisplayFromUsd('').value, '');
assert.strictEqual(price.filterCanonicalFromDisplay('', 'min'), '');

selected = 'USD';
property.price_mode = 'single';
assert.deepStrictEqual({ ...price.format(property, 'From %s') }, { prefix: '', money: '$450,000', suffix: '' });
property.price_mode = 'range';
assert.strictEqual(price.format(property, 'From %s').money, '$450,000–$600,000');

delete window.PeraCurrency;
assert.strictEqual(price.format(property, 'From %s').money, '$450,000–$600,000');
assert.deepStrictEqual({ ...price.filterDisplayFromUsd('400000') }, { value: '400000', formatted: '$400,000', currency: 'USD' });
assert.strictEqual(price.filterCanonicalFromDisplay('400000', 'min'), '400000');

const mapSource = fs.readFileSync(`${__dirname}/../js/property-map.js`, 'utf8');
const mapTemplate = fs.readFileSync(`${__dirname}/../page-property-map.php`, 'utf8');
assert(mapSource.includes("window.addEventListener('pera:currency-change'"));
assert(mapSource.includes('window.PeraCurrency.render(resultsEl)'));
assert(mapSource.includes("formData.get('min_price')"));
assert(mapSource.includes('priceMax < filters.minPrice'));
assert(mapSource.includes('priceMin > filters.maxPrice'));
assert(mapSource.includes('syncCanonicalPrice(target)'));
assert(mapSource.includes('renderPriceFilters();'));
assert(mapSource.includes('canonicalInput.value = mapPrice.filterCanonicalFromDisplay'));
const currencyHandler = mapSource.split("window.addEventListener('pera:currency-change'")[1].split('});')[0];
assert(!currencyHandler.includes('applyFilters('));
assert(mapSource.includes("filtersForm.addEventListener('reset'"));
assert(mapTemplate.includes('data-map-display-price="min"'));
assert(mapTemplate.includes('data-map-canonical-price="min" name="min_price" type="hidden"'));
assert(mapTemplate.includes('data-map-display-price="max"'));
assert(mapTemplate.includes('data-map-canonical-price="max" name="max_price" type="hidden"'));
assert(!mapSource.includes('history.pushState') && !mapSource.includes('history.replaceState'));

console.log('Property map currency tests passed.');
