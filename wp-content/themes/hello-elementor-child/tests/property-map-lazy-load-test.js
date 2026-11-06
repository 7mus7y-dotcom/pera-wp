'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const theme = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(theme, 'js/property-map.js'), 'utf8');
const enqueue = fs.readFileSync(path.join(theme, 'inc/modules/enqueue-assets.php'), 'utf8');
const marker = '  const bootstrapPropertyMap = () => {';
const end = '\n\n  if (document.readyState';
const startAt = source.indexOf(marker);
const endAt = source.indexOf(end, startAt);
assert.ok(startAt >= 0 && endAt > startAt, 'bootstrap function exists');
const loader = source.slice(startAt, endAt);
assert.ok(!enqueue.includes("'pera-google-maps',"), 'Google Maps is no longer eagerly enqueued');
assert.ok(enqueue.includes("'google_maps_api_url' => esc_url_raw( $google_maps_url )"), 'server passes API URL');
assert.ok(enqueue.includes("array( 'pera-property-map-price' )"), 'map JS depends on local price script only');
assert.ok(source.includes("document.addEventListener('DOMContentLoaded', bootstrapPropertyMap)"), 'DOM readiness preserved');

function scenario({ hash = '', observerSupported = true, apiUrl = 'https://maps.googleapis.com/maps/api/js?key=test' } = {}) {
  const scripts = [], allNodes = [];
  const mapEl = {
    contents: [], replaceChildren(...items) { this.contents = items; }
  };
  const makeNode = (name) => {
    const el = { name, children: [], handlers: {}, attrs: {},
      appendChild(n) { this.children.push(n); },
      setAttribute(k,v) { this.attrs[k] = v; },
      addEventListener(k,cb) { this.handlers[k] = cb; },
      remove() { this.removed = true; }
    };
    allNodes.push(el);
    return el;
  };
  let instance, mapsInitialized = 0;
  class IntersectionObserverMock {
    constructor(callback, opts) { this.callback = callback; this.opts = opts; instance = this; }
    observe(el) { this.observed = el; }
    disconnect() { this.disconnected = true; }
    fire(isIntersecting) { this.callback([{ isIntersecting }]); }
  }
  const document = {
    getElementById(id) { return id === 'property-map' ? mapEl : null; },
    createElement: makeNode,
    head: { appendChild(el) { scripts.push(el); } }
  };
  const window = {
    location: { hash },
    peraPropertyMap: { google_maps_api_url: apiUrl },
    handlers: {},
    addEventListener(k, cb) { this.handlers[k] = cb; }
  };
  if (observerSupported) window.IntersectionObserver = IntersectionObserverMock;
  vm.runInNewContext(loader + '\nbootstrapPropertyMap();',
    { document, window, initPropertyMap() { mapsInitialized++; } });
  return {
    scripts, mapEl, window, allNodes,
    observer: () => instance,
    count: () => mapsInitialized
  };
}

// Above the fold: no API transfer before the explorer is near the viewport.
{
  const f = scenario();
  assert.equal(f.scripts.length, 0);
  assert.equal(f.observer().observed, f.mapEl);
  assert.equal(f.observer().opts.rootMargin, '450px 0px');
  f.observer().fire(false);
  assert.equal(f.scripts.length, 0);
  f.observer().fire(true);
  assert.equal(f.scripts.length, 1);
  assert.equal(f.scripts[0].src, 'https://maps.googleapis.com/maps/api/js?key=test');
  assert.equal(f.observer().disconnected, true);
  f.observer().fire(true);
  assert.equal(f.scripts.length, 1, 'multiple intersection events do not load twice');
  f.window.google = { maps: {} };
  f.scripts[0].onload();
  assert.equal(f.count(), 1, 'map initialises after API becomes available');
}
{
  const f = scenario({ hash: '#property-map-explorer' });
  assert.equal(f.scripts.length, 1, 'anchor deep link loads immediately');
}
{
  const f = scenario();
  f.window.location.hash = '#property-map';
  f.window.handlers.hashchange();
  assert.equal(f.scripts.length, 1, 'hash navigation loads the map');
}
{
  const f = scenario({ observerSupported: false });
  assert.equal(f.scripts.length, 1, 'old browsers get immediate load fallback');
}
{
  const f = scenario();
  f.observer().fire(true);
  f.scripts[0].onerror();
  assert.equal(f.mapEl.contents.length, 1, 'error message is displayed');
  const box = f.mapEl.contents[0];
  assert.equal(box.attrs.role, 'alert');
  const retry = box.children.find(n => n.name === 'button');
  assert.ok(retry, 'retry button is present');
  retry.handlers.click();
  assert.equal(f.scripts.length, 2, 'retry starts a new request');
  f.window.google = { maps: {} };
  f.scripts[1].onload();
  assert.equal(f.count(), 1, 'map initialises after retry succeeds');
}
{
  const f = scenario({ apiUrl: '' });
  f.observer().fire(true);
  assert.equal(f.scripts.length, 0, 'missing URL never inserts script');
  assert.equal(f.mapEl.contents.length, 1, 'missing config reports failure');
}
console.log('PASS property-map API lazy loader: viewport, anchors, no duplicates, failure/retry, legacy fallback.');
