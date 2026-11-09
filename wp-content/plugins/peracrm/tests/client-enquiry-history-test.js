'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const plugin = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(plugin,file), 'utf8');
const data = read('inc/frontend-data/crm-client-view.php');
const view = read('inc/views/pages/crm-client.php');
const css = read('assets/frontend/crm.css');
const ingester = read('inc/integrations/enquiries.php');

assert.match(data, /peracrm_activity_list\(\s*\$client_id,\s*50,\s*0,\s*'enquiry'\s*\)/, 'dedicated enquiry query');
assert.match(data, /'enquiry_submissions'\s*=>\s*\$enquiry_submissions/, 'query passed to view');
assert.match(data, /function pera_crm_client_view_enquiry_category/, 'category label helper');
for (const text of ['Citizenship','Sell','Rent','Favourites','Consultancy','Property enquiry','Website enquiry']) {
  assert.ok(data.includes("'"+text+"'"), text+' category exists');
}
assert.match(view, /crm-enquiries-submissions/, 'submission list renders');
assert.match(view, /pera_crm_client_view_decode_payload/, 'payload decoded safely');
assert.match(view, /pera_crm_client_view_has_full_timeline_details_access/, 'permission-aware details');
assert.match(view, /pera_crm_client_view_render_enquiry_details/, 'existing safe details renderer used');
assert.match(view, /esc_html\(\s*\$category\s*\)/, 'category escaped');
assert.match(view, /esc_url\(\s*\(string\)\s*\(\s*\$payload\['page_url'\]/, 'source URL escaped');
assert.match(view, /crm-enquiries-linked-properties/, 'existing property cards retained');
assert.match(view, /peracrm-linked-properties-grid/, 'property grid retained');
assert.match(css, /crm-enquiries-submissions__header/, 'history styling present');
assert.match(ingester, /'citizenship_enquiry'/, 'citizenship ingestion source retained');
assert.match(ingester, /'sr_enquiry'/, 'seller-renter ingestion source retained');
console.log('PASS CRM client enquiry history structural regression.');
