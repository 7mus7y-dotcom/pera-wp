'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

function expect(condition, label) {
  if (!condition) {
    process.stderr.write(`FAIL ${label}\n`);
    process.exit(1);
  }
}

const listeners = {};
const attributes = {};
const bodyClasses = new Set();
let modalAvailable = false;

const document = {
  readyState: 'loading',
  activeElement: null,
  body: {
    classList: {
      add: (name) => bodyClasses.add(name),
      remove: (name) => bodyClasses.delete(name)
    }
  },
  querySelector: (selector) => selector === '[data-pera-offer-video-modal]' && modalAvailable ? modal : null,
  addEventListener: (type, callback) => {
    listeners[type] = listeners[type] || [];
    listeners[type].push(callback);
  },
  contains: (element) => element !== null
};

function focusElement(element) {
  document.activeElement = element;
  element.focusCount = (element.focusCount || 0) + 1;
}

const closeButton = { focus: function () { focusElement(this); } };
const player = {
  currentTime: 12,
  src: '',
  pauseCount: 0,
  loadCount: 0,
  pause: function () { this.pauseCount += 1; },
  load: function () { this.loadCount += 1; },
  removeAttribute: function (name) { if (name === 'src') this.src = ''; },
  focus: function () { focusElement(this); }
};
const caption = { textContent: '', hidden: true };
const dialog = {
  focus: function () { focusElement(this); },
  contains: (element) => element === closeButton || element === player || element === dialog,
  querySelectorAll: () => [closeButton, player]
};
const modal = {
  hidden: true,
  getAttribute: (name) => attributes[name] || null,
  setAttribute: (name, value) => { attributes[name] = value; },
  querySelector: (selector) => ({
    '[role="dialog"]': dialog,
    '[data-pera-offer-video-player]': player,
    '[data-pera-offer-video-caption]': caption
  })[selector]
};
const opener = {
  focus: function () { focusElement(this); },
  getAttribute: (name) => ({ 'data-video-url': 'https://example.test/offer.mp4', 'data-video-text': 'Offer tour' })[name] || '',
  closest: (selector) => selector === '[data-pera-offer-video-open]' ? opener : null
};
const closeTarget = {
  closest: (selector) => selector === '[data-pera-offer-video-close]' ? closeTarget : null
};

function dispatch(type, event) {
  (listeners[type] || []).forEach((callback) => callback(event));
}

const source = fs.readFileSync(path.join(__dirname, '../js/latest-offers-video.js'), 'utf8');
vm.runInNewContext(source, { document, Array });

expect((listeners.click || []).length === 0, 'script does not bind against a missing modal immediately');
modalAvailable = true;
dispatch('DOMContentLoaded', {});
expect((listeners.click || []).length === 1, 'script initializes after footer modal becomes available');

dispatch('click', { target: opener, preventDefault: function () {} });
expect(modal.hidden === false && player.src === 'https://example.test/offer.mp4', 'delegated trigger opens the late-rendered modal');
expect(document.activeElement === dialog, 'opening moves focus into the dialog');

player.focus();
let prevented = false;
dispatch('keydown', { key: 'Tab', shiftKey: false, preventDefault: () => { prevented = true; } });
expect(prevented && document.activeElement === closeButton, 'forward Tab cycles from the last control to the first');

closeButton.focus();
prevented = false;
dispatch('keydown', { key: 'Tab', shiftKey: true, preventDefault: () => { prevented = true; } });
expect(prevented && document.activeElement === player, 'reverse Tab cycles from the first control to the last');

dispatch('click', { target: closeTarget, preventDefault: function () {} });
expect(modal.hidden && player.currentTime === 0, 'closing hides the modal and resets playback');
expect(document.activeElement === opener, 'closing restores focus to the opening button');
expect(!bodyClasses.has('pera-offer-video-modal-open'), 'closing restores page scrolling');

console.log('Latest Offers video modal tests passed');
