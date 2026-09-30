'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

function expect(condition, label) {
  if (!condition) {
    process.stderr.write(`FAIL ${label}\n`);
    process.exit(1);
  }
}

const source = fs.readFileSync(path.join(__dirname, '../js/main.js'), 'utf8');
const start = source.indexOf("document.querySelectorAll('.property-gallery__strip-shell')");
const end = source.indexOf('  /* Property gallery: images', start);
expect(start !== -1 && end !== -1, 'gallery initializer is present');

const listeners = {};
const button = () => ({
  disabled: false,
  hidden: true,
  addEventListener(type, callback) { this[type] = callback; }
});
const previousButton = button();
const nextButton = button();
const itemOffsets = [2, 162.4, 322.8, 483.2, 643.6, 804, 964.4, 1124.8];
const stripItems = itemOffsets.map((offset) => ({
  offset,
  getBoundingClientRect() {
    return { left: 11 + strip.clientLeft + this.offset - strip.scrollLeft };
  }
}));
const strip = {
  clientLeft: 1,
  clientWidth: 320,
  scrollWidth: 1307.2,
  scrollLeft: 0,
  querySelectorAll: () => stripItems,
  getBoundingClientRect: () => ({ left: 11 }),
  addEventListener(type, callback) { listeners[type] = callback; },
  scrollTo(options) {
    this.scrollLeft = options.left;
    this.lastScroll = options;
    listeners.scroll();
  }
};
const shell = {
  classList: { toggle() {} },
  querySelector(selector) {
    return ({
      '.property-gallery__strip': strip,
      '.property-gallery__strip-nav--prev': previousButton,
      '.property-gallery__strip-nav--next': nextButton
    })[selector];
  }
};
const resizeObservers = [];
function ResizeObserver(callback) {
  this.callback = callback;
  this.observe = function () {};
  resizeObservers.push(this);
}
const windowListeners = {};
const window = {
  getComputedStyle: () => ({ scrollPaddingLeft: '2px' }),
  requestAnimationFrame(callback) { callback(); return null; },
  addEventListener(type, callback) { windowListeners[type] = callback; },
  ResizeObserver
};
const document = { querySelectorAll: () => [shell] };

vm.runInNewContext(source.slice(start, end), {
  document,
  window,
  ResizeObserver,
  Array,
  Math,
  parseFloat
});

// This recreates the production failure: the raw left edge can be just over the
// tolerance even though CSS has snapped to that item's padded destination.
strip.scrollLeft = 320.4;
const legacyPosition = stripItems[2].getBoundingClientRect().left -
  strip.getBoundingClientRect().left + strip.scrollLeft;
expect(legacyPosition > strip.scrollLeft + 2, 'legacy logic would select the current item again');
nextButton.click();
expect(strip.lastScroll.left === 481.2, 'next advances past a fractionally aligned current item');

while (!nextButton.disabled) nextButton.click();
expect(strip.scrollLeft === strip.scrollWidth - strip.clientWidth, 'next reaches the clamped final destination');
expect(nextButton.disabled && !previousButton.disabled, 'controls identify the actual end');

while (!previousButton.disabled) previousButton.click();
expect(strip.scrollLeft === 0, 'previous returns to the clamped first destination');
expect(previousButton.disabled && !nextButton.disabled, 'controls identify the actual beginning');

strip.scrollLeft = 510;
listeners.scroll();
nextButton.click();
expect(strip.scrollLeft === 641.6, 'next works after native scrolling to an intermediate position');
previousButton.click();
expect(strip.scrollLeft === 481.2, 'previous works after native scrolling');

stripItems[4].offset = 670.25;
strip.scrollWidth = 1333.85;
resizeObservers[0].callback();
strip.scrollLeft = 500;
nextButton.click();
expect(strip.scrollLeft === 668.25, 'destinations are recalculated after item size changes');

strip.clientWidth = 360;
windowListeners.resize();
strip.scrollLeft = strip.scrollWidth - strip.clientWidth;
listeners.scroll();
expect(nextButton.disabled, 'resizing keeps the end state accurate');

console.log('Property gallery navigation tests passed');
