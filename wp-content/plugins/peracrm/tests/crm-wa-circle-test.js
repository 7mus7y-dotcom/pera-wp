'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const pluginRoot = path.resolve(__dirname, '..');
const css = fs.readFileSync(path.join(pluginRoot, 'assets/frontend/crm.css'), 'utf8');
const client = fs.readFileSync(path.join(pluginRoot, 'inc/views/pages/crm-client.php'), 'utf8');
const helpers = fs.readFileSync(path.join(pluginRoot, 'inc/helpers.php'), 'utf8');
const whatsapp = fs.readFileSync(path.join(pluginRoot, 'inc/whatsapp.php'), 'utf8');

const compactStart = css.indexOf('/* Compact operational controls share one sizing contract');
const compactEnd = css.indexOf('/* Icon-button contract', compactStart);
assert.ok(compactStart >= 0 && compactEnd > compactStart, 'shared compact button sizing contract exists');
const compactRules = css.slice(compactStart, compactEnd);
for (const selector of [
  '.crm-page--client-view .crm-client-profile-panel .crm-field--actions .btn',
  '.crm-page--client-view #crm-add-reminder > .btn',
  '.crm-page--client-view form[data-crm-ajax-form="note"] > .btn',
  '.crm-page--client-view .crm-danger-zone__trigger',
  '.crm-page--client-view .crm-danger-zone__block--advisor .peracrm-advisor-reassign-row .button',
  '.crm-page--client-view .crm-whatsapp__composer .btn',
  '.crm-page--client-view .crm-status-actions .btn',
]) {
  assert.ok(compactRules.includes(selector), 'client action uses the same compact sizing contract: ' + selector);
}
for (const declaration of [
  /padding:\s*var\(--crm-button-compact-padding-y\) var\(--crm-button-compact-padding-x\);/,
  /font-size:\s*var\(--crm-button-operational-font-size\);/,
  /line-height:\s*var\(--crm-control-compact-line-height\);/,
]) {
  assert.match(compactRules, declaration, 'client actions share Save status compact sizing');
}

const waButtonRule = css.match(/\.crm-page--client-view \.crm-phone-row a\.crm-phone-wa-shortcut\.btn\s*\{([^}]+)\}/);
assert.ok(waButtonRule, 'WhatsApp shortcut has a scoped rule that overrides shared button padding');
for (const declaration of [
  /flex:\s*0 0 18px;/,
  /width:\s*18px;/,
  /height:\s*18px;/,
  /gap:\s*0;/,
  /padding:\s*0;/,
  /border-radius:\s*50%;/,
]) {
  assert.match(waButtonRule[1], declaration, 'WhatsApp shortcut remains an 18px round icon control');
}
const waIconRule = css.match(/a\.crm-phone-wa-shortcut\.btn \.icon\s*\{([^}]+)\}/);
assert.ok(waIconRule, 'WhatsApp glyph has a dedicated size rule');
assert.match(waIconRule[1], /width:\s*14px;/);
assert.match(waIconRule[1], /height:\s*14px;/);

assert.match(client, /class="[^"]*crm-client-profile-panel/, 'Save profile panel remains present');
assert.match(client, /id="crm-add-reminder"[\s\S]*?data-crm-ajax-form="reminder"/, 'Add reminder form remains present');
assert.match(client, /data-crm-ajax-form="note"/, 'Add note form remains present');
assert.match(client, /class="[^"]*crm-danger-zone__trigger/, 'client danger-zone trigger remains present');
assert.match(helpers, /class="button">\s*' \. esc_html\('Reassign'\)/, 'advisor reassign control remains a WordPress button');
assert.match(whatsapp, /class="btn btn--green" type="submit"[\\s\\S]*?esc_html__\('Send WhatsApp text'/, 'WhatsApp send control remains present');
assert.match(whatsapp, /class="btn btn--ghost" type="button" data-wa-refresh/, 'WhatsApp refresh control remains present');

console.log('PASS CRM client actions use compact sizing; WhatsApp shortcut remains 18x18px round with a 14x14px icon.');
