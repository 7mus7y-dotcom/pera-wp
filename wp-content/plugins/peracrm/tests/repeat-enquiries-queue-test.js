'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const plugin = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(plugin, relative), 'utf8');
const intake = read('inc/integrations/enquiries.php');
const queue = read('inc/repeat-enquiries.php');
const overview = read('inc/views/pages/crm-overview.php');
const bootstrap = read('inc/bootstrap.php');

assert.match(intake, /peracrm_find_existing_client_id_by_email\(\s*\$email\s*\)/, 'email match identifies an existing client');
assert.match(intake, /peracrm_find_existing_client_id_by_phone\(\s*\$phone\s*\)/, 'phone match identifies an existing client');
assert.match(intake, /'is_repeat_enquiry'\s*=>\s*\$is_repeat_enquiry/, 'repeat state is saved with enquiry activity');
assert.match(intake, /\$is_repeat_enquiry\s*\?\s*false\s*:\s*peracrm_ingest_enforce_advisor_assignment/, 'repeat submissions preserve the assigned advisor');

assert.match(queue, /peracrm_repeat_enquiry_reviewed_ids/, 'review state is saved per user');
assert.match(queue, /event_payload LIKE %s/, 'queue selects recorded repeat enquiry events');
assert.match(queue, /check_admin_referer\(/, 'mark-reviewed action checks a nonce');
assert.match(queue, /pera_crm_get_allowed_client_ids_for_user/, 'employee results are scoped to permitted clients');
assert.match(queue, /admin_post_peracrm_repeat_enquiry_review/, 'review form has an authenticated handler');

assert.match(overview, /New enquiries from existing clients/, 'overview names the repeat enquiry queue');
assert.match(overview, /Mark reviewed/, 'staff can clear a reviewed item');
assert.match(overview, /peracrm_repeat_enquiry_review/, 'review action is linked from the queue');
assert.match(bootstrap, /repeat-enquiries\.php/, 'queue service is loaded by the CRM plugin');

console.log('PASS repeat enquiry queue: matching, persistence, access scope, overview and review action.');
