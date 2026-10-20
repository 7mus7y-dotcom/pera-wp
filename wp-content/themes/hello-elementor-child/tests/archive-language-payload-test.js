'use strict';
// Execute production client handlers with a small DOM/transport harness.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert/strict');
const theme = path.resolve(__dirname, '..');
const blog = fs.readFileSync(path.join(theme, 'js/blog-search.js'), 'utf8');
const property = fs.readFileSync(path.join(theme, 'archive-property.php'), 'utf8');
const enqueue = fs.readFileSync(path.join(theme, 'inc/modules/enqueue-assets.php'), 'utf8');
assert.match(enqueue, /'language'\s*=>\s*function_exists\( 'pera_ml_current_language' \) \? pera_ml_current_language\(\) : 'en'/);
class Payload extends Map { constructor() { super(); } }
async function testBlog(lang) {
  const events = {}, calls = [];
  const input = { value: '', addEventListener() {} }, sort = { value: 'published' };
  const form = {
    dataset: { archiveType: 'category', archiveId: '42' },
    querySelector: s => s.includes('type="search"') ? input : s.includes('sort-input') ? sort : null,
    getAttribute: () => `https://example.org/${lang}/category/regional-guides/`,
    classList: { toggle() {} }, addEventListener: (e, cb) => { events['form:' + e] = cb; },
    submit() { throw Error('Unexpected fallback submission'); }
  };
  const results = { innerHTML: '', setAttribute() {}, contains: () => true,
    addEventListener: (e, cb) => { events['results:' + e] = cb; } };
  const document = { querySelector: s => s.includes('form') ? form : results, querySelectorAll: () => [],
    addEventListener: (e, cb) => { events[e] = cb; }, getElementById: () => null };
  const window = { fetch() {}, peraBlogSearch: { language: lang, action: 'pera_blog_search', nonce: 'blog-nonce', ajax_url: '/wp-admin/admin-ajax.php' },
    location: { href: `https://example.org/${lang}/category/regional-guides/?paged=2` },
    history: { replaceState(a, b, url) { window.location.href = url; } }, clearTimeout() {} };
  vm.runInNewContext(blog, { window, document, URL, FormData: Payload, AbortController,
    fetch: async (url, options) => { calls.push(options.body); return { ok: true, json: async () => ({ success: true, data: { grid_html: '<article>server cards</article>' } }) }; } });
  const click = href => ({ preventDefault() {}, target: { closest: () => ({ href }) } });
  for (const href of [`/${lang}/category/regional-guides/?paged=2`, `/${lang}/category/regional-guides/page/2/`]) {
    events['results:click'](click(href));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.at(-1).get('paged'), '2');
    assert.equal(calls.at(-1).get('pera_ml_lang'), lang);
    assert.equal(calls.at(-1).get('archive_type'), 'category');
    assert.equal(calls.at(-1).get('archive_id'), '42');
    assert.equal(new URL(window.location.href).searchParams.get('paged'), '2');
  }
  input.value = 'Istanbul';
  events['form:submit']({ preventDefault() {} });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(calls.at(-1).get('s'), 'Istanbul');
  assert.equal(calls.at(-1).get('paged'), '1');
  events.click(click(`/${lang}/category/regional-guides/?sort=oldest`));
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(calls.at(-1).get('sort'), 'oldest');
  for (const body of calls) { assert.equal(body.get('pera_ml_lang'), lang); assert.equal(body.get('nonce'), 'blog-nonce'); }
}
function testProperty(lang) {
  const start = property.indexOf('  function buildPayload(paged) {');
  const end = property.indexOf('\n  // ---------------------------', start);
  let paginationClick;
  const context = { URL, document: { addEventListener: (event, callback) => { paginationClick = callback; }, getElementById: () => null }, FormData: Payload, form: {}, peraMlLanguage: lang, peraMlNonce: 'property-nonce',
    renderPriceUi() {}, syncTaxCsvInputs() {}, getSelectedV2Bed: () => '2', getSelectedPropertyType: () => 'apartment',
    getSortValue: () => 'price_asc', debugParam: '', window: { location: { origin: 'https://example.org', pathname: `/${lang}/property/page/2/` } } };
  vm.createContext(context);
  vm.runInContext(property.slice(start, end), context);
  const handlerStart = property.indexOf("  document.addEventListener('click'", property.indexOf('// SEO pagination links: enhance with AJAX'));
  const handlerEnd = property.indexOf('\n});', handlerStart) + '\n});'.length;
  let lastPayload;
  context.runAjaxFilter = page => { lastPayload = context.buildPayload(page); };
  vm.runInContext(property.slice(handlerStart, handlerEnd), context);
  for (const href of [`/${lang}/property/page/2/`, `/${lang}/property/?paged=2`]) {
    paginationClick({ preventDefault() {}, target: { closest: () => ({ getAttribute: () => href }) } });
    assert.equal(lastPayload.get('paged'), '2');
    assert.equal(lastPayload.get('pera_ml_lang'), lang);
  }
  for (const page of [1, 2, 3]) {
    const body = context.buildPayload(page);
    assert.equal(body.get('pera_ml_lang'), lang);
    assert.equal(body.get('pera_ml_nonce'), 'property-nonce');
    assert.equal(body.get('paged'), String(page));
    assert.equal(body.get('archive_base'), `/${lang}/property/`);
    assert.equal(body.get('sort'), 'price_asc');
    assert.equal(body.get('v2_beds'), '2');
    assert.equal(body.get('property_type'), 'apartment');
  }
}
(async () => {
  for (const lang of ['zh', 'ru', 'de', 'ar']) { await testBlog(lang); testProperty(lang); }
  console.log('Archive language payload tests passed (zh, ru, de, ar; pagination, filtering, sorting)');
})().catch(error => { console.error(error); process.exit(1); });
