/* node tests/property-sticky-summary-browser-test.js; uses local Chromium. */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const assert = require('node:assert/strict');
const theme = path.resolve(__dirname, '..');
const markup = execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'property-sticky-summary-test.php'), '--fixture'], { encoding: 'utf8' });
const css = ['main.css', 'property.css'].map(file => fs.readFileSync(path.join(theme, 'css', file), 'utf8')).join('\n');
const script = fs.readFileSync(path.join(theme, 'js/property-sticky-summary.js'), 'utf8');
// Use the actual floating component's opening tag: no independent URL/tracking fixture.
const whatsappSource = fs.readFileSync(path.join(theme, 'inc/whatsapp.php'), 'utf8');
const floatingTag = whatsappSource.match(/<a href="<\?php echo esc_url\( \$whatsapp_context\['whatsapp_url'\] \); \?>" class="floating-whatsapp"[^\n]+/)[0]
  .replace(/<\?php.*?\?>/g, 'property-context-fixture');
const fixture = `<style>${css}</style><style>
  /* Page geometry fixture; production styles above are unchanged. */
  #site-header { height: 80px; background: white; } .property-hero { margin: 0; min-height: 0; height: 500px; }
  #property-overview { height: 650px; } #middle { height: 1800px; } #contact-form { padding: 30px; }
  .enquiry-cta-form { height: 500px; } #after { height: 1800px; }
  </style><header id="site-header"><a href="#">Home</a><button aria-expanded="false">Menu</button></header>
  <section class="property-hero">Hero</section><section id="property-overview">Quick facts</section>${markup}
  <div id="middle"></div><section id="contact-form"><h2>Request full details</h2><form class="enquiry-cta-form"><input id="name"></form></section><div id="after"></div>
  <div id="cookie-banner"></div><div id="floating-home">${floatingTag}<svg class="icon"></svg></a></div><dialog>Modal</dialog>`;
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  await page.route('https://property.test/**', route => route.fulfill({body:'<html></html>',contentType:'text/html'}));
  await page.goto('https://property.test/');
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const bar = page.locator('[data-property-sticky-preview]');
  async function scroll(y) { await page.evaluate(y => scrollTo(0, y), y); await page.waitForTimeout(130); }
  async function visible(value) { await (value ? bar.waitFor({state:'visible'}) : bar.waitFor({state:'hidden'})); }
  for (const width of [320, 375, 430, 1280]) {
    for (const dir of ['ltr', 'rtl']) {
      await page.setViewportSize({ width, height: 800 });
      await page.setContent(fixture);
      await page.evaluate(dir => document.documentElement.dir = dir, dir);
      await page.evaluate(() => {
        window.originalWhatsApp = document.getElementById('floating-whatsapp');
        window.waClicks = 0;
        originalWhatsApp.addEventListener('click', e => { e.preventDefault(); window.waClicks++; });
      });
      const attributes = await page.locator('#floating-whatsapp').evaluate(e => e.outerHTML);
      await page.evaluate(() => {
        localStorage.clear();
        window.PeraCurrencyConfig = { storageKey:'pera_currency', state:'fresh', supported:{USD:{symbol:'$',rounding:1},EUR:{symbol:'€',rounding:1}}, rates:{EUR:0.9} };
      });
      await page.addScriptTag({content:fs.readFileSync(path.join(theme, '../../plugins/pera-currency/assets/js/currency.js'),'utf8')});
      await page.addScriptTag({content: script});
      await visible(false);
      assert(await bar.evaluate(e => e.inert));
      await scroll(1300); await visible(true);
      const rect = await bar.boundingBox();
      if (width <= 768) {
        assert(Math.abs(rect.y + rect.height - 800) < 1, 'fixed mobile bottom');
        assert(rect.height >= 65 && rect.height <= 90, 'compact height');
        assert.equal(await bar.locator('#floating-whatsapp').count(), 1);
        assert.equal(await page.locator('#floating-whatsapp').count(), 1);
        assert.equal(await page.locator('#floating-whatsapp').evaluate(e => e === window.originalWhatsApp), true);
        await page.locator('#floating-whatsapp').click();
        assert.equal(await page.evaluate(() => window.waClicks), 1, 'original click listener survives');
        assert.equal(await page.locator('#floating-whatsapp').evaluate(e => e.outerHTML), attributes, 'all URL and tracking attributes preserved');
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no document overflow');
        assert(await bar.evaluate(e => [...e.querySelectorAll('a')].every(a => { const r = a.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; })), 'actions fit');
      } else {
        const header = await page.locator('#site-header').boundingBox();
        assert(Math.abs(rect.y - header.y - header.height) < 1, 'below header');
        assert.equal(await bar.locator('#floating-whatsapp').count(), 0);
      }
      await page.evaluate(() => document.body.classList.add('is-nav-open')); await visible(false);
      assert.equal(await page.locator('#floating-home #floating-whatsapp').count(), 1);
      await page.evaluate(() => document.body.classList.remove('is-nav-open')); await visible(true);
      await page.evaluate(() => document.getElementById('cookie-banner').classList.add('cookie-banner--visible')); await visible(false);
      await page.evaluate(() => document.getElementById('cookie-banner').classList.remove('cookie-banner--visible')); await visible(true);
      await page.evaluate(() => document.querySelector('dialog').showModal()); await visible(false);
      await page.evaluate(() => document.querySelector('dialog').close()); await visible(true);
      await page.emulateMedia({colorScheme:'dark'});
      assert.notEqual(await bar.evaluate(e => getComputedStyle(e).backgroundColor), 'rgb(255, 255, 255)');
      await page.evaluate(() => window.PeraCurrency.setSelected('EUR'));
      assert.equal(await bar.locator('[data-pera-money]').textContent(), '€269,100', 'existing currency runtime updates summary');
      assert((await bar.locator('.property-sticky-preview__price').textContent()).includes('From'), 'starting price semantics retained');
      await page.evaluate(() => window.PeraCurrency.setSelected('USD'));
      await bar.locator('a[href="#contact-form"]').click();
      await page.waitForTimeout(150);
      assert.equal(await page.evaluate(() => document.activeElement.id), 'name');
      assert(Math.abs((await page.locator('#contact-form').boundingBox()).y - 96) < 2, 'header offset and reduced motion');
      await scroll(await page.locator('.enquiry-cta-form').evaluate(e => e.getBoundingClientRect().top + scrollY - 150)); await visible(false);
      await scroll(await page.locator('.enquiry-cta-form').evaluate(e => e.getBoundingClientRect().bottom + scrollY + 20)); await visible(true);
      await scroll(0); await visible(false);
      assert.equal(await page.locator('#floating-home #floating-whatsapp').count(), 1);
      await page.emulateMedia({colorScheme:'light'});
    }
  }
  for (const width of [320,375,430]) {
    await page.setViewportSize({width,height:800});
    await bar.locator('.property-sticky-preview__price').evaluate(e => e.textContent = 'Contact us for pricing');
    await bar.locator('.property-sticky-preview__facts').evaluate(e => e.textContent = '');
    await bar.locator('a[href="#contact-form"]').evaluate(e => e.textContent = 'Запросить информацию');
    await scroll(1300); await visible(true);
    assert(await bar.evaluate(e => e.scrollWidth <= innerWidth), 'missing price and translated action fit');
    await scroll(0); await visible(false);
  }
  await page.setViewportSize({width:375,height:800}); await scroll(1300); await visible(true);
  await page.setViewportSize({width:1280,height:800}); await page.waitForTimeout(150);
  assert.equal(await page.locator('#floating-home #floating-whatsapp').count(), 1, 'desktop restores floating link');
  await page.evaluate(() => document.getElementById('site-header').style.height = '55px'); await page.waitForTimeout(150);
  assert.equal(Math.round((await bar.boundingBox()).y), 55, 'header resize tracked');
  assert(css.includes('env(safe-area-inset-bottom)'), 'safe-area padding');
  assert(!script.includes("addEventListener('scroll'"), 'no scroll polling');
  assert.deepEqual(errors, []);
  await browser.close();
  console.log('Sticky summary Chromium tests passed (4 widths × LTR/RTL, scroll, focus, WhatsApp, overlays, dark mode, header resize).');
})().catch(e => { console.error(e); process.exit(1); });
