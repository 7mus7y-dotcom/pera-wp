'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const theme = path.resolve(__dirname, '..');
const main = fs.readFileSync(path.join(theme, 'js/main.js'), 'utf8');
const analytics = fs.readFileSync(path.join(theme, 'inc/modules/analytics.php'), 'utf8');
const ahrefs = fs.readFileSync(path.join(theme, 'inc/modules/analytics/ahrefs.php'), 'utf8');

function extract(source, name) {
  const start = source.indexOf('function ' + name + '(');
  assert.ok(start >= 0, name + ' exists');
  const first = source.indexOf('{', start);
  let depth = 0;
  for (let i = first; i < source.length; i++) {
    if (source[i] === '{') depth++;
    if (source[i] === '}' && --depth === 0) return source.slice(start, i + 1);
  }
  throw new Error('Unclosed function ' + name);
}
function fixture(key = 'test-key') {
  const scripts = [];
  const attrs = {};
  const document = {
    getElementById(id) { return scripts.find(s => s.id === id) || null; },
    createElement(tag) {
      assert.equal(tag, 'script');
      return { attrs: {}, setAttribute(k, v) { this.attrs[k] = v; } };
    },
    head: { appendChild(script) { scripts.push(script); } }
  };
  const window = { peraTrackingConfig: { ahrefsKey: key } };
  const ctx = vm.createContext({
    window, document, ahrefsLoaded: false,
    chkAnalytics: null, chkMarketing: null,
    initGA() {}, initMetaPixel() {}
  });
  vm.runInContext(extract(main, 'initAhrefs') + '\n' + extract(main, 'applyPrefs'), ctx);
  return { window, scripts, ctx };
}
{
  const f = fixture();
  assert.equal(f.scripts.length, 0, 'no pre-consent script');
  f.ctx.applyPrefs({ analytics: false, marketing: false });
  assert.equal(f.scripts.length, 0, 'rejected analytics');
  f.ctx.applyPrefs({ analytics: false, marketing: true });
  assert.equal(f.scripts.length, 0, 'marketing consent alone cannot start Ahrefs');
  f.ctx.applyPrefs({ analytics: true, marketing: false });
  assert.equal(f.scripts.length, 1, 'analytics consent loads Ahrefs');
  assert.equal(f.scripts[0].src, 'https://analytics.ahrefs.com/analytics.js');
  assert.equal(f.scripts[0].attrs['data-key'], 'test-key');
  assert.equal(f.scripts[0].async, true);
  f.ctx.applyPrefs({ analytics: true, marketing: false });
  assert.equal(f.scripts.length, 1, 'repeat preference application is idempotent');
}
{
  const f = fixture('');
  f.ctx.applyPrefs({ analytics: true });
  assert.equal(f.scripts.length, 0, 'missing key suppresses load');
}
{
  const f = fixture();
  f.scripts.push({ id: 'pera-ahrefs-analytics' });
  f.ctx.applyPrefs({ analytics: true });
  assert.equal(f.scripts.length, 1, 'existing script is not duplicated');
}
assert.ok(!analytics.includes('pera_analytics_render_ahrefs()'), 'no unconditional head Ahrefs');
assert.ok(!ahrefs.includes('<script src='), 'no head script tag');
assert.match(ahrefs, /peraTrackingConfig\.ahrefsKey/, 'config supplies Ahrefs key');
assert.match(ahrefs, /wp_script_is\(\s*'pera-main-js',\s*'enqueued'\s*\)/, 'only on enqueued main runtime');
assert.match(ahrefs, /wp_add_inline_script\(\s*'pera-main-js',\s*\$inline,\s*'before'\s*\)/, 'config runs before JS');
assert.match(ahrefs, /add_action\(\s*'wp_enqueue_scripts',\s*'pera_analytics_inject_ahrefs_config'/, 'config enqueue hook exists');
console.log('PASS Ahrefs consent: reject, marketing-only, analytics grant, idempotence, missing key, PHP wiring.');
