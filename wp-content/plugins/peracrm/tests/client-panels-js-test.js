'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/frontend/crm.js'), 'utf8');
const marker = '// Client workspace preferences:';
assert(source.includes(marker));
const code = source.slice(source.indexOf(marker));

function fixture(options = {}) {
  const listeners = {};
  let focused = null;
  let handler = () => Promise.resolve({ json: () => Promise.resolve({ success: true, data: { hidden: [] } }) });
  const requests = [];
  class Element {
    constructor(attrs = {}) { this.attrs = attrs; this.dataset = {}; this.hidden = !!attrs.hidden; this.disabled = false; this.checked = false; this.children = []; this.tagName = 'DIV'; this.textContent = ''; this.open = false;
      for (const [key, value] of Object.entries(attrs)) {
        if (key.startsWith('data-')) { this.dataset[key.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = value; }
      }
    }
    matches(selector) { return selector.split(',').some(s => { const match = s.trim().match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/); return match && Object.hasOwn(this.attrs, match[1]) && (match[2] === undefined || match[2] === this.attrs[match[1]]); }); }
    querySelectorAll(selector) { return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]); }
    querySelector(selector) { if (selector === 'summary') { return this.summary; } return this.querySelectorAll(selector)[0] || null; }
    closest(selector) { return this.matches(selector) ? this : this.parentElement ? this.parentElement.closest(selector) : null; }
    append(child) { this.children.push(child); child.parentElement = this; return child; }
    setAttribute(key, value) { this.attrs[key] = value; }
    focus() { focused = this; }
    scrollIntoView() { this.scrolled = true; }
    replaceWith(incoming) { const i = this.parentElement.children.indexOf(this); this.parentElement.children[i] = incoming; incoming.parentElement = this.parentElement; }
  }
  const root = new Element();
  function panel(key, hidden = false) {
    const p = new Element({ 'data-crm-optional-panel': key }); p.hidden = hidden;
    p.append(new Element({ 'data-crm-panel-hide': key })).hidden = true;
    p.formValue = 'unsaved form data';
    return p;
  }
  const dealSlot = root.append(new Element({ 'data-crm-panel-slot': 'deals' }));
  const deals = dealSlot.append(panel('deals', !!options.hidden));
  const instructionsSlot = root.append(new Element({ 'data-crm-panel-slot': 'instructions' }));
  const seller = instructionsSlot.append(panel('seller_properties'));
  const enquiries = root.append(panel('enquiries'));
  const manager = root.append(new Element({ 'data-crm-manage-panels': '', 'data-client-id': '125', 'data-nonce': 'client-bound-nonce' }));
  manager.summary = new Element(); manager.summary.tagName = 'SUMMARY';
  function toggle(key, checked = true) { const t = manager.append(new Element({ 'data-crm-panel-toggle': key })); t.checked = checked; return t; }
  const toggles = { deals: toggle('deals', !options.hidden), seller_properties: toggle('seller_properties'), enquiries: toggle('enquiries') };
  for (const key of Object.keys(toggles)) { manager.append(new Element({ 'data-crm-panel-state': key })); }
  const empty = manager.append(new Element({ 'data-crm-panels-empty': '' }));
  const restore = manager.append(new Element({ 'data-crm-panels-restore': '' }));
  const feedback = root.append(new Element({ 'data-crm-panels-feedback': '', 'data-error': 'Save failed', 'data-saved': 'Saved', 'data-hidden-label': 'Hidden', 'data-visible-label': 'Visible' }));
  const anchor = deals.append(new Element({ id: 'deal-target' }));
  const document = {
    querySelector: selector => root.querySelector(selector), querySelectorAll: selector => root.querySelectorAll(selector),
    addEventListener: (type, fn) => { (listeners[type] ||= []).push(fn); },
    getElementById: id => root.querySelectorAll('[id]').find(node => node.attrs.id === id)
  };
  const parsed = new Map();
  const window = { peraCrmData: { ajaxUrl: '/wp-admin/admin-ajax.php' },
    location: { href: 'https://crm.example/crm/client/125/', origin: 'https://crm.example', pathname: '/crm/client/125/', search: '', hash: options.hash || '' },
    FormData: class { constructor() { this.values = {}; } append(k,v) { this.values[k] = v; } },
    fetch: (url, opts) => { requests.push({ url, ...opts }); return handler(url, opts); },
    addEventListener: (type, fn) => { (listeners[type] ||= []).push(fn); },
    DOMParser: class { parseFromString(html) { return parsed.get(html); } }
  };
  vm.runInNewContext(code, { window, document, URL, Promise, Array });
  const emit = (type, target, extra = {}) => { const event = { target, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; }, ...extra }; (listeners[type] || []).forEach(fn => fn(event)); return event; };
  const success = hidden => ({ json: () => Promise.resolve({ success: true, data: { hidden } }) });
  const flush = () => new Promise(resolve => setImmediate(resolve));
  return { Element, panel, root, manager, feedback, deals, seller, enquiries, toggles, empty, restore, requests, anchor, dealSlot, instructionsSlot, parsed, emit, success, flush, focused: () => focused, setHandler: fn => { handler = fn; } };
}

(async () => {
  const f = fixture();
  assert.equal(f.manager.open, false, 'disclosure remains collapsed');
  assert.equal(f.deals.children[0].hidden, false, 'working enhancement enables dismiss buttons');
  assert.equal(f.toggles.deals.disabled, false);
  let resolve;
  f.setHandler(() => new Promise(r => { resolve = r; }));
  const click = f.emit('click', f.deals.children[0]);
  assert(click.prevented && click.stopped, 'dismiss does not trigger panel actions');
  assert(f.deals.hidden, 'dismiss immediately hides entire panel');
  assert.equal(f.deals.formValue, 'unsaved form data', 'form values retained');
  assert.equal(f.focused(), f.manager.summary, 'keyboard focus leaves hidden panel');
  assert.equal(f.toggles.deals.checked, false);
  assert(f.restore.disabled && f.toggles.enquiries.disabled, 'pending controls disabled');
  f.emit('click', f.deals.children[0]);
  assert.equal(f.requests.length, 1, 'double submission prevented');
  assert.deepEqual(f.requests[0].body.values, { action: 'peracrm_client_panels', client_id: '125', nonce: 'client-bound-nonce', operation: 'hide', panel: 'deals' });
  resolve(f.success(['deals'])); await f.flush();
  assert(f.deals.hidden && !f.restore.disabled);
  f.setHandler(() => Promise.reject(new Error('Network failed')));
  f.toggles.deals.checked = true; f.emit('change', f.toggles.deals); await f.flush();
  assert(f.deals.hidden && !f.toggles.deals.checked, 'failed restore rolls back prior hidden state');
  assert.equal(f.feedback.textContent, 'Network failed');
  assert.equal(f.focused(), f.feedback, 'error accessible outside collapsed disclosure');
  f.setHandler(() => Promise.resolve(f.success([])));
  f.toggles.deals.checked = true; f.emit('change', f.toggles.deals); await f.flush();
  assert(!f.deals.hidden && !f.empty.hidden, 'individual restore updates state and empty message');
  f.setHandler(() => Promise.resolve({ json: () => Promise.resolve({ success: false, data: { message: 'Access denied' } }) }));
  f.emit('click', f.enquiries.children[0]); await f.flush();
  assert(!f.enquiries.hidden && f.toggles.enquiries.checked, 'HTTP application failure restores visible panel');
  f.setHandler(() => Promise.resolve({ json: () => Promise.reject(new Error('Invalid JSON')) }));
  f.emit('click', f.enquiries.children[0]); await f.flush();
  assert(!f.enquiries.hidden, 'malformed response restores visible state');
  f.setHandler(() => Promise.resolve(f.success(['deals', 'seller_properties'])));
  f.emit('click', f.deals.children[0]); await f.flush();
  f.setHandler(() => Promise.reject(new Error('Network failed')));
  f.emit('click', f.restore); await f.flush();
  assert(f.deals.hidden && f.seller.hidden, 'failed restore all rolls back every panel');
  f.setHandler(() => Promise.resolve(f.success([])));
  f.emit('click', f.restore); await f.flush();
  assert(!f.deals.hidden && !f.seller.hidden && !f.enquiries.hidden, 'restore all shows optional panels');
  assert.equal(f.requests.at(-1).body.values.operation, 'restore_all');
  // Saved hidden state is retained on initial page enhancement.
  const reload = fixture({ hidden: true });
  assert(reload.deals.hidden && !reload.toggles.deals.checked, 'page-load preferences do not flash or reset');
  reload.setHandler(() => Promise.resolve(reload.success([])));
  reload.emit('hashchange', null); // No hash: no preference mutation.
  assert.equal(reload.requests.length, 0);
  // Restore and scroll an existing link target inside a hidden optional panel.
  const link = new reload.Element(); link.attrs.href = '#deal-target';
  link.closest = selector => selector === 'a[href]' ? link : null;
  link.href = 'https://crm.example/crm/client/125/#deal-target';
  reload.emit('click', link); await reload.flush();
  assert(!reload.deals.hidden && reload.anchor.scrolled && reload.focused() === reload.anchor, 'hidden link target safely restored, scrolled and focused');
  // Reconcile only conditional slots after a real status/profile event.
  const s = fixture();
  const newDeals = new s.Element({ 'data-crm-panel-slot': 'deals' });
  const newInstructions = new s.Element({ 'data-crm-panel-slot': 'instructions' });
  newInstructions.append(s.panel('landlord_properties'));
  s.parsed.set('deals-empty', { querySelector: () => newDeals });
  s.parsed.set('landlord', { querySelector: () => newInstructions });
  s.emit('peracrm:workspace-panels', null, { detail: { deals: 'deals-empty', instructions: 'landlord' } });
  assert(!s.root.querySelector('[data-crm-optional-panel="deals"]'), 'stage change removes ineligible Deals');
  assert(s.root.querySelector('[data-crm-optional-panel="landlord_properties"]'), 'type change renders landlord panel');
  assert.equal(s.enquiries.formValue, 'unsaved form data', 'unrelated forms untouched by eligibility refresh');
  const same = new s.Element({ 'data-crm-panel-slot': 'instructions' }); same.append(s.panel('landlord_properties'));
  const original = newInstructions.children[0]; original.formValue = 'new unsaved instruction';
  s.parsed.set('same-landlord', { querySelector: () => same });
  s.emit('peracrm:workspace-panels', null, { detail: { instructions: 'same-landlord' } });
  assert.equal(s.root.querySelector('[data-crm-optional-panel="landlord_properties"]').formValue, 'new unsaved instruction', 'unchanged eligibility preserves instruction editor values');
  console.log('OK: client panel JavaScript persistence, rollback, focus, navigation and eligibility checks');
})().catch(error => { console.error(error); process.exit(1); });
