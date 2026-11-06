'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const main = fs.readFileSync(path.join(root, 'js/main.js'), 'utf8');
const analytics = fs.readFileSync(path.join(root, 'inc/modules/analytics.php'), 'utf8');
const config = fs.readFileSync(path.join(root, 'inc/modules/analytics/ga4.php'), 'utf8');
const meta = fs.readFileSync(path.join(root, 'inc/modules/analytics/meta.php'), 'utf8');

function extractFunction(source, name) {
  const start = source.indexOf('function ' + name + '(');
  assert.ok(start >= 0, name + ' exists');
  const bodyStart = source.indexOf('{', start);
  let depth = 0;
  for (let i = bodyStart; i < source.length; i++) {
    if (source[i] === '{') depth++;
    if (source[i] === '}' && --depth === 0) return source.slice(start, i + 1);
  }
  throw new Error('Unclosed ' + name);
}

function fixture() {
  const injected = [];
  const scripts = [];
  const document = {
    createElement(name) {
      assert.equal(name, 'script');
      return { async: false, src: '' };
    },
    getElementsByTagName(name) {
      assert.equal(name, 'script');
      return [{ parentNode: { insertBefore(script) { scripts.push(script); } } }];
    },
    head: { appendChild(script) { scripts.push(script); } },
    getElementById() { return null; }
  };
  const window = { peraTrackingConfig: { metaPixelId: 'test-pixel' } };
  const context = vm.createContext({
    window, document, marketingLoaded: false, analyticsLoaded: false,
    chkAnalytics: null, chkMarketing: null,
    initGA() { injected.push('ga'); }
  });
  vm.runInContext(extractFunction(main, 'initMetaPixel') + '\n' +
    extractFunction(main, 'applyPrefs'), context);
  return { context, window, scripts, injected };
}

// Verify the actual frontend consent functions; never contact Meta.
{
  const f = fixture();
  assert.equal(f.scripts.length, 0, 'no load before consent');
  f.context.applyPrefs({ marketing: false, analytics: false });
  assert.equal(f.scripts.length, 0, 'rejected marketing: no network script');
  assert.equal(f.window.fbq, undefined, 'no tracking object before consent');
  f.context.applyPrefs({ marketing: true, analytics: false });
  assert.equal(f.scripts.length, 1, 'accepted marketing loads exactly one script');
  assert.equal(f.scripts[0].src, 'https://connect.facebook.net/en_US/fbevents.js');
  assert.deepEqual(Array.from(f.window.fbq.queue, args => Array.from(args)),
    [['init', 'test-pixel'], ['track', 'PageView']]);
  f.context.applyPrefs({ marketing: true, analytics: false });
  assert.equal(f.scripts.length, 1, 'repeated consent does not duplicate Pixel');
  assert.equal(f.window.fbq.queue.length, 2, 'PageView is not duplicated');
}
{
  const f = fixture();
  f.context.applyPrefs({ marketing: false, analytics: true });
  assert.equal(f.scripts.length, 0, 'analytics-only consent does not load marketing scripts');
  assert.deepEqual(f.injected, ['ga'], 'analytics consent remains independent');
}
{
  const f = fixture();
  f.window.peraTrackingConfig.metaPixelId = '';
  f.context.applyPrefs({ marketing: true });
  assert.equal(f.scripts.length, 0, 'missing Pixel ID does not make network request');
}

// Assert the PHP wiring: no unconditional head loader / noscript bypass.
assert.ok(!analytics.includes("pera_analytics_meta_pixel_print_head();"),
  'Pixel must not be injected unconditionally from PHP');
assert.ok(!analytics.includes("add_action( 'wp_body_open', 'pera_analytics_meta_pixel_print_noscript'"),
  'noscript bypass must not be hooked');
assert.ok(!analytics.includes("add_action( 'wp_footer', 'pera_analytics_meta_pixel_print_noscript'"),
  'footer noscript bypass must not be hooked');
assert.match(config, /peraTrackingConfig\.metaPixelId/, 'PHP injects Pixel ID for main.js');
assert.match(config, /wp_script_is\(\s*'pera-main-js',\s*'enqueued'\s*\)/,
  'config attached only if main JS is enqueued');
assert.match(meta, /define\(\s*'PERA_META_PIXEL_ID'/,
  'current Pixel identifier preserved');

console.log('PASS Meta Pixel consent: no consent, reject, analytics-only, accept, idempotence, PHP wiring.');
