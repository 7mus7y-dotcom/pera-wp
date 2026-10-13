/* Run: node tests/component-tabs-test.js (requires Playwright Chromium).
 * Exercises the production tab script against real browser keyboard/touch events. */
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const theme = path.resolve(__dirname, '..');
const fixtures = process.env.NEARBY_FIXTURE_DIR;
const css = fs.readFileSync(path.join(theme, 'css/main.css'), 'utf8').replace(/@font-face\s*\{[^}]*\}/g, '');
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  try {
    for (const language of ['en', 'tr', 'ar']) {
      const context = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      const markup = fixtures ? fs.readFileSync(path.join(fixtures, `${language}.html`), 'utf8') :
        `<section data-component-tabs><div role="tablist" class="section-cta component-tabs">${[0,1,2,3,4].map(i => `<button role="tab" class="pill pill--lg ${i === 0 ? 'pill--active' : ''}" id="t${i}" aria-controls="p${i}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}">Tab ${i}</button>`).join('')}</div>${[0,1,2,3,4].map(i => `<div role="tabpanel" id="p${i}" aria-labelledby="t${i}" tabindex="0" ${i ? 'hidden' : ''}>Panel ${i}</div>`).join('')}</section>`;
      await page.setContent(`<html lang="${language}" dir="${language === 'ar' ? 'rtl' : 'ltr'}"><body>${markup}</body></html>`);
      // Fonts are unrelated to this interaction contract and need no network access.
      await page.addStyleTag({ content: css });
      await page.addScriptTag({ path: path.join(theme, 'js/component-tabs.js') });
      const tabs = page.getByRole('tab');
      assert.equal(await tabs.count(), 5);
      await tabs.nth(4).tap();
      assert.equal(await tabs.nth(4).getAttribute('aria-selected'), 'true');
      assert.equal(await page.getByRole('tabpanel').count(), 1);
      await tabs.nth(4).focus();
      await page.keyboard.press('Home');
      assert.equal(await tabs.nth(0).getAttribute('aria-selected'), 'true');
      await page.keyboard.press('ArrowRight');
      const expected = language === 'ar' ? 4 : 1;
      assert.equal(await tabs.nth(expected).getAttribute('aria-selected'), 'true');
      await page.keyboard.press('End');
      assert.equal(await tabs.nth(4).getAttribute('aria-selected'), 'true');
      await page.keyboard.press('ArrowLeft');
      assert.equal(await tabs.nth(language === 'ar' ? 0 : 3).getAttribute('aria-selected'), 'true');
      assert.equal(await page.locator('[role="tab"][tabindex="0"]').count(), 1);
      assert.equal(await page.locator('[role="tab"][aria-selected="true"]').count(), 1);
      assert.equal(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle), 'solid');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
      const validRelationships = await page.evaluate(() => [...document.querySelectorAll('[role="tab"]')].every(tab => {
        const panel = document.getElementById(tab.getAttribute('aria-controls'));
        return panel && panel.getAttribute('aria-labelledby') === tab.id;
      }));
      assert.equal(validRelationships, true);
      assert.deepEqual(errors, []);
      if (fixtures && language === 'ar') await page.screenshot({ path: path.join(fixtures, 'rtl-mobile.png'), fullPage: true });
      await context.close();
    }
    // Two instances must remain independent.
    const page = await browser.newPage();
    await page.setContent([0,1].map(i => `<section data-component-tabs><button role="tab" id="a${i}" aria-controls="x${i}">A</button><button role="tab" id="b${i}" aria-controls="y${i}">B</button><div id="x${i}" role="tabpanel">X</div><div id="y${i}" role="tabpanel" hidden>Y</div></section>`).join(''));
    await page.addScriptTag({ path: path.join(theme, 'js/component-tabs.js') });
    await page.locator('#b0').click();
    assert.equal(await page.locator('#y0').isVisible(), true);
    assert.equal(await page.locator('#y1').isVisible(), false);
    // New rules must leave existing components unchanged.
    await page.setContent('<article class="card-shell"><h3>Existing card</h3><div class="section-cta"><button class="pill pill--active pill--lg">Existing pill</button></div><div class="grid-2--tight"><p>A</p><p>B</p></div></article>');
    const baseline = await page.addStyleTag({ content: css.split('/* Reusable category tabs:')[0] });
    const computed = () => page.evaluate(() => [...document.querySelectorAll('article,h3,button,div,p')].map(element => {
      const style = getComputedStyle(element);
      return ['display','color','backgroundColor','margin','padding','gap','borderRadius','width','height'].map(key => style[key]);
    }));
    const before = await computed();
    await baseline.evaluate(node => node.remove());
    await page.addStyleTag({ content: css });
    assert.deepEqual(await computed(), before);
    console.log('Tabs browser tests passed: mobile touch, keyboard, English/Turkish/RTL, focus, overflow, ARIA, independent instances.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
