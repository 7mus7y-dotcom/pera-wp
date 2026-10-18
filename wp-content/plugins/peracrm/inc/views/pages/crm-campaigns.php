<?php
if (!defined('ABSPATH')) { exit; }
peracrm_campaign_drafts_require();
peracrm_subscribers_private_headers();
$id = (int)get_query_var('peracrm_campaign_id');
$screen = (string)get_query_var('peracrm_campaign_screen');
$is_config = $screen === 'configuration';
if ($is_config) { peracrm_campaign_drafts_require(false,true); }
$can_write = peracrm_campaigns_staff_can('peracrm_manage_campaigns',true);
$can_config = peracrm_with_target_blog(static function () { return user_can(peracrm_get_actor_user_id(),'manage_options'); });
$form = $GLOBALS['peracrm_campaign_form'] ?? null;
$config = null; $storage_error = false; $row = null; $rows = []; $history = [];
$page = max(1,(int)($_GET['campaign_page'] ?? 1));
try {
    $config = peracrm_campaign_configuration($is_config);
    if ($id) { $row = peracrm_campaign_draft_get($id); $history = peracrm_campaign_draft_history($id,max(1,(int)($_GET['history_page'] ?? 1))); }
    elseif ($screen === '') { $rows = peracrm_campaign_draft_list($page); }
} catch (Throwable $error) { $storage_error = true; }
$title = $is_config ? 'Campaign configuration' : ($id || $screen === 'new' ? 'Campaign editor' : 'Campaigns');
function peracrm_campaign_view_value($value) { return is_scalar($value) ? (string)$value : ''; }
function peracrm_campaign_view_field($name,$label,$value,$limit,$textarea=false) {
    echo '<label>'.esc_html($label).($textarea ? '<textarea rows="4"' : '<input type="text"').' name="'.esc_attr($name).'" maxlength="'.(int)$limit.'"'.($textarea ? '>'.esc_textarea(peracrm_campaign_view_value($value)).'</textarea>' : ' value="'.esc_attr(peracrm_campaign_view_value($value)).'">').'</label>';
}
function peracrm_campaign_view_form($operation,$revision,$editor=false) {
    echo '<form method="post" class="peracrm-campaign-form"'.($editor ? ' data-campaign-editor' : '').'>';
    wp_nonce_field('peracrm_campaign_draft');
    echo '<input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="revision" value="'.(int)$revision.'">';
}
function peracrm_campaign_view_block($index,$block) {
    $type = ($block['type'] ?? '') === 'cta' ? 'cta' : 'text';
    echo '<fieldset data-campaign-block><legend>'.($type === 'cta' ? 'CTA block' : 'Text block').'</legend><input type="hidden" name="document[blocks]['.(int)$index.'][type]" value="'.$type.'">';
    foreach ($type === 'cta' ? ['label'=>['Button label',160],'url'=>['Link URL',2048]] : ['heading'=>['Heading',160],'text'=>['Text',4000]] as $key=>$settings) {
        peracrm_campaign_view_field('document[blocks]['.$index.']['.$key.']',$settings[0],$block[$key] ?? '',$settings[1],$key==='text');
    }
    echo '<div class="peracrm-campaign-actions"><button class="btn btn--ghost" type="button" data-block-up aria-label="Move block up">Move up</button><button class="btn btn--ghost" type="button" data-block-down aria-label="Move block down">Move down</button><button class="btn btn--ghost" type="button" data-block-remove>Remove block</button></div></fieldset>';
}
peracrm_frontend_render_shell_header();
?>
<main id="primary" class="site-main crm-page crm-page--campaigns">
<?php peracrm_frontend_render_partial('crm-header',['title'=>$title,'active_view'=>'campaigns']); ?>
<section class="content-panel content-panel--overlap-hero"><div class="content-panel-box border-dm crm-layout"><div class="crm-layout__main peracrm-campaigns">
<nav class="peracrm-campaign-actions" aria-label="Campaign tools"><a href="<?php echo esc_url(peracrm_campaign_drafts_url()); ?>">Campaigns</a><?php if ($can_write) : ?><a href="<?php echo esc_url(peracrm_campaign_drafts_url('new/')); ?>">Create campaign</a><?php endif; ?><?php if ($can_config) : ?><a href="<?php echo esc_url(peracrm_campaign_drafts_url('configuration/')); ?>">Sender and footer configuration</a><?php endif; ?></nav>
<p>Prepare and save campaign drafts.</p>
<?php if (!$can_write) : ?><p class="crm-notice">Read only while impersonating. Return to your own identity to edit.</p><?php endif; ?>
<?php if ($form) : ?><p role="alert" class="crm-notice" data-campaign-error><?php echo esc_html($form['error']); ?></p><a target="_blank" rel="noopener" href="<?php echo esc_url(peracrm_campaign_drafts_url($is_config ? 'configuration/' : ($id ? $id.'/' : 'new/'))); ?>">Open saved version in another tab</a><?php endif; ?>
<?php if ($storage_error || !$config) : ?><p role="alert">Campaign storage is unavailable. No changes were accepted.</p>
<?php elseif ($is_config) :
$doc = $form['document'] ?? $config['document']; $revision = $form['revision'] ?? $config['revision']; ?>
<h2>Administrator approvals</h2><p>Approving these values records an editorial decision only. It does not verify DNS, domain ownership, a provider or deliverability. Stable sender keys identify choices in drafts. Maximum 10 identities; all limits are bytes.</p>
<?php peracrm_campaign_view_form('configuration',$revision,true); ?><fieldset <?php disabled(!$can_write); ?>>
<div data-campaign-senders>
<?php $senders = isset($doc['senders']) && is_array($doc['senders']) ? array_slice(array_values($doc['senders']),0,10) : []; if (!$senders) { $senders=[[]]; }
foreach ($senders as $i=>$sender) : if (!is_array($sender)) { continue; } ?><fieldset data-campaign-sender><legend>Sender identity</legend>
<?php foreach (['id'=>['Stable sender key',64],'name'=>['From name',160],'email'=>['From email',254],'reply_to'=>['Reply-to email',254]] as $key=>$setting) { peracrm_campaign_view_field('document[senders]['.$i.']['.$key.']',$setting[0],$sender[$key] ?? '',$setting[1]); } ?>
<label><input type="checkbox" name="document[senders][<?php echo (int)$i; ?>][approved]" value="1" <?php checked(!empty($sender['approved'])); ?>> Approve this identity and reply-to</label><button type="button" class="btn btn--ghost" data-sender-remove>Remove identity</button></fieldset><?php endforeach; ?></div>
<button type="button" class="btn btn--ghost" data-sender-add>Add identity</button>
<?php peracrm_campaign_view_field('document[footer]','Mandatory footer: legal identity, postal address and required wording',$doc['footer'] ?? '',4000,true); ?>
<label><input type="checkbox" name="document[footer_approved]" value="1" <?php checked(!empty($doc['footer_approved'])); ?>> Approve mandatory footer</label>
<p>Sender and footer approval does not enable email delivery.</p>
<button type="submit" class="btn">Save configuration</button><p role="status" data-campaign-status><?php echo $form ? 'Unsaved — request failed' : (isset($_GET['campaign_saved']) ? 'Saved' : 'Saved configuration revision '.(int)$revision); ?></p></fieldset></form>
<?php elseif ($id || $screen === 'new') : if ($id && !$row) : ?><p>Campaign unavailable.</p><?php else :
$doc = $form['document'] ?? ($row['document'] ?? peracrm_campaign_draft_document([]));
$revision = $form['revision'] ?? (int)($row['revision'] ?? 0);
$missing = []; try { $missing = peracrm_campaign_draft_missing(peracrm_campaign_draft_document($doc),$config['document']); } catch (Throwable $error) { $missing=['Correct the invalid fields before saving']; } ?>
<h2><?php echo $id ? 'Edit draft #'.(int)$id : 'New draft'; ?></h2>
<p class="crm-notice" data-campaign-missing><?php echo esc_html($missing ? 'Missing requirements: '.implode('; ',$missing).'. You can still save an incomplete draft.' : 'Draft fields complete.'); ?></p>
<?php peracrm_campaign_view_form('save',$revision,true); ?><fieldset <?php disabled(!$can_write); ?>>
<div class="peracrm-campaign-grid"><?php foreach (['internal_name'=>['Internal name',160],'subject'=>['Subject',200],'preheader'=>['Preheader',300],'language'=>['Explicit language (for example en)',35]] as $key=>$setting) { peracrm_campaign_view_field('document['.$key.']',$setting[0],$doc[$key] ?? '',$setting[1]); } ?></div>
<label>Approved sender identity<select name="document[sender_id]"><option value="">Choose an approved sender</option>
<?php $selected_sender = peracrm_campaign_view_value($doc['sender_id'] ?? ''); $found=false; foreach ($config['document']['senders'] as $sender) : if (!$sender['approved']) { continue; } $found = $found || $selected_sender === $sender['id']; ?>
<option value="<?php echo esc_attr($sender['id']); ?>" <?php selected($selected_sender,$sender['id']); ?>><?php echo esc_html($sender['name'].' <'.$sender['email'].'> · Reply-to: '.$sender['reply_to']); ?></option><?php endforeach; ?>
<?php if ($selected_sender !== '' && !$found) : ?><option selected value="<?php echo esc_attr($selected_sender); ?>"><?php echo esc_html($selected_sender.' — approval unavailable'); ?></option><?php endif; ?></select></label>
<?php foreach (['introduction'=>['Introduction',4000],'main_text'=>['Main text',16000]] as $key=>$setting) { peracrm_campaign_view_field('document['.$key.']',$setting[0],$doc[$key] ?? '',$setting[1],true); } ?>
<h3>Ordered content blocks</h3><p>Use up to 20 plain text or CTA blocks. Field limits are measured in bytes.</p><noscript><p>JavaScript is required to add, move or remove structured blocks. Existing blocks and text can still be saved.</p></noscript>
<div data-campaign-blocks><?php $blocks = isset($doc['blocks']) && is_array($doc['blocks']) ? array_slice(array_values($doc['blocks']),0,20) : []; foreach ($blocks as $i=>$block) { if (is_array($block)) { peracrm_campaign_view_block($i,$block); } } ?></div>
<div class="peracrm-campaign-actions"><button type="button" class="btn btn--ghost" data-block-add="text">Add text block</button><button type="button" class="btn btn--ghost" data-block-add="cta">Add CTA block</button></div>
<h3>Mandatory approved footer</h3><p class="peracrm-campaign-footer"><?php echo esc_html($config['document']['footer_approved'] ? $config['document']['footer'] : 'No approved footer. Draft saving remains available.'); ?></p><p>Sender and footer use current administrator approvals. Updating configuration can change these values without changing your saved text.</p>
<button type="submit" class="btn">Save draft</button><p role="status" data-campaign-status><?php echo $form ? 'Unsaved — request failed' : (isset($_GET['campaign_saved']) ? 'Saved' : ($id ? 'Saved revision '.(int)$revision : 'Unsaved new draft')); ?></p>
</fieldset></form>
<template data-block-template="text"><?php peracrm_campaign_view_block(0,['type'=>'text']); ?></template><template data-block-template="cta"><?php peracrm_campaign_view_block(0,['type'=>'cta']); ?></template>
<?php if ($id) : ?><details><summary>Immutable revision history</summary><p>Latest 25 revisions on this page. Each save records the real actor and UTC time.</p><ol><?php foreach ($history as $entry) : ?><li><details><summary><?php echo esc_html('Revision '.$entry['revision'].' · Actor '.$entry['actor_user_id'].' · '.$entry['created_at'].' UTC'); ?></summary><pre class="peracrm-campaign-history"><?php echo esc_html(wp_json_encode(json_decode($entry['document_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); ?></pre></details></li><?php endforeach; ?></ol>
<?php $hp=max(1,(int)($_GET['history_page'] ?? 1)); if ($hp>1) : ?><a href="<?php echo esc_url(add_query_arg('history_page',$hp-1,peracrm_campaign_drafts_url($id.'/'))); ?>">Newer revisions</a><?php endif; if (count($history)===25) : ?> <a href="<?php echo esc_url(add_query_arg('history_page',$hp+1,peracrm_campaign_drafts_url($id.'/'))); ?>">Older revisions</a><?php endif; ?></details><?php endif; endif; ?>
<?php else : ?>
<h2>Draft campaigns</h2><div class="peracrm-subscriber-table" role="region" tabindex="0" aria-label="Campaign drafts"><table><thead><tr><th>Internal name</th><th>Subject</th><th>Language</th><th>Status</th><th>Updated (UTC)</th><th>Actions</th></tr></thead><tbody>
<?php foreach (array_slice($rows,0,25) as $item) : ?><tr><td><?php echo esc_html($item['internal_name'] ?: 'Untitled draft'); ?></td><td><?php echo esc_html($item['subject']); ?></td><td><?php echo esc_html($item['language'] ?: 'Missing'); ?></td><td>Draft</td><td><?php echo esc_html($item['updated_at']); ?></td><td><a href="<?php echo esc_url(peracrm_campaign_drafts_url($item['id'].'/')); ?>">Edit</a><?php if ($can_write) : peracrm_campaign_view_form('duplicate',0); ?><input type="hidden" name="source_id" value="<?php echo (int)$item['id']; ?>"><button class="btn btn--ghost" type="submit">Duplicate</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$rows) : ?><tr><td colspan="6">No campaigns yet. Create an incomplete draft to begin.</td></tr><?php endif; ?></tbody></table></div>
<nav aria-label="Campaign pages"><?php if ($page>1) : ?><a href="<?php echo esc_url(add_query_arg('campaign_page',$page-1,peracrm_campaign_drafts_url())); ?>">Previous</a><?php endif; ?> <span>Page <?php echo (int)$page; ?></span> <?php if (count($rows)>25) : ?><a href="<?php echo esc_url(add_query_arg('campaign_page',$page+1,peracrm_campaign_drafts_url())); ?>">Next</a><?php endif; ?></nav>
<?php endif; ?>
</div><?php peracrm_frontend_render_partial('crm-side-nav',['active_view'=>'campaigns']); ?></div></section></main>
<?php peracrm_frontend_render_shell_footer(); ?>
