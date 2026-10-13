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
    const propertyCss = fs.readFileSync(path.join(theme, 'css/property.css'), 'utf8');
    const rtlCss = fs.readFileSync(path.join(theme, '../../plugins/pera-multilingual/assets/css/rtl.css'), 'utf8');
    const sprite = fs.readFileSync(path.join(theme, 'logos-icons/icons.svg'), 'utf8');
    const shell = (markup, language) => `<html lang="${language}" dir="${language === 'ar' ? 'rtl' : 'ltr'}"><body class="${language === 'ar' ? 'pera-ml-rtl' : ''}"><main class="site-main pera-single-property content-rail"><h1>Sample property</h1><section class="section section-soft property-location" id="property-location"><div class="grid-2--tight" id="existing-location"><div class="property-location__main"><h2>About this project</h2><p>Existing property location description (simulated).</p></div><aside class="property-location__aside"><div class="card-shell property-map-card"><h3 class="property-map-card__title">Map</h3><p class="property-map-card__address text-soft">Şişli, İstanbul (sample)</p><div class="media-frame media-frame--image-fill"><iframe class="media-embed--map" title="Location map" srcdoc="Sample map placeholder" loading="lazy"></iframe></div><a class="btn btn--solid btn--green" href="https://www.google.com/maps?q=41.06,28.99">Open in Google Maps</a></div></aside></div>${markup}</section><section class="section section-soft"><h2>Property pricing</h2></section></main><div hidden>${sprite}</div></body></html>`;
    for (const width of [390, 834, 1440]) for (const language of ['en', 'tr', 'ar']) {
      const context = await browser.newContext({ viewport: { width, height: 1000 }, hasTouch: true });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
      const markup = fixtures ? fs.readFileSync(path.join(fixtures, `${language}.html`), 'utf8') :
        `<section data-component-tabs><div role="tablist" class="section-cta component-tabs">${[0,1,2,3,4].map(i => `<button role="tab" class="pill pill--lg ${i === 0 ? 'pill--active' : ''}" id="t${i}" aria-controls="p${i}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}">Tab ${i}</button>`).join('')}</div>${[0,1,2,3,4].map(i => `<div role="tabpanel" id="p${i}" aria-labelledby="t${i}" tabindex="0" ${i ? 'hidden' : ''}>Panel ${i}</div>`).join('')}</section>`;
      // Establish the original map/location layout before inserting the real PHP fixture.
      await page.setContent(shell('', language));
      await page.addStyleTag({ content: css + propertyCss + rtlCss });
      const locationBefore = await page.locator('#existing-location').boundingBox();
      const mapBefore = await page.locator('iframe').boundingBox();
      await page.locator('#property-location').evaluate((node, html) => node.insertAdjacentHTML('beforeend', html), markup);
      assert.deepEqual(await page.locator('#existing-location').boundingBox(), locationBefore);
      assert.deepEqual(await page.locator('iframe').boundingBox(), mapBefore);
      await page.addScriptTag({ path: path.join(theme, 'js/component-tabs.js') });
      assert.equal(await page.locator('[data-component-tabs]').evaluate(node => getComputedStyle(node).direction), language === 'ar' ? 'rtl' : 'ltr');
      const tabs = page.getByRole('tab');
      assert.equal(await tabs.count(), 5);
      await tabs.nth(4).tap();
      assert.equal(await tabs.nth(4).getAttribute('aria-selected'), 'true');
      assert.equal(await page.getByRole('tabpanel').count(), 1);
      await tabs.nth(4).focus();
      await page.keyboard.press('Home');
      assert.equal(await tabs.nth(0).getAttribute('aria-selected'), 'true');
      await page.keyboard.press('ArrowRight');
      assert.equal(await tabs.nth(language === 'ar' ? 4 : 1).getAttribute('aria-selected'), 'true');
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
      if (fixtures) {
        await tabs.nth(0).click();
        assert.equal(await page.locator('[data-component-tabs] > h2').count(), 1);
        assert.equal(await page.locator('[data-component-tabs] h4').count(), 0);
        const geometry = await page.getByRole('tabpanel').evaluate(panel => {
          const grid = panel.querySelector('.grid-2');
          const rows = [...panel.querySelectorAll('.poi-list > li')];
          return { columns: getComputedStyle(grid).gridTemplateColumns.split(' ').length, rows: rows.map(row => {
            const name = row.querySelector('a').getBoundingClientRect();
            const distance = row.querySelector('span').getBoundingClientRect();
            const list = row.getBoundingClientRect();
            return { nameLeft: name.left, nameRight: name.right, distanceLeft: distance.left, distanceRight: distance.right, rowLeft: list.left, rowRight: list.right };
          }) };
        });
        assert.equal(geometry.columns, width <= 768 ? 1 : 2);
        for (const row of geometry.rows) {
          if (language === 'ar') { assert.ok(row.distanceRight < row.nameLeft); assert.ok(Math.abs(row.distanceLeft - row.rowLeft) < 1); }
          else { assert.ok(row.nameRight < row.distanceLeft); assert.ok(Math.abs(row.distanceRight - row.rowRight) < 1); }
        }
        assert.ok(await page.getByRole('tabpanel').getByText('(IST)', { exact: false }).count());
        assert.ok(await page.getByRole('tabpanel').getByText('(SAW)', { exact: false }).count());
        for (let i = 0; i < 5; i++) {
          await tabs.nth(i).click();
          assert.equal(await page.getByRole('tabpanel').count(), 1);
          const rowsPerGroup = await page.getByRole('tabpanel').locator('.poi-list').evaluateAll(lists => lists.map(list => list.children.length));
          assert.ok(rowsPerGroup.every(count => count <= 3));
        }
        await tabs.nth(0).click();
        await tabs.nth(0).focus();
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(() => document.activeElement.getAttribute('role')), 'tabpanel');
        // Native Enter/Space should activate the tab without reloading.
        await tabs.nth(4).focus(); await page.keyboard.press('Enter');
        assert.equal(await tabs.nth(4).getAttribute('aria-selected'), 'true');
        await tabs.nth(0).focus(); await page.keyboard.press('Space');
        assert.equal(await tabs.nth(0).getAttribute('aria-selected'), 'true');
        await page.screenshot({ path: path.join(fixtures, `${language}-${width}.png`), fullPage: true });
      }
      assert.deepEqual(errors, []);
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
    console.log('Tabs browser tests passed: desktop/tablet/mobile, compact groups, opposite-edge distances, map geometry preserved, actual RTL stylesheet, keyboard/touch, no console errors, independent instances and existing CSS regression probes.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
