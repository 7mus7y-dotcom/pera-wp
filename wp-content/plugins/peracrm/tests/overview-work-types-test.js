'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const rem = read('inc/repositories/reminders.php');
const schema = read('inc/schema.php');
const main = read('peracrm.php');
const data = read('inc/frontend-data/crm-data.php');
const overview = read('inc/views/pages/crm-overview.php');
const client = read('inc/views/pages/crm-client.php');
const ajax = read('inc/frontend-data/crm-client-view.php');
const admin = read('inc/admin/actions.php');
const css = read('assets/frontend/crm.css');
for (const [id,label] of Object.entries({
  general_follow_up:'General follow-up',
  prepare_portfolio:'Prepare portfolio',
  call:'Call',
  email:'Email',
  whatsapp:'WhatsApp'
})) {
  assert.ok(rem.includes("'"+id+"' => __('"+label+"'"), 'category '+id);
}
assert.match(schema, /task_type VARCHAR\(32\) NOT NULL DEFAULT 'general_follow_up'/,'schema persists type with backwards-compatible default');
assert.match(main, /PERACRM_SCHEMA_VERSION',\s*45/,'schema upgrade version');
assert.match(rem, /function peracrm_reminder_normalize_type/,'type validation helper');
assert.match(rem, /'task_type' => peracrm_reminder_normalize_type\(\$reminder\['task_type'\] \?\? ''\)/,'legacy fallback normalization');
assert.match(rem, /peracrm_reminders_insert_table\(\$client_id, \$advisor_user_id, \$due_at, \$note, peracrm_reminder_normalize_type\(\$task_type\)\)/,'validated insert');
assert.match(rem, /\(client_id, advisor_user_id, due_at, status, task_type, note, created_at, updated_at\)/, 'SQL insert persists type');
assert.match(rem, /SELECT id, client_id, advisor_user_id, due_at, status, task_type, note, created_at, updated_at/, 'SQL queries include type');
assert.match(client, /name="peracrm_task_type"/,'client reminder form accepts task type');
assert.match(client, /peracrm_reminder_type_options\(\)/,'client options share enum');
assert.match(ajax, /peracrm_reminder_add\([\s\S]*?peracrm_task_type/, 'AJAX create saves type');
assert.match(admin, /peracrm_reminder_add\([\s\S]*?peracrm_task_type/, 'fallback POST saves type');
assert.match(data, /'task_type'\s*=>\s*peracrm_reminder_normalize_type/, 'dashboard task rows contain category');
assert.match(overview, /\$_GET\['task_type'\]/, 'overview reads selected category');
assert.match(overview, /\$todays_tasks = array_values\(\s*array_filter\(\s*\$todays_tasks,\s*\$match_task_type\s*\)\s*\)/,'today filters');
assert.match(overview, /\$overdue_tasks = array_values\(\s*array_filter\(\s*\$overdue_tasks,\s*\$match_task_type\s*\)\s*\)/,'overdue filters');
assert.match(overview, /\$render_work_type_filters\( home_url\( '\/crm\/' \), \$work_type_filter, \$work_type_options, array\(\), __\( 'Filter priority work by task type', 'peracrm' \) \)/, 'Overview passes accessible label to shared navigation');
assert.match(overview, /<nav class="crm-work-type-filters" aria-label="<\?php echo esc_attr\( \$accessible_label \); \?>"/, 'shared navigation renders accessible label');
assert.match(overview, /crm-overview-work-type/, 'task rows show category');
assert.match(overview, /'All', 'peracrm'/,'all categories reset');
assert.match(css, /\.crm-page--overview \.crm-work-type-filters/, 'Overview filter spacing scoped appropriately');
assert.match(css, /\.crm-page \.crm-work-type-filters \.crm-work-type-filter/, 'shared compact chips styled for both routes');
console.log('PASS reminder task categories: schema, persistence, validation, frontend creation, Overview filtering.');
