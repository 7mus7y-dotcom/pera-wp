'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const pluginRoot = path.resolve(__dirname, '..');
const css = fs.readFileSync(path.join(pluginRoot, 'assets/frontend/crm.css'), 'utf8');
const client = fs.readFileSync(path.join(pluginRoot, 'inc/views/pages/crm-client.php'), 'utf8');

const buttonRule = css.match(/\.crm-page--client-view \.crm-phone-row a\.crm-phone-wa-shortcut\.btn\s*\{([^}]+)\}/);
assert.ok(buttonRule, 'WhatsApp shortcut has a scoped rule with enough specificity to override the shared .btn contract');
for (const declaration of [
  /flex:\s*0 0 18px;/,
  /width:\s*18px;/,
  /height:\s*18px;/,
  /gap:\s*0;/,
  /padding:\s*0;/,
  /border-radius:\s*50%;/,
]) {
  assert.match(buttonRule[1], declaration, 'shortcut remains an 18px round icon control');
}

const iconRule = css.match(/a\.crm-phone-wa-shortcut\.btn \.icon\s*\{([^}]+)\}/);
assert.ok(iconRule, 'WhatsApp glyph has a dedicated size rule');
assert.match(iconRule[1], /width:\s*14px;/);
assert.match(iconRule[1], /height:\s*14px;/);
assert.match(client, /class="crm-phone-wa-shortcut btn btn--ghost btn--green"/, 'saved phone shortcut markup remains present');

console.log('PASS CRM WhatsApp shortcut is 18x18px round with a 14x14px icon.');
