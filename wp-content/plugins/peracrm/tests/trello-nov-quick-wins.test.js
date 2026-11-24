'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const pluginRoot = path.resolve(__dirname, '..');
const themeRoot = path.resolve(pluginRoot, '../../themes/hello-elementor-child');
const crmCss = fs.readFileSync(path.join(pluginRoot, 'assets/frontend/crm.css'), 'utf8');
const overview = fs.readFileSync(path.join(pluginRoot, 'inc/views/pages/crm-overview.php'), 'utf8');
const client = fs.readFileSync(path.join(pluginRoot, 'inc/views/pages/crm-client.php'), 'utf8');
const mainCss = fs.readFileSync(path.join(themeRoot, 'css/main.css'), 'utf8');

// Trello #239: single-property favourite has visible mouse and keyboard states.
assert.match(mainCss, /body\.single-property \.fav-toggle:not\(\.is-fav\):hover/);
assert.match(mainCss, /\.fav-toggle:focus-visible\s*\{[\s\S]*?outline:\s*2px solid/);

// Trello #244: dark-mode overview border/padding scoped to overview only.
assert.match(crmCss, /@media \(prefers-color-scheme: dark\)[\s\S]*?\.crm-page--overview \.content-panel-box\.border-dm\.crm-layout/);

// Trello #229: reuse normalized WhatsApp link generated from saved phone, no new number parsing.
assert.match(client, /\$whatsapp_link = function_exists\( 'peracrm_whatsapp_url_from_phone' \)/);
assert.match(client, /class="crm-phone-wa-shortcut btn btn--ghost btn--green"/);
assert.match(client, /if \( '' !== \$whatsapp_link \)/);
assert.match(crmCss, /\.crm-page--client-view \.crm-phone-row \.crm-phone-wa-shortcut/);

// Trello #160: date semantics remain distinct, in both task layouts.
assert.match(overview, /\$task_due_is_today = ! \$task_is_overdue/);
assert.match(overview, /wp_date\( 'Y-m-d', \(int\) \$task\['due_ts'\] \) === wp_date\( 'Y-m-d' \)/);
assert.match(overview, /crm-chip--due-today/);
assert.match(crmCss, /\.crm-page \.crm-chip--due-today/);
console.log('PASS Trello #239 #244 #229 #160 UI regression assertions');
