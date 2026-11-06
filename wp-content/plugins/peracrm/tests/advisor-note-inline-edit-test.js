'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const plugin = path.resolve(__dirname, '..');
const file = relative => fs.readFileSync(path.join(plugin, relative), 'utf8');
const view = file('inc/views/pages/crm-client.php');
const repo = file('inc/repositories/notes.php');
const ajax = file('inc/frontend-data/crm-client-view.php');
const admin = file('inc/admin/admin.php');
const actions = file('inc/admin/actions.php');
const js = file('assets/frontend/crm.js');
const css = file('assets/frontend/crm.css');

assert.match(view, /data-crm-note-edit/, 'edit button exists');
assert.match(view, /crm-note-row-actions/, 'edit and delete share an action row');
assert.match(view, /data-crm-ajax-form="note-edit"/, 'inline editor uses the existing AJAX system');
assert.match(view, /data-crm-note-cancel/, 'inline edit can be cancelled');
assert.match(view, /esc_textarea\(\s*\(string\)\s*\(\s*\$note\['note_body'\]/, 'textarea is safely escaped');
assert.match(view, /wp_nonce_field\(\s*'peracrm_edit_note',\s*'peracrm_edit_note_nonce'\s*\)/, 'edit nonce rendered');
assert.match(view, /name="peracrm_note_id"/, 'note ID submitted');
assert.match(view, /name="peracrm_note_body"/, 'edited text submitted');
assert.match(view, /data-crm-ajax-form="note-delete"/, 'delete action retained');
assert.match(view, /crm-note-activity-chip/, 'activity label retained');
assert.match(view, /crm-note-properties--saved/, 'linked properties retained');

assert.match(ajax, /'note-edit' === \$type/, 'AJAX edit route');
assert.match(ajax, /check_admin_referer\(\s*'peracrm_edit_note'/, 'AJAX CSRF check');
assert.match(ajax, /peracrm_admin_get_assigned_advisor_id_for_client/, 'assigned advisor authorization');
assert.match(ajax, /peracrm_notes_update_body\(\s*\$client_id,\s*\$note_id,\s*\$body\s*\)/, 'AJAX save operation');
assert.match(ajax, /'notes', \$ok \? 200 : 400/, 'refresh notes panel through existing AJAX');
assert.match(admin, /admin_post_peracrm_edit_note/, 'standard POST registered');
assert.match(actions, /function peracrm_handle_edit_note\(/, 'standard POST fallback defined');
assert.match(actions, /check_admin_referer\('peracrm_edit_note'/, 'standard POST nonce');
assert.match(actions, /peracrm_notes_update_body\(\$client_id, \$note_id, \$body\)/, 'standard POST uses same repository update');

assert.match(repo, /function peracrm_notes_update_body\(/, 'dedicated update function');
assert.match(repo, /'client_id' => \$client_id/, 'update scoped to client ID');
assert.match(repo, /'id' => \$note_id/, 'update scoped to note ID');
assert.match(repo, /peracrm_notes_table_has_visibility_column/, 'internal visibility retained');
assert.match(repo, /'note_body' => \$note_body/, 'update changes body only');
assert.ok(!repo.slice(repo.indexOf('function peracrm_notes_update_body('),
  repo.indexOf('function peracrm_notes_insert(')).includes("'created_at' =>"),
  'original timestamps remain unchanged');
assert.match(js, /peracrm:panel-replaced/, 'panel replacement still available');
assert.match(js, /Inline editing of advisor note text/, 'inline editor interaction');
assert.match(js, /editor\.querySelector\('textarea'\)\.value = summary\.textContent\.trim\(\)/, 'cancel restores note content');
assert.match(css, /\.crm-note-row-actions/, 'inline buttons styled');
assert.match(css, /\.crm-note-inline-editor\[hidden\]/, 'closed editor remains hidden');
console.log('PASS advisor note inline edit wiring and structural regressions.');
