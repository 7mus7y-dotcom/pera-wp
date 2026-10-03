'use strict';

const fs = require('fs');
const path = require('path');

const theme = path.resolve(__dirname, '..');
const archive = fs.readFileSync(path.join(theme, 'archive-property.php'), 'utf8');
const css = fs.readFileSync(path.join(theme, 'css/property.css'), 'utf8');

function expect(condition, label) {
  if (!condition) throw new Error('FAIL ' + label);
}

const inputRule = css.match(/\.filter-price__slider input\[type="range"\]\{([\s\S]*?)\n\}/);
expect(inputRule && /pointer-events:\s*none;/.test(inputRule[1]), 'overlaid input rectangles do not intercept pointers');
expect(/::-webkit-slider-thumb\{\s*pointer-events:\s*auto;\s*\}/.test(css), 'WebKit and Blink thumbs accept pointers');
expect(/::-moz-range-thumb\{\s*pointer-events:\s*auto;\s*\}/.test(css), 'Firefox thumbs accept pointers');
expect(/::-webkit-slider-runnable-track\{[\s\S]*?background:\s*transparent;/.test(css), 'WebKit and Blink native tracks are transparent');
expect(/::-moz-range-track\{[\s\S]*?background:\s*transparent;/.test(css), 'Firefox native tracks are transparent');
expect(/\.filter-price__slider::after\{[\s\S]*?z-index:\s*0;/.test(css), 'one neutral base track occupies the bottom layer');
expect(/\.filter-price__slider::before\{[\s\S]*?z-index:\s*1;/.test(css), 'selected-range fill occupies the middle layer');
expect(/#price-min-range,\s*\n#price-max-range\{\s*z-index:\s*2;/.test(css), 'both interactive thumbs occupy the top layer');
expect(archive.includes("style.setProperty('--fill-left'") && archive.includes("style.setProperty('--fill-right'"), 'selected-range fill follows both slider values');

for (const id of ['priceMinRange', 'priceMaxRange']) {
  expect(archive.includes(`${id}.addEventListener('input'`), `${id} retains its input listener`);
  expect(archive.includes(`${id}.addEventListener('change'`), `${id} retains its change listener`);
}
expect(archive.includes("fd.set('action', 'pera_filter_properties_v2')"), 'slider changes retain the property AJAX endpoint');
expect(archive.includes('syncPriceUiDebounced();') && archive.includes('runAjaxFilter(1, false);'), 'debounced input and committed change still trigger filtering');

console.log('Archive price slider interaction tests passed');
