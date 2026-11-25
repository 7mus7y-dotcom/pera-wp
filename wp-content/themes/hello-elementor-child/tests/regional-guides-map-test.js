/* Offline behavior checks for the production controller, including absent assets. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const code = fs.readFileSync(`${__dirname}/../js/regional-guides-map.js`, 'utf8');
function node(tag = 'div') {
  return {tag, children: [], hidden: true, dataset: {}, replaceChildren(...items) { this.children = items; }, attributes: {}, textContent: '', append(...items) { this.children.push(...items); }, addEventListener(event, fn) { this[event] = fn; }, getAttribute(key) { return this.attributes[key]; }, setAttribute(key, value) { this.attributes[key] = value; }};
}
const preview = node(), choices = node(), card = node(), status = node('p'), canvas = node();
const guides = [{id: 1, lat: 41, lng: 29, title: '<script>bad()</script>', url: 'https://example.test/ar/guide/'}, {id: 2, lat: 41, lng: 29, title: 'Second', url: 'https://example.test/ar/second/'}];
const elements = {'[data-map-status]': status, '[data-map-canvas]': canvas, '[data-map-preview]': preview, '[data-map-choices]': choices, '[data-map-card]': card};
const root = {
  dataset: {mapConfig: JSON.stringify({guides, landmarks: [{lat: 41.02, lng: 28.97, title: 'Landmark'}]})},
  querySelector(selector) { return elements[selector]; },
  querySelectorAll() { return guides.map(guide => ({dataset: {mapGuide: guide.id}, content: {cloneNode: () => ({href: guide.url})}})); }
};
const document = {querySelectorAll(selector) { return selector === '[data-regional-map]' ? [root] : []; }, createElement: node, createTextNode: text => ({textContent: text})};
status.hidden = false;
const context = {window: {}, document};
vm.runInNewContext(code, context); assert.equal(status.hidden, false, 'missing L preserves visible fallback');
const markers = [], events = {}, layers = [];
function handler(enabled) { return {enabled, enable() { this.enabled = true; }, disable() { this.enabled = false; }}; }
const map = {fitBounds() {}, on(name, fn) { events[name] = fn; }, eachLayer(fn) { layers.forEach(fn); }, latLngToLayerPoint() { return {distanceTo() { return 0; }}; }, dragging: handler(false), touchZoom: handler(false), doubleClickZoom: handler(false)};
class TileLayer {on(event, fn) { this[event] = fn; } addTo() { layers.push(this); return this; }}
const group = {addTo() { return this; }, clearLayers() { markers.length = 0; }};
const L = {map(_canvas, options) { assert.equal(options.scrollWheelZoom, false); assert.equal(options.touchZoom, true); return map; }, tileLayer() { return new TileLayer(); }, TileLayer, divIcon: opts => opts, layerGroup: () => group, marker(coords, opts) { const element = node(); const marker = {coords, opts, getElement() { return element; }, on(event, fn) { this[event] = fn; return this; }, bindTooltip() { return this; }, bindPopup(content) { this.popup = content; return this; }, addTo() { markers.push(this); return this; }}; return marker; }};
context.L = L; context.window = {L, matchMedia: () => ({matches: true}), location: {href: 'https://example.test/ar/category/regional-guides/'}}; context.URL = URL;
vm.runInNewContext(code, context);
const guideMarkers = markers.filter(m => m.opts.icon.className.endsWith('--guide'));
assert.equal(guideMarkers.length, 1, 'identical guides grouped');
assert.equal(preview.hidden, true, 'preview starts hidden');
guideMarkers[0].click();
assert.equal(preview.hidden, false, 'selecting a marker opens the side preview');
assert.equal(guideMarkers[0].popup, undefined, 'guide markers have no popup');
assert.equal(choices.children.length, 2, 'both clustered guides are selectable');
assert.equal(choices.children[0].textContent, guides[0].title, 'choice title uses textContent');
assert.equal(card.children[0].href, guides[0].url, 'first localized card is shown');
choices.children[1].click();
assert.equal(card.children[0].href, guides[1].url, 'another guide replaces the card');
assert.equal(choices.children[1].getAttribute('aria-pressed'), 'true');
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
assert.equal(typeof events.zoomend, 'function', 'regroup on zoom');
console.log('Regional map controller regressions passed');
