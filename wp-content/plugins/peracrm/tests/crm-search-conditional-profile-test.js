'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const overview = read('inc/views/pages/crm-overview.php');
const profile = read('inc/views/pages/crm-client.php');
const actions = read('inc/frontend-data/crm-client-view.php');

assert.match(overview, /\$is_header_search\s*=\s*\$is_leads\s*&&/);
assert.match(overview, /if \( ! \$is_header_search && ! empty\( \$clients_new_leads_items \) \) :/);
assert.match(overview, /if \( ! \$is_header_search \) : \?>\s*<header class="crm-section__header">/);
assert.match(overview, /\$is_header_search \? array\( 'crm_client_search' => \$header_search_term \)/);

assert.match(profile, /\$show_buyer_requirements\s*=\s*! in_array\( \$client_type_value, array\( 'seller', 'landlord', 'agent' \), true \)/);
assert.match(profile, /<\?php if \( \$show_buyer_requirements \) : \?>[\s\S]*name="peracrm_budget_min_usd"[\s\S]*name="peracrm_budget_max_usd"[\s\S]*name="peracrm_bedrooms"[\s\S]*<\?php endif; \?>/);
assert.match(profile, /name="peracrm_client_id"/);
for (const name of ['peracrm_budget_min_usd', 'peracrm_budget_max_usd', 'peracrm_bedrooms']) {
  assert.match(actions, new RegExp("if \\( isset\\( \\$_POST\\['" + name + "'\\] \\) \\)"));
}
console.log('PASS CRM search-only workspace and conditional buyer requirements regression assertions');
