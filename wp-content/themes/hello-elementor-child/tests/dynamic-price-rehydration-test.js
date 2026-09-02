'use strict';

const fs = require('fs');
const path = require('path');

const theme = path.resolve(__dirname, '..');
const archive = fs.readFileSync(path.join(theme, 'archive-property.php'), 'utf8');
const favourites = fs.readFileSync(path.join(theme, 'js/favourites.js'), 'utf8');
const favouritesPage = fs.readFileSync(path.join(theme, 'page-favourites.php'), 'utf8');

function expect(condition, label) {
  if (!condition) {
    throw new Error('FAIL ' + label);
  }
}

const guardedRender = "window.PeraCurrency && typeof window.PeraCurrency.render === 'function'";

expect(archive.includes(guardedRender), 'archive AJAX rehydration is safely guarded');
expect(archive.includes('window.PeraCurrency.render(grid);'), 'archive renders inserted card grid');
expect(favourites.includes(guardedRender), 'favourites AJAX rehydration is safely guarded');
expect(favourites.includes('window.PeraCurrency.render(favouritesGrid);'), 'favourites renders inserted card grid');
expect(archive.includes('pera_property_display_price_enqueue_assets();'), 'archive host enqueues currency runtime');
expect(favouritesPage.includes('pera_property_display_price_enqueue_assets();'), 'favourites host enqueues currency runtime');
expect(!archive.includes("body.set('currency'"), 'archive AJAX sends no selected currency');
expect(!favourites.includes("body.set('currency'"), 'favourites AJAX sends no selected currency');
expect(!archive.includes('MutationObserver') && !favourites.includes('MutationObserver'), 'no global mutation observer added');

console.log('Dynamic property price rehydration tests passed');
