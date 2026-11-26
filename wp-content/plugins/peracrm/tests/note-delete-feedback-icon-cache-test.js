'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(base, 'assets/frontend/crm.js'), 'utf8');
const php = fs.readFileSync(path.join(base, 'inc/views/pages/crm-client.php'), 'utf8');
const svg = fs.readFileSync(path.join(base, 'logos-icons/icons.svg'), 'utf8');
const start = js.indexOf("function ensureStatusNode(form) {");
const end = js.indexOf("\n})();", start);
assert.ok(start >= 0 && end > start, 'AJAX client form handler exists');
const handler = js.slice(start, end);
const feedback = /type === 'note-delete'\s*\?\s*document\.querySelector\('\[data-crm-panel="notes"\] form\[data-crm-ajax-form="note"\]'\)\s*\|\|\s*activeForm\s*:\s*activeForm/g;
assert.equal((handler.match(feedback) || []).length, 2, 'success and error messages move to Notes footer');
assert.match(handler, /activeForm = type === 'note-delete' \? form : resolveLiveForm\(activePanel, type, form\)/,
  'delete completion does not reset unrelated note forms');
const spriteRenderer = fs.readFileSync(path.join(base, 'inc/svg-sprite.php'), 'utf8');
assert.match(spriteRenderer, /PERACRM_PATH\s*\.\s*'\/logos-icons\/icons\.svg'/, 'CRM owns the icon sprite');
assert.match(spriteRenderer, /add_action\(\s*'wp_footer'/, 'sprite is inlined for CRM icon use');
assert.match(php, /\$sprite_url\s*=\s*'#'/, 'advisor note icons use the inline fragment');
assert.match(php, /esc_url\(\s*'#icon-whatsapp'\s*\)/, 'WhatsApp shortcut uses local sprite fragment');
assert.match(php, /\$sprite_url \. \$note_type_icons\[\s*\$note_type\s*\]/,
  'advisor note symbol uses local sprite fragment');
assert.match(php, /crm-phone-wa-shortcut/, 'saved phone WhatsApp shortcut remains present');
assert.match(php, /'phone_call'\s*=>\s*'icon-phone-call'/, 'phone mapping preserved');
assert.match(svg, /<symbol id="icon-phone-call"/, 'phone icon is in the sprite');
console.log('PASS Advisor Notes delete feedback placement and phone icon asset versioning.');
