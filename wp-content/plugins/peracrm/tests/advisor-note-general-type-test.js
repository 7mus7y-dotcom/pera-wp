'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root,p), 'utf8');
const view = read('inc/views/pages/crm-client.php');
const ajax = read('inc/frontend-data/crm-client-view.php');
const admin = read('inc/admin/actions.php');
const notes = read('inc/repositories/notes.php');
assert.match(view, /<option value="general"><\?php esc_html_e\( 'General', 'peracrm' \); \?><\/option>/);
assert.match(view, /'general' => __\( 'General', 'peracrm' \)/);
for (const [file,source] of Object.entries({ajax,admin,notes})) {
  assert.match(source, /'phone_call', 'email_sent', 'wa_sent', 'options_sent', 'general'/,
    file+' accepts General');
}
assert.match(notes, /\$property_ids = \$activity_type === 'options_sent' \?/,
  'only Options sent requires property linkage');
assert.match(notes, /if \(\$activity_type === 'options_sent' && !\$property_ids\)/);
console.log('PASS General note category: UI, labels, AJAX/POST/repository validation and optional properties.');
