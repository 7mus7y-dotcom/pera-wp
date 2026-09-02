'use strict';

const fs = require('fs');
const path = require('path');
const archive = fs.readFileSync(path.resolve(__dirname, '../archive-property.php'), 'utf8');

function expect(condition, label) {
  if (!condition) throw new Error('FAIL ' + label);
}

expect(archive.includes('let canonicalMinUsd') && archive.includes('let canonicalMaxUsd'), 'canonical USD state is explicit');
expect(archive.includes('convertInputFromUsd') && archive.includes('convertInputToUsd'), 'filter delegates both conversion directions to the plugin');
expect(archive.includes("fd.set('action', 'pera_filter_properties_v2')"), 'existing AJAX endpoint remains in use');
expect(archive.includes("params.set('min_price', minRaw)") && archive.includes("params.set('max_price', maxRaw)"), 'URLs use canonical hidden USD values');
expect(!archive.includes("params.set('currency'") && !archive.includes("fd.set('currency'"), 'currency is absent from URLs and AJAX');
expect(archive.includes("window.addEventListener('pera:currency-change'") && archive.includes('renderPriceUi();'), 'currency switch rerenders without querying');
expect(archive.includes("window.addEventListener('popstate'") && archive.includes("params.get('min_price')"), 'history restores canonical USD state');
expect(archive.includes('canonicalMinUsd = null') && archive.includes('canonicalMaxUsd = null'), 'reset clears canonical price state');
expect(archive.includes('window.PeraCurrency;'), 'plugin integration is guarded');

console.log('Archive currency price filter tests passed');
