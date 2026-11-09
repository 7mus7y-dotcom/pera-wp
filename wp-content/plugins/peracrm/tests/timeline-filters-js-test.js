'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/frontend/crm.js'), 'utf8');
const start = source.indexOf('/* Timeline filters:');
const code = source.slice(start, source.indexOf('\n(function () {\n  function initViewToggle', start));
assert(start > 0);

function fixture() {
  let listener, body, selected = 'all';
  const requests = [], events = [], timers = new Map();
  class Body {
    constructor(name) { this.name = name; this.attrs = {}; this.expanded = false; }
    setAttribute(k, v) { this.attrs[k] = v; }
    removeAttribute(k) { delete this.attrs[k]; }
    querySelector(selector) {
      if (selector.includes('data-collapsed')) return this.expanded ? {} : null;
      return { setAttribute: (k, v) => { this.expanded = v === 'false'; } };
    }
    replaceWith(next) { body = next; }
  }
  body = new Body('original');
  const links = ['all', 'activity', 'notes', 'reminders'].map(key => ({
    dataset: { crmTimelineFilter: key }, href: 'https://crm.test/crm/client/42/?peracrm_timeline=' + key,
    attrs: {}, classList: { toggle() {} },
    setAttribute(k, v) { this.attrs[k] = v; selected = key; }, removeAttribute(k) { delete this.attrs[k]; },
    closest() { return this; }
  }));
  const status = { hidden: true, textContent: '', dataset: { loading: 'Loading', error: 'Try again' } };
  const panel = {
    isConnected: true,
    addEventListener(type, fn) { listener = fn; },
    querySelector(s) { return s === '.crm-section__body' ? body : status; },
    querySelectorAll() { return links; }
  };
  const document = { querySelector: () => panel, dispatchEvent: e => events.push(e) };
  const window = {
    location: { href: 'https://crm.test/crm/client/42/', origin: 'https://crm.test', pathname: '/crm/client/42/' },
    AbortController,
    setTimeout(fn) { const id = timers.size + 1; timers.set(id, fn); return id; },
    clearTimeout(id) { timers.delete(id); },
    fetch(url, options) { return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })); },
    DOMParser: class { parseFromString(html) {
      return { querySelector() {
        if (html === 'login') return null;
        const next = new Body(html);
        return { querySelector(s) { return s === '.crm-section__body' ? next : { dataset: { crmTimelineFilter: html } }; } };
      } };
    } },
    CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } }
  };
  vm.runInNewContext(code, { window, document, URL });
  const click = (index, extra = {}) => {
    const e = { target: links[index], button: 0, preventDefault() { this.prevented = true; }, ...extra };
    listener(e); return e;
  };
  const finish = (i, filter, extra = {}) => requests[i].resolve({ ok: true, redirected: false, text: async () => filter, ...extra });
  return { click, requests, finish, status, events, timers, links, body: () => body, selected: () => selected };
}
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
  const f = fixture();
  assert(!f.click(1, { ctrlKey: true }).prevented, 'modified clicks retain normal link behavior');
  assert.equal(f.requests.length, 0);
  const original = f.body(); original.expanded = true;
  assert(f.click(2).prevented, 'ordinary click prevents document navigation');
  assert.equal(original.attrs['aria-busy'], 'true');
  assert.equal(f.requests[0].options.credentials, 'same-origin');
  assert.equal(f.requests[0].options.cache, 'no-store');
  f.finish(0, 'notes'); await flush();
  assert.equal(f.body().name, 'notes');
  assert(f.body().expanded, 'See more expansion survives filtering');
  assert.equal(f.selected(), 'notes');
  assert.equal(f.links[2].attrs['aria-current'], 'true');
  assert(f.status.hidden);
  assert.equal(f.events[0].detail.panel, 'timeline', 'replacement reinitializes expanders');

  f.click(1); f.click(3);
  assert(f.requests[1].options.signal.aborted, 'new filter aborts previous request');
  f.finish(2, 'reminders'); await flush();
  f.finish(1, 'activity'); await flush();
  assert.equal(f.body().name, 'reminders', 'late response cannot overwrite the latest selection');
  assert.equal(f.selected(), 'reminders');

  for (const result of ['login', 'wrong-filter', 'redirect', 'http-error', 'network']) {
    const before = f.body(); f.click(0); const i = f.requests.length - 1;
    if (result === 'network') f.requests[i].reject(new Error('Offline'));
    else f.finish(i, result, { redirected: result === 'redirect', ok: result !== 'http-error' });
    await flush();
    assert.equal(f.body(), before, result + ' retains current timeline');
    assert.equal(f.selected(), 'reminders', result + ' retains selected filter');
    assert.equal(f.status.textContent, 'Try again');
    assert.equal(f.body().attrs['aria-busy'], undefined);
    assert.equal(f.timers.size, 0, 'request timeout cleared');
  }
  f.click(0); f.finish(f.requests.length - 1, 'all'); await flush();
  assert.equal(f.body().name, 'all', 'failed requests can be retried without navigation');
  console.log('PASS timeline filters: in-place updates, expansion, modified clicks, racing responses, failures and retry.');
})().catch(error => { console.error(error); process.exitCode = 1; });
