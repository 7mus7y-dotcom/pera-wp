/* Run with Playwright installed: node tests/offcanvas-accessibility-test.js.
 * Uses production control markup with PHP translations replaced by their fallbacks.
 */
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const theme = path.resolve(__dirname, '..');
const header = fs.readFileSync(path.join(theme, 'header.php'), 'utf8');
const fallback = html => html.replace(/<\?php[\s\S]*?\?>/g, php => {
  const label = php.match(/pera_ml_ui\( '([^']+)'/);
  return label ? label[1] : '';
});
const opener = fallback(header.match(/<button type="button"\s+class="header-menu-toggle"[\s\S]*?<\/button>/)[0]);
const closer = fallback(header.match(/<button type="button"\s+class="offcanvas-close"[\s\S]*?<\/button>/)[0]);
const nav = fallback(header.split('\n').find(line => line.startsWith('<nav id="pera-offcanvas-menu"')));
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_PATH || undefined});
  try {
    for (const width of [390, 1440]) {
      const page = await browser.newPage({viewport: {width, height: 900}});
      await page.setContent(`<input id="nav-toggle" type="checkbox" hidden><header id="site-header"><div class="header-icons">${opener}</div></header>${nav}${closer}<a href="#article">Article</a></nav><div class="offcanvas-backdrop"></div>`);
      await page.addStyleTag({path: path.join(theme, 'css/main.css')});
      await page.addScriptTag({content: fs.readFileSync(path.join(theme, 'js/main.js'), 'utf8')});
      await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
      const open = page.getByRole('button', {name: 'Open main menu', exact: true});
      const close = page.getByRole('button', {name: 'Close menu', exact: true});
      assert.equal(await open.getAttribute('aria-controls'), 'pera-offcanvas-menu');
      assert.equal(await page.locator('#pera-offcanvas-menu').count(), 1);
      for (const key of ['Enter', 'Space']) {
        await open.focus();
        await page.keyboard.press(key);
        assert.equal(await open.getAttribute('aria-expanded'), 'true');
        assert.equal(await page.locator('#nav-toggle').isChecked(), true);
        assert.equal(await close.evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('Escape');
        assert.equal(await open.getAttribute('aria-expanded'), 'false');
        assert.equal(await page.locator('#nav-toggle').isChecked(), false);
        assert.equal(await open.evaluate(el => el === document.activeElement), true);
        await page.locator('nav a').evaluate(el => el.focus());
        assert.equal(await open.evaluate(el => el === document.activeElement), true);
      }
      await open.click();
      await close.click();
      assert.equal(await open.getAttribute('aria-expanded'), 'false');
      await open.click();
      await page.locator('.offcanvas-backdrop').dispatchEvent('click');
      assert.equal(await open.getAttribute('aria-expanded'), 'false');
      await page.locator('#nav-toggle').evaluate(el => { el.checked = true; el.dispatchEvent(new Event('change')); });
      assert.equal(await open.getAttribute('aria-expanded'), 'true');
      await page.keyboard.press('Escape');
      await page.close();
    }
    console.log('PASS: native controls, names, unique target, Enter/Space, Escape, focus restoration, inert links, backdrop and checkbox synchronization at 390/1440px');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
