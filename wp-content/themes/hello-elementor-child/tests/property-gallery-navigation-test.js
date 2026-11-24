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
  getBoundingClientRect: () => ({ left: 11, width: 320 }),
  addEventListener(type, callback) { listeners[type] = callback; },
  scrollTo(options) {
    this.scrollLeft = options.left;
    this.lastScroll = options;
    listeners.scroll();
  }
};
const shell = {
  classList: {
    values: new Set(),
    toggle(name, active) { if (active) this.values.add(name); else this.values.delete(name); },
    contains(name) { return this.values.has(name); }
  },
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

// Empty strip space moves by one item without intercepting tile clicks.
strip.scrollLeft = 320.4;
listeners.click({ target: strip, clientX: 300 });
expect(strip.scrollLeft === 481.2, 'blank space right advances gallery');
listeners.click({ target: strip, clientX: 20 });
expect(strip.scrollLeft === 320.8, 'blank space left reverses gallery to the previous snapped item');
listeners.click({ target: stripItems[2], clientX: 300 });
expect(strip.scrollLeft === 320.8, 'clicking a gallery tile does not scroll the strip');

console.log('Property gallery navigation tests passed');

// Real destinations remain tile-aware with mixed portrait, landscape and video widths.
[2, 132, 662, 892, 1650, 1780, 2300, 2600].forEach((offset, index) => {
  stripItems[index].offset = offset;
});
strip.scrollWidth = 2900;
strip.scrollLeft = 0;
resizeObservers[0].callback();
nextButton.click();
expect(strip.scrollLeft === 130, 'next targets a narrow portrait tile');
nextButton.click();
expect(strip.scrollLeft === 660, 'next targets a wide landscape tile');
previousButton.click();
expect(strip.scrollLeft === 130, 'previous handles unequal tile widths');

const modalSource = source.slice(end, source.lastIndexOf('});'));
const control = () => ({
  ...button(),
  attributes: {},
  getAttribute(name) { return this.attributes[name]; },
  setAttribute(name, value) { this.attributes[name] = value; },
  removeAttribute(name) { delete this.attributes[name]; },
  focus() { modalDocument.activeElement = this; }
});
const classes = () => {
  const values = new Set();
  return {
    add: (value) => values.add(value),
    remove: (value) => values.delete(value),
    contains: (value) => values.has(value)
  };
};
const trigger = (galleryType, gallerySrc, label, galleryMime, galleryPoster) => ({
  ...control(),
  attributes: { 'aria-label': label },
  dataset: { galleryType, gallerySrc, galleryMime, galleryPoster }
});
const photo = trigger('image', '/main.jpg', 'Main image');
const primary = trigger('video', '/primary.webm', 'Primary video', 'video/webm', '/main.jpg');
const offer = trigger('video', '/offer.mp4', 'Offer caption', 'video/mp4', '/main.jpg');
const finalPhoto = trigger('image', '/end.jpg', 'Last image');
const mediaSource = control();
Object.defineProperty(mediaSource, 'src', {
  get() { return this.getAttribute('src'); },
  set(value) { this.setAttribute('src', value); }
});
const player = {
  ...control(),
  paused: true,
  currentTime: 0,
  loads: 0,
  plays: 0,
  events: {},
  addEventListener(type, callback) { this.events[type] = callback; },
  querySelector: () => mediaSource,
  load() { this.loads++; },
  play() { this.plays++; this.paused = false; this.events.playing(); },
  pause() { this.paused = true; }
};
const image = control();
const caption = control();
const close = control();
const previous = control();
const next = control();
const play = control();
const pause = control();
const hero = control();
const modal = {
  ...control(),
  classList: classes(),
  querySelector(selector) {
    return ({
      '.lightbox__img': image,
      '.lightbox__video': player,
      '.lightbox__video-play': play,
      '.lightbox__video-pause': pause,
      '.lightbox__caption': caption,
      '.lightbox__close': close,
      '[data-gallery-prev]': previous,
      '[data-gallery-next]': next
    })[selector];
  },
  querySelectorAll: () => [close]
};
const modalDocument = {
  getElementById: () => modal,
  querySelectorAll: () => [photo, primary, offer, finalPhoto],
  querySelector(selector) {
    return selector === '[data-open-property-video]' ? hero : primary;
  },
  body: { classList: classes() }
};
vm.runInNewContext(modalSource, { document: modalDocument });
expect(player.plays === 0 && player.loads === 0, 'gallery initialization does not start playback');

hero.click();
expect(mediaSource.src === '/primary.webm' && mediaSource.type === 'video/webm', 'hero opens the existing primary source');
play.click();
player.currentTime = 7;
next.click();
expect(player.paused && player.currentTime === 0, 'changing video slides stops and resets the previous video');
expect(mediaSource.src === '/offer.mp4' && mediaSource.type === 'video/mp4', 'offer slide loads its own MP4');
expect(player.poster === '/main.jpg' && caption.textContent === 'Offer caption', 'offer shows the main poster and row caption');
expect(player.plays === 1 && !play.hidden, 'the next video waits for an explicit play action');
play.click();
player.currentTime = 12;
next.click();
expect(player.paused && player.currentTime === 0 && player.hidden, 'advancing to a photo stops and hides the player');
player.paused = false;
player.events.playing();
expect(player.paused, 'a video cannot keep playing on an image slide');
previous.click();
play.click();
player.currentTime = 9;
close.click();
expect(player.paused && player.currentTime === 0 && modal.getAttribute('aria-hidden') === 'true', 'closing resets the active offer video');
hero.click();
expect(mediaSource.src === '/primary.webm', 'opening the primary video again restores its source');

console.log('Property gallery video playback tests passed');
