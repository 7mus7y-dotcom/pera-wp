'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(base, 'assets/frontend/crm.js'), 'utf8');
const php = fs.readFileSync(path.join(base, 'inc/views/pages/crm-client.php'), 'utf8');
const svg = fs.readFileSync(path.join(base, '../../themes/hello-elementor-child/logos-icons/icons.svg'), 'utf8');
const start = js.indexOf("function ensureStatusNode(form) {");
const end = js.indexOf("\n})();", start);
assert.ok(start >= 0 && end > start, 'AJAX client form handler exists');
const handler = js.slice(start, end);
const feedback = /type === 'note-delete'\s*\?\s*document\.querySelector\('\[data-crm-panel="notes"\] form\[data-crm-ajax-form="note"\]'\)\s*\|\|\s*activeForm\s*:\s*activeForm/g;
assert.equal((handler.match(feedback) || []).length, 2, 'success and error messages move to Notes footer');
assert.match(handler, /activeForm = type === 'note-delete' \? form : resolveLiveForm\(activePanel, type, form\)/,
  'delete completion does not reset unrelated note forms');
assert.match(php, /is_file\(\s*\$sprite_path\s*\)/, 'sprite path tested');
assert.match(php, /add_query_arg\(\s*'ver',\s*\(string\)\s*filemtime\(\s*\$sprite_path\s*\),\s*\$sprite_url\s*\)/,
  'sprite version is based on deployed file mtime');
assert.match(php, /esc_url\(\s*\$sprite_url \. '#' \. \$note_type_icons\[\s*\$note_type\s*\]\s*\)/,
  'fragment follows cache-busting query string');
assert.match(php, /'phone_call'\s*=>\s*'icon-phone-call'/, 'phone mapping preserved');
assert.match(svg, /<symbol id="icon-phone-call"/, 'phone icon is in the sprite');
console.log('PASS Advisor Notes delete feedback placement and phone icon asset versioning.');
