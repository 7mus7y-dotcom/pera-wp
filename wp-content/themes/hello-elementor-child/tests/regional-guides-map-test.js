/* Offline behavior checks for the production controller, including absent assets. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const code = fs.readFileSync(`${__dirname}/../js/regional-guides-map.js`, 'utf8');
function node(tag = 'div') {
  return {tag, children: [], hidden: true, attributes: {}, textContent: '', append(...items) { this.children.push(...items); }, addEventListener(event, fn) { this[event] = fn; }, getAttribute(key) { return this.attributes[key]; }, setAttribute(key, value) { this.attributes[key] = value; }};
}
const button = node('button'), status = node('p'), canvas = node(); button.attributes['aria-pressed'] = 'false';
const guides = [{lat: 41, lng: 29, title: '<script>bad()</script>', url: 'https://example.test/ar/guide/'}, {lat: 41, lng: 29, title: 'Second', url: 'https://example.test/ar/second/'}];
const root = {dataset: {mapConfig: JSON.stringify({guides, landmarks: [{lat: 41.02, lng: 28.97, title: 'Landmark'}], read: 'Read guide'})}, querySelector(selector) { return selector === 'button' ? button : selector === '[data-map-status]' ? status : canvas; }};
const document = {querySelectorAll(selector) { return selector === '[data-regional-map]' ? [root] : []; }, createElement: node, createTextNode: text => ({textContent: text})};
status.hidden = false;
const context = {window: {}, document};
vm.runInNewContext(code, context); assert.equal(status.hidden, false, 'missing L preserves visible fallback');
const markers = [], events = {}, layers = [];
function handler(enabled) { return {enabled, enable() { this.enabled = true; }, disable() { this.enabled = false; }}; }
const map = {fitBounds() {}, on(name, fn) { events[name] = fn; }, eachLayer(fn) { layers.forEach(fn); }, latLngToLayerPoint() { return {distanceTo() { return 0; }}; }, dragging: handler(false), touchZoom: handler(false), doubleClickZoom: handler(false)};
class TileLayer {on(event, fn) { this[event] = fn; } addTo() { layers.push(this); return this; }}
const group = {addTo() { return this; }, clearLayers() { markers.length = 0; }};
const L = {map(_canvas, options) { assert.equal(options.scrollWheelZoom, false); assert.equal(options.touchZoom, true); return map; }, tileLayer() { return new TileLayer(); }, TileLayer, divIcon: opts => opts, layerGroup: () => group, marker(coords, opts) { const marker = {coords, opts, bindTooltip() { return this; }, bindPopup(content) { this.popup = content; return this; }, addTo() { markers.push(this); return this; }}; return marker; }};
context.L = L; context.window = {L, matchMedia: () => ({matches: true}), location: {href: 'https://example.test/ar/category/regional-guides/'}}; context.URL = URL;
vm.runInNewContext(code, context);
const guideMarkers = markers.filter(m => m.opts.icon.className.endsWith('--guide'));
assert.equal(guideMarkers.length, 1, 'identical guides grouped');
assert.equal(guideMarkers[0].popup.children.length, 2, 'popup retains both guides');
assert.equal(guideMarkers[0].popup.children[0].children[0].textContent, guides[0].title, 'title uses textContent');
assert.equal(guideMarkers[0].popup.children[0].children[2].href, guides[0].url, 'translated link retained');
assert.equal(guideMarkers[0].opts.keyboard, true);
assert.equal(status.hidden, true); layers[0].tileerror(); assert.equal(status.hidden, false, 'tile failure shows fallback');
canvas.pointerdown({pointerType: 'touch'});
assert.equal(map.dragging.enabled, false, 'touch never starts one-finger dragging');
canvas.pointerdown({pointerType: 'mouse'});
assert.equal(map.dragging.enabled, true, 'hybrid mouse dragging restored');
canvas.touchstart({touches: [{}, {}]});
assert.equal(map.dragging.enabled, false, 'two-finger gestures use TouchZoom, not Drag');
canvas.mousedown({sourceCapabilities: {firesTouchEvents: true}});
assert.equal(map.dragging.enabled, false, 'synthetic mouse does not enable touch drag');
canvas.mousedown({}); assert.equal(map.dragging.enabled, true, 'legacy mouse dragging works');
assert.equal(button.hidden, true, 'no activation UI');
assert.equal(typeof events.zoomend, 'function', 'regroup on zoom');
console.log('Regional map controller regressions passed');
