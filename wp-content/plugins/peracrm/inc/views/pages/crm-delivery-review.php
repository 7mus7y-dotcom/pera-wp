<?php
if (!defined('ABSPATH')) { exit; }
peracrm_subscribers_require_staff();
$get=static fn($key,$default='')=>isset($_GET[$key]) && is_string($_GET[$key]) ? wp_unslash($_GET[$key]) : $default;
$kind=$get('review_kind','hard_bounce');
$categories=['hard_bounce'=>'Hard bounces','complaint'=>'Complaints','unsubscribe'=>'Unsubscribes','administrative'=>'Administrative blocks','temporary'=>'Temporary / undetermined feedback'];
if (!isset($categories[$kind])) { $kind='hard_bounce'; }
$search=$get('review_search'); $page=max(1,(int)$get('review_page','1'));
$jobId=max(0,(int)$get('review_job','0')); $zohoOnly=$get('review_all_sources')!=='1';
?>
<div class="peracrm-delivery-review">
<h2>Bounced addresses and list cleanup</h2>
<p>Hard-bounce, complaint and unsubscribe flags already block future marketing sends. Review records here before removing a subscriber profile. Suppression and consent history, frozen deliveries and CRM client records are retained.</p>
<?php if ($get('cleanup_notice')==='removed') : ?><p class="crm-notice" role="status">Subscriber profile removed. Suppression and historical records were retained; linked CRM clients were not changed.</p><?php endif; ?>
<nav class="peracrm-subscriber-links" aria-label="Delivery review categories">
<?php foreach ($categories as $key=>$label) : ?><a href="<?php echo esc_url(peracrm_delivery_review_url(['review_kind'=>$key])); ?>" <?php if ($key===$kind) { echo 'aria-current="page"'; } ?>><?php echo esc_html($label); ?></a><?php endforeach; ?>
</nav>
<form method="get" class="peracrm-subscriber-form peracrm-subscriber-grid" action="<?php echo esc_url(peracrm_subscribers_url()); ?>">
<input type="hidden" name="subscriber_view" value="delivery-review"><input type="hidden" name="review_kind" value="<?php echo esc_attr($kind); ?>">
<label>Search email address<input name="review_search" maxlength="254" value="<?php echo esc_attr($search); ?>"></label><button class="btn" type="submit">Search</button>
</form>
<?php $result=peracrm_delivery_review_list($kind,$search,$page); ?>
<h3><?php echo esc_html($categories[$kind]); ?></h3>
<p><?php echo (int)$result['total']; ?> <?php echo $kind==='temporary'?'feedback events (an address can appear more than once)':'suppressed addresses'; ?>. Dates are shown in UTC.</p>
<?php if ($kind==='temporary') : ?><p>These events are temporary failures, delivery delays or undetermined bounces. They do not authorize profile removal here. Older events remain visible after a later successful delivery.</p><?php else : ?><p>“Review for removal” means a current suppressed subscriber profile exists. Removing it does not erase the block or permanently erase every historical occurrence of the email.</p><?php endif; ?>
<div class="peracrm-subscriber-table" tabindex="0" role="region" aria-label="Delivery review records"><table>
<thead><tr><th>Email</th><th>Reason / source</th><th>Event date (UTC)</th><th>Linked CRM client</th><th>Review / cleanup</th></tr></thead><tbody>
<?php foreach ($result['rows'] as $item) : ?>
<tr><td><?php if (!empty($item['subscriber_id'])) : ?><a href="<?php echo esc_url(peracrm_subscribers_url((int)$item['subscriber_id'].'/')); ?>"><?php echo esc_html($item['normalized_email']); ?></a><?php else : echo esc_html($item['normalized_email']); endif; ?></td>
<td><?php echo esc_html($kind==='temporary' ? implode(' / ',array_filter([$item['feedback_type'],$item['bounce_type'],$item['feedback_subtype']])) : $categories[$kind]); ?><br><small><?php echo esc_html($item['source']??'Source unavailable'); ?></small></td>
<td><?php echo esc_html($item['occurred_at']??'Unknown'); ?></td>
<td><?php if (!empty($item['client_id'])) : ?><a href="<?php echo esc_url(peracrm_with_target_blog(static fn()=>home_url('/crm/client/'.(int)$item['client_id'].'/'))); ?>">Open client #<?php echo (int)$item['client_id']; ?></a><?php elseif (!empty($item['linked_client_present'])) : ?>Linked client (restricted)<?php else : ?>No stored client link<?php endif; ?></td>
<td><?php if ($item['cleanup_candidate']) : ?>
<strong>Review for removal</strong>
<?php if ($can_write) : ?>
<details><summary>Remove subscriber profile</summary>
<p>Remove only this subscriber profile. Existing suppression and CRM records remain.</p>
<?php if ($item['linked_client_present']) : ?><p>This subscriber has a stored CRM link. The client and its contact history will remain.</p><?php endif; ?>
<?php peracrm_subscribers_form_start('delivery_cleanup'); ?>
<input type="hidden" name="email" value="<?php echo esc_attr($item['normalized_email']); ?>"><input type="hidden" name="subscriber_id" value="<?php echo (int)$item['subscriber_id']; ?>"><input type="hidden" name="subscriber_version" value="<?php echo (int)$item['subscriber_version']; ?>">
<label class="peracrm-review-confirm"><input type="checkbox" name="confirmed" value="1" required> I reviewed <?php echo esc_html($item['normalized_email']); ?> and confirm removal of this subscriber profile.</label>
<button class="btn btn--ghost" type="submit">Remove reviewed profile</button></form></details>
<?php endif; ?>
<?php elseif ($kind==='temporary') : ?>Review only; no removal action<?php else : ?>Blocked; no current subscriber profile<?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$result['rows']) : ?><tr><td colspan="5">No records match this category and search.</td></tr><?php endif; ?>
</tbody></table></div>
<nav class="peracrm-subscriber-links" aria-label="Delivery review pages">
<?php $links=['review_kind'=>$kind,'review_search'=>$search]; if ($page>1) : ?><a href="<?php echo esc_url(peracrm_delivery_review_url($links+['review_page'=>$page-1])); ?>">Previous</a><?php endif; ?>
<span>Page <?php echo (int)$page; ?></span>
<?php if ($page*25<$result['total']) : ?><a href="<?php echo esc_url(peracrm_delivery_review_url($links+['review_page'=>$page+1])); ?>">Next</a><?php endif; ?>
</nav>
<section class="peracrm-unsent-review" aria-labelledby="unsent-review-title">
<h2 id="unsent-review-title">Review the remaining unsent list</h2>
<p>Select a paused job to inspect held and retry recipients. Accepted and unknown outcomes are excluded. This view does not modify the queue or resume sending.</p>
<p>Format and placeholder hints are advisory. Passing these checks does not prove that a mailbox exists or that the contact is recently engaged. Verify the remaining older contacts before deciding whether to resume.</p>
<?php if (peracrm_campaigns_staff_can('peracrm_view_campaign_reports')) : ?>
<form method="get" class="peracrm-subscriber-form peracrm-subscriber-grid" action="<?php echo esc_url(peracrm_subscribers_url()); ?>">
<input type="hidden" name="subscriber_view" value="delivery-review">
<label>Paused job number<input type="number" name="review_job" min="1" required value="<?php echo $jobId?(int)$jobId:''; ?>"></label>
<label>Search remaining email<input name="review_search" maxlength="254" value="<?php echo esc_attr($search); ?>"></label>
<label class="peracrm-review-confirm"><input type="checkbox" name="review_all_sources" value="1" <?php checked(!$zohoOnly); ?>> Include all sources (default: current Zoho migration profiles only)</label>
<button class="btn" type="submit">Review unsent recipients</button></form>
<?php if ($jobId) :
try { $unsent=peracrm_delivery_review_unsent($jobId,$search,max(1,(int)$get('unsent_page','1')),$zohoOnly); }
catch (Throwable $error) { $unsent=null; }
if (!$unsent) : ?><p role="status">Unsent review unavailable. Use a paused job and verify campaign report access.</p>
<?php else : ?>
<h3>Paused job #<?php echo (int)$jobId; ?> · <?php echo (int)$unsent['total']; ?> matching unsent recipients</h3>
<?php if ($can_write && $unsent['total']<=10000) : peracrm_subscribers_form_start('delivery_export'); ?>
<input type="hidden" name="review_job" value="<?php echo (int)$jobId; ?>"><input type="hidden" name="review_search" value="<?php echo esc_attr($search); ?>"><input type="hidden" name="review_all_sources" value="<?php echo $zohoOnly?'0':'1'; ?>"><button class="btn btn--ghost" type="submit">Download unsent review CSV</button><p>Exports up to 10,000 matching rows. This is a review file, not proof of mailbox validity.</p></form>
<?php endif; ?>
<p>Source filter: <?php echo $zohoOnly?'current Zoho migration profiles':'all sources'; ?>. Consent dates are evidence dates, not dates of recent engagement.</p>
<div class="peracrm-subscriber-table" tabindex="0" role="region" aria-label="Unsent recipient review"><table><thead><tr><th>Email</th><th>Source / language</th><th>Consent recorded / confirmed (UTC)</th><th>Review hints</th><th>Linked CRM client</th></tr></thead><tbody>
<?php foreach ($unsent['rows'] as $item) : ?><tr>
<td><?php echo esc_html($item['mailbox']); ?></td><td><?php echo esc_html(($item['source']??'Unavailable').' / '.($item['preferred_language']??'Unknown')); ?></td>
<td><?php echo esc_html(($item['recorded_at']??'Unknown').' / '.($item['confirmed_at']??'Unknown')); ?></td>
<td><?php echo esc_html(implode('; ',$item['hints'])?:'No format or current-consent flags; mailbox deliverability unverified'); ?></td>
<td><?php if (!empty($item['client_id'])) : ?><a href="<?php echo esc_url(peracrm_with_target_blog(static fn()=>home_url('/crm/client/'.(int)$item['client_id'].'/'))); ?>">Open client #<?php echo (int)$item['client_id']; ?></a><?php elseif ($item['linked_client_present']) : ?>Linked client (restricted)<?php else : ?>No stored client link<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$unsent['rows']) : ?><tr><td colspan="5">No held or retry recipients match.</td></tr><?php endif; ?>
</tbody></table></div>
<?php $unsentPage=max(1,(int)$get('unsent_page','1')); $args=['review_job'=>$jobId,'review_search'=>$search,'review_all_sources'=>$zohoOnly?'0':'1']; ?>
<nav class="peracrm-subscriber-links" aria-label="Unsent recipient pages">
<?php if ($unsentPage>1) : ?><a href="<?php echo esc_url(peracrm_delivery_review_url($args+['unsent_page'=>$unsentPage-1])); ?>">Previous unsent recipients</a><?php endif; ?><span>Page <?php echo (int)$unsentPage; ?></span>
<?php if ($unsentPage*25<$unsent['total']) : ?><a href="<?php echo esc_url(peracrm_delivery_review_url($args+['unsent_page'=>$unsentPage+1])); ?>">Next unsent recipients</a><?php endif; ?></nav>
<?php endif; endif; ?>
<?php else : ?><p>Campaign report access is required for unsent-list review.</p><?php endif; ?>
</section></div>
