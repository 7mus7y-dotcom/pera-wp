/* node tests/property-sticky-summary-browser-test.js; uses local Chromium. */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const assert = require('node:assert/strict');
const theme = path.resolve(__dirname, '..');
let markup;
try {
  markup = execFileSync('php', [path.join(__dirname, 'property-sticky-summary-test.php'), '--fixture'], { encoding: 'utf8' });
} catch (error) {
  if (error.code !== 'ENOENT') throw error;
  console.log('PHP unavailable: browser layout harness uses representative summary markup; PHP rendering test must run separately.');
  markup = `<aside class="property-sticky-preview" data-property-sticky-preview hidden inert aria-label="Property summary">
    <div class="container property-sticky-preview__inner"><span class="property-sticky-preview__title">Large Furnished Studio · Bomonti</span>
    <div class="property-sticky-preview__details"><div class="property-sticky-preview__price">From <span data-pera-money data-usd-min="299000" data-price-mode="from" dir="ltr">$299,000</span></div><div class="property-sticky-preview__facts">69 m² · 1+1</div></div>
    <div class="property-sticky-preview__actions"><span class="property-sticky-preview__whatsapp"></span><a class="btn btn--solid btn--blue" href="#contact-form">Enquire</a></div></div></aside>`;
}
const css = fs.readFileSync(path.join(theme, 'css/main.css'), 'utf8');
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
      // The actual currency runtime should update the summary's canonical money node.
      await page.evaluate(() => localStorage?.clear()).catch(() => {});
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
