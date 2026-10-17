<?php
if (!defined('ABSPATH')) { exit; }
if (!peracrm_campaigns_staff_can('peracrm_manage_subscribers')) { wp_die('Forbidden', '', ['response' => 403]); }
peracrm_subscribers_private_headers();
$can_write = peracrm_campaigns_staff_can('peracrm_manage_subscribers', true);
$id = (int) get_query_var('peracrm_subscriber_id');
$path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$is_import = substr(rtrim($path, '/'), -7) === '/import';
$is_suppression = substr(rtrim($path, '/'), -12) === '/suppression';
$batch_id = isset($_GET['batch']) && is_string($_GET['batch']) ? wp_unslash($_GET['batch']) : '';
$title = $is_import ? 'Subscriber import' : ($is_suppression ? 'Suppression intake' : ($id ? 'Subscriber profile' : 'Subscribers'));
function peracrm_subscribers_form_start($operation, $batch = '') {
    echo '<form method="post" action="' . esc_url(peracrm_with_target_blog(static function () { return admin_url('admin-post.php'); })) . '" class="peracrm-subscriber-form"' . ($operation === 'preview' ? ' enctype="multipart/form-data"' : '') . '>';
    wp_nonce_field('peracrm_subscribers');
    echo '<input type="hidden" name="action" value="peracrm_subscribers"><input type="hidden" name="operation" value="' . esc_attr($operation) . '">';
    if ($batch !== '') { echo '<input type="hidden" name="batch" value="' . esc_attr($batch) . '">'; }
}
peracrm_frontend_render_shell_header();
?>
<main id="primary" class="site-main crm-page crm-page--subscribers">
<?php peracrm_frontend_render_partial('crm-header', ['title' => $title, 'active_view' => 'subscribers']); ?>
<div class="container peracrm-subscribers">
<nav class="peracrm-subscriber-links" aria-label="Subscriber tools"><a href="<?php echo esc_url(peracrm_subscribers_url()); ?>">Subscribers</a><a href="<?php echo esc_url(peracrm_subscribers_url('import/')); ?>">CSV preview and import</a><a href="<?php echo esc_url(peracrm_subscribers_url('suppression/')); ?>">Suppression intake</a></nav>
<?php if (isset($_GET['subscriber_notice'])) : ?><p role="status" class="crm-notice"><?php echo esc_html($_GET['subscriber_notice'] === 'saved' ? 'Request saved. Review the result below.' : 'Request was not accepted. Check permissions, readiness, mapping and evidence.'); ?></p><?php endif; ?>
<?php if (!$can_write) : ?><p class="crm-notice">Read only while impersonating. Return to your own identity to make changes.</p><?php endif; ?>
<?php if ($is_import) :
$gate = peracrm_subscribers_readiness(); ?>
<h2>Safe subscriber intake</h2><p>Real intake is <?php echo empty($gate['enabled']) ? 'disabled' : 'enabled'; ?>. Preview does not create subscribers or change consent. No emails are sent. CSV: 1 MiB, 1000 data rows, 64 columns; first row must contain headings.</p>
<p>Import historical unsubscribe, complaint and hard-bounce sources first. Missing evidence remains unknown. Existing identities and suppressions are preserved; client matches remain unlinked.</p>
<?php if ($can_write && peracrm_with_target_blog(static function () { return user_can(peracrm_get_actor_user_id(), 'manage_options'); })) : ?>
<details><summary>Administrator readiness decisions</summary>
<?php peracrm_subscribers_form_start('readiness'); foreach (['consent_mapping' => 'Historical consent and evidence mapping reference', 'suppression_sources' => 'Suppression-source inventory and intake reference', 'retention' => 'Approved retention decision reference'] as $key => $label) : ?>
<label><?php echo esc_html($label); ?><input name="readiness[<?php echo esc_attr($key); ?>]" required maxlength="191" value="<?php echo esc_attr($gate[$key] ?? ''); ?>"></label>
<?php endforeach; ?><label><input type="checkbox" name="readiness[enabled]" value="1" <?php checked(!empty($gate['enabled'])); ?>> Enable real-data application after recording all decisions</label><button class="btn" type="submit">Save readiness</button></form></details>
<?php endif; ?>
<?php if ($batch_id !== '') :
try { $batch = peracrm_subscribers_batch($batch_id); $entries = peracrm_subscribers_batch_rows($batch_id, (int) ($_GET['preview_page'] ?? 1)); } catch (Throwable $error) { $batch = null; }
if (!$batch) : ?><p>Batch unavailable.</p><?php else : ?>
<h2>Batch preview</h2><p>Status: <?php echo esc_html($batch['status']); ?> · <?php echo (int) $batch['total']; ?> rows · Expires <?php echo esc_html($batch['expires_at']); ?> UTC</p>
<p>Rows are temporary bookkeeping, separate from subscriber changes. Application processes up to 25 rows per request; retry continues unfinished rows. Committed rows are retained after a partial failure. After three storage failures a row fails permanently; failed suppressions block opted-in rows. Cancel or expiry removes remaining payloads.</p>
<pre class="peracrm-batch-summary"><?php echo esc_html($batch['summary_json']); ?></pre>
<div class="peracrm-subscriber-table" tabindex="0" role="region" aria-label="Import preview"><table><thead><tr><th>Row</th><th>Mailbox</th><th>State</th><th>Preview / exclusions</th><th>Apply result</th></tr></thead><tbody>
<?php foreach ($entries as $entry) : $row = json_decode($entry['payload_json'], true); $preview = $row['preview'] ?? []; $flags = [];
foreach (['duplicate','conflict','existing','suppressed','missing_evidence','batch_suppressed'] as $flag) { if (!empty($preview[$flag])) { $flags[] = str_replace('_', ' ', $flag); } }
if (isset($preview['client_match'])) { $flags[] = 'client: ' . $preview['client_match']; }
?>
<tr><td><?php echo (int) $entry['line_number']; ?></td><td><?php echo esc_html($row['email'] ?? 'Invalid row'); ?></td><td><?php echo esc_html($row['state'] ?? ''); ?></td><td><?php echo esc_html(implode('; ', $flags) ?: 'Valid'); ?></td><td><?php echo esc_html($entry['status'] . ' ' . $entry['result']); ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php $preview_page = max(1, (int) ($_GET['preview_page'] ?? 1)); if ($preview_page > 1) : ?><a href="<?php echo esc_url(add_query_arg(['batch' => $batch_id, 'preview_page' => $preview_page - 1], peracrm_subscribers_url('import/'))); ?>">Previous rows</a><?php endif; ?>
<?php if (count($entries) === 50) : ?><a href="<?php echo esc_url(add_query_arg(['batch' => $batch_id, 'preview_page' => $preview_page + 1], peracrm_subscribers_url('import/'))); ?>">Next rows</a><?php endif; ?>
<?php if ($can_write && in_array($batch['status'], ['preview','applying'], true)) : foreach (['apply' => 'Apply next 25 rows', 'cancel' => 'Cancel and remove preview', 'export' => 'Download safe preview CSV'] as $operation => $label) { peracrm_subscribers_form_start($operation, $batch_id); echo '<button class="btn btn--ghost" type="submit">' . esc_html($label) . '</button></form>'; } endif; ?>
<?php endif; endif; ?>
<?php if ($can_write) : ?><h2>New preview</h2>
<?php peracrm_subscribers_form_start('preview'); ?>
<label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
<p>Map fields explicitly using zero-based column indexes. Map evidence columns even when values are empty. State values: unknown, subscribed, unsubscribe, complaint, hard_bounce. Date format: YYYY-MM-DD HH:MM:SS (UTC). Topics use | separators.</p>
<div class="peracrm-subscriber-grid">
<?php foreach (['email','state','source','source_ref','evidence_ref','policy_version','occurred_at','language','topics'] as $field) : ?><label><?php echo esc_html(str_replace('_',' ',$field)); ?><input type="number" name="mapping[<?php echo esc_attr($field); ?>]" min="0" max="63" <?php echo in_array($field, ['language','topics'], true) ? '' : 'required'; ?>></label><?php endforeach; ?>
</div><label><input type="checkbox" name="synthetic" value="1"> Synthetic data only (example.test mailboxes)</label><label><input type="checkbox" name="suppression_complete" value="1"> I confirm all historical suppression sources have been imported before opted-in contacts</label><button class="btn" type="submit">Create preview only</button></form>
<?php endif; ?>
<?php elseif ($is_suppression) : ?>
<h2>Record a historical suppression</h2><p>Suppression applies even without a subscriber. Reasons accumulate and cannot be cleared here. Record the historical occurrence time, not today’s date.</p>
<?php if ($can_write) : peracrm_subscribers_form_start('suppression'); ?>
<label>Email<input type="email" name="email" required maxlength="254"></label><label>Reason<select name="reason"><option value="unsubscribe">Unsubscribe</option><option value="complaint">Complaint</option><option value="hard_bounce">Hard bounce</option><option value="administrative">Administrative</option></select></label>
<label>Source<input name="source" required maxlength="64"></label><label>Source reference<input name="source_ref" maxlength="191"></label><label>Occurred at (UTC)<input name="occurred_at" required placeholder="YYYY-MM-DD HH:MM:SS"></label><button class="btn" type="submit">Record suppression</button></form><?php endif; ?>
<h2>Recent suppressions</h2><p>Most recent 50 matching mailboxes, including mailboxes without a subscriber profile.</p><form method="get"><label>Search suppression mailbox <input name="suppression_search" value="<?php echo esc_attr(isset($_GET['suppression_search']) && is_string($_GET['suppression_search']) ? wp_unslash($_GET['suppression_search']) : ''); ?>"></label><button type="submit" class="btn">Search</button></form>
<div class="peracrm-subscriber-table"><table><thead><tr><th>Mailbox</th><th>Reasons</th><th>Latest evidence source</th><th>Occurred at (UTC)</th></tr></thead><tbody><?php foreach (peracrm_subscribers_suppressions(isset($_GET['suppression_search']) && is_string($_GET['suppression_search']) ? wp_unslash($_GET['suppression_search']) : '') as $suppression) : ?><tr><td><?php echo esc_html($suppression['normalized_email']); ?></td><td><?php $reasons=[]; foreach (['unsubscribe','complaint','hard_bounce','administrative'] as $reason) { if (!empty($suppression[$reason])) { $reasons[]=$reason; } } echo esc_html(implode(', ',$reasons)); ?></td><td><?php echo esc_html(($suppression['source'] ?? '') . ' / ' . ($suppression['source_ref'] ?? '')); ?></td><td><?php echo esc_html($suppression['occurred_at'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($id) : $row = peracrm_subscribers_detail($id); if (!$row) : ?><p>Subscriber unavailable.</p><?php else : ?>
<h2><?php echo esc_html($row['email']); ?></h2><dl class="peracrm-subscriber-grid">
<?php foreach (['subscription_state','source','source_ref','evidence_ref','policy_version','recorded_at','confirmed_at','withdrawn_at','created_at','updated_at'] as $field) : ?><div><dt><?php echo esc_html(str_replace('_',' ',$field)); ?></dt><dd><?php echo esc_html($row[$field] ?? '—'); ?></dd></div><?php endforeach; ?></dl>
<p>Suppressions: <?php $flags = []; foreach (['unsubscribe','complaint','hard_bounce','administrative'] as $reason) { if (!empty($row[$reason])) { $flags[] = $reason; } } echo esc_html(implode(', ', $flags) ?: 'None'); ?></p>
<?php if (!empty($row['client_id'])) : ?><a href="<?php echo esc_url(peracrm_with_target_blog(static function () use ($row) { return home_url('/crm/client/' . (int) $row['client_id'] . '/'); })); ?>">Open linked client</a><?php endif; ?>
<?php if ($can_write) : peracrm_subscribers_form_start('profile'); ?><input type="hidden" name="subscriber_id" value="<?php echo (int) $id; ?>">
<label>Explicit language<input name="language" maxlength="35" value="<?php echo esc_attr($row['preferred_language']); ?>"></label><label>Topics separated by |<input name="topics" value="<?php echo esc_attr(implode('|', json_decode($row['preferences_json'], true) ?: [])); ?>"></label><label>Optional client ID (only one authorized mailbox match)<input type="number" min="1" name="client_id" value="<?php echo esc_attr($row['client_id'] ?? ''); ?>"></label><p>Profile changes cannot grant consent, resubscribe or clear suppression.</p><button class="btn" type="submit">Save profile</button></form>
<?php peracrm_subscribers_form_start('withdraw'); ?><input type="hidden" name="subscriber_id" value="<?php echo (int) $id; ?>"><button class="btn btn--ghost" type="submit" data-subscriber-withdraw>Withdraw marketing permission</button></form><?php endif; endif; ?>
<?php else :
$filters = []; foreach (['search','state','suppression','language','topic'] as $key) { $filters[$key] = isset($_GET[$key]) && is_string($_GET[$key]) ? wp_unslash($_GET[$key]) : ''; }
$page = max(1, (int) ($_GET['subscriber_page'] ?? 1)); $result = peracrm_subscribers_list($filters, $page); ?>
<form method="get" class="peracrm-subscriber-form peracrm-subscriber-grid" action="<?php echo esc_url(peracrm_subscribers_url()); ?>">
<label>Search mailbox<input name="search" value="<?php echo esc_attr($filters['search']); ?>"></label><label>State<select name="state"><?php foreach (['' => 'All','unknown' => 'Unknown','pending' => 'Pending','subscribed' => 'Subscribed','unsubscribed' => 'Unsubscribed'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters['state'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
<label>Suppression<select name="suppression"><?php foreach (['' => 'All','yes' => 'Suppressed','no' => 'Not suppressed'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters['suppression'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
<label>Explicit language<input name="language" value="<?php echo esc_attr($filters['language']); ?>"></label><label>Topic<input name="topic" value="<?php echo esc_attr($filters['topic']); ?>"></label><button class="btn" type="submit">Filter</button></form>
<p><?php echo (int) $result['total']; ?> subscribers</p><div class="peracrm-subscriber-table" role="region" tabindex="0" aria-label="Subscribers"><table><thead><tr><th>Mailbox</th><th>Permission</th><th>Suppression</th><th>Language / topics</th><th>Evidence / source</th><th>Dates (UTC)</th></tr></thead><tbody>
<?php foreach ($result['rows'] as $row) : ?><tr><td><a href="<?php echo esc_url(peracrm_subscribers_url($row['id'] . '/')); ?>"><?php echo esc_html($row['email']); ?></a></td><td><?php echo esc_html($row['subscription_state']); ?></td><td><?php echo peracrm_campaigns_has_suppression($row) ? 'Suppressed' : 'None'; ?></td><td><?php echo esc_html(($row['preferred_language'] ?: 'Unknown') . ' / ' . implode(', ', json_decode($row['preferences_json'], true) ?: [])); ?></td><td><?php echo esc_html($row['source'] . ' / ' . $row['evidence_ref']); ?></td><td><?php echo esc_html('Recorded: ' . ($row['recorded_at'] ?: 'Unknown') . '; withdrawn: ' . ($row['withdrawn_at'] ?: '—')); ?></td></tr><?php endforeach; ?>
<?php if (!$result['rows']) : ?><tr><td colspan="6">No subscribers match these filters.</td></tr><?php endif; ?></tbody></table></div>
<nav aria-label="Subscriber pages"><?php if ($page > 1) : ?><a href="<?php echo esc_url(add_query_arg($filters + ['subscriber_page' => $page - 1], peracrm_subscribers_url())); ?>">Previous</a><?php endif; ?> <span>Page <?php echo (int) $page; ?></span> <?php if ($page * 25 < $result['total']) : ?><a href="<?php echo esc_url(add_query_arg($filters + ['subscriber_page' => $page + 1], peracrm_subscribers_url())); ?>">Next</a><?php endif; ?></nav>
<?php endif; ?>
</div></main>
<?php peracrm_frontend_render_shell_footer(); ?>
