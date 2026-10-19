<?php
if (!defined('ABSPATH')) { exit; }
peracrm_campaign_drafts_require(true);
peracrm_subscribers_private_headers();
$campaign=(int)get_query_var('peracrm_campaign_id'); $job=(int)get_query_var('peracrm_campaign_job_id');
$before=max(0,(int)($_GET['before'] ?? 0)); $revision=max(0,(int)($_GET['revision_id'] ?? 0));
peracrm_frontend_render_shell_header();
?>
<main id="primary" class="site-main crm-page crm-page--campaigns">
<?php peracrm_frontend_render_partial('crm-header',['title'=>'Operational queue','active_view'=>'campaigns']); ?>
<section class="content-panel content-panel--overlap-hero"><div class="content-panel-box border-dm crm-layout"><div class="crm-layout__main peracrm-campaigns peracrm-queue">
<nav class="peracrm-campaign-actions" aria-label="Queue navigation"><a href="<?php echo esc_url(peracrm_campaign_drafts_url()); ?>">Campaigns</a><a href="<?php echo esc_url(peracrm_campaign_queue_url()); ?>">Queue overview</a><?php if ($campaign) : ?><a href="<?php echo esc_url(peracrm_campaign_drafts_url($campaign.'/')); ?>">Campaign #<?php echo (int)$campaign; ?></a><a href="<?php echo esc_url(peracrm_campaign_queue_url($campaign)); ?>">Published revisions and campaign jobs</a><?php endif; ?></nav>
<p class="crm-notice peracrm-queue-disabled" role="note"><strong>Dispatch disabled — scheduled work will not send.</strong></p>
<p>Queue preparation and lifecycle intent only. Sending, real intake and public collection remain disabled.</p>
<?php try {
if ($campaign && !peracrm_campaign_draft_get($campaign)) { throw new RuntimeException(); }
if ($job) {
    $summary=peracrm_campaign_queue_ui_summary($campaign,$job); $current=$summary['job'];
    echo '<h2>'.esc_html(peracrm_campaign_draft_get($campaign)['document']['internal_name'] ?: 'Untitled campaign').'</h2>';
}
?>
<section data-campaign-queue data-id="<?php echo (int)$campaign; ?>" data-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('peracrm_campaign_queue_ui')); ?>" data-service-nonce="<?php echo esc_attr(wp_create_nonce('peracrm_campaign_queue_'.$campaign)); ?>">
<?php if ($job) : ?>
<label>Schedule intent in Istanbul (Europe/Istanbul)<input type="datetime-local" step="60" data-queue-schedule value="<?php echo esc_attr($current['scheduled_at'] ? (new DateTimeImmutable($current['scheduled_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d\TH:i') : ''); ?>" aria-describedby="queue-schedule-help queue-feedback"></label>
<p id="queue-schedule-help">This input always means Europe/Istanbul. The server converts it explicitly to UTC. Recording a time does not activate dispatch.</p>
<?php endif; ?>
<p id="queue-feedback" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" data-queue-feedback></p>
<div data-queue-panel>
<?php if ($job) { echo peracrm_campaign_queue_ui_panel($campaign,$job); }
elseif ($campaign) {
    $published=peracrm_campaign_queue_ui_revisions($campaign,max(0,(int)($_GET['revisions_before'] ?? 0)));
    echo '<h2>Published frozen revisions</h2><p>Preparation is idempotent for each published revision, including cancelled jobs. Draft and unpublished revisions cannot prepare a queue.</p><ul>';
    foreach (array_slice($published,0,20) as $item) {
        echo '<li>Frozen revision '.(int)$item['draft_revision'].' · '.esc_html(peracrm_campaign_queue_ui_time($item['published_at']));
        if ($item['job_id']) { echo ' · <a href="'.esc_url(peracrm_campaign_queue_url($campaign,(int)$item['job_id'])).'">Inspect job #'.(int)$item['job_id'].'</a>'; }
        else { echo ' <button type="button" class="btn btn--ghost" data-queue-action="prepare" data-revision="'.(int)$item['id'].'">Prepare queue for revision '.(int)$item['draft_revision'].'</button>'; }
        echo '</li>';
    }
    echo '</ul>';
    if (!$published) { echo '<p>No published frozen revisions on this page. Review, finish materialization and publish a frozen revision from the campaign first.</p>'; }
    if ($revision && !array_filter(array_slice($published,0,20),static function ($r) use ($revision) { return (int)$r['id']===$revision; })) { echo '<p>Requested revision is not on this page. Use published revision history; unpublished revisions cannot prepare.</p>'; }
    if (count($published)>20) { echo '<a href="'.esc_url(add_query_arg('revisions_before',(int)$published[19]['id'],peracrm_campaign_queue_url($campaign))).'">Older published revisions</a>'; }
} ?>
</div></section>
<?php if (!$job) : $jobs=peracrm_campaign_queue_ui_jobs($campaign,$before); ?>
<h2><?php echo $campaign ? 'Campaign jobs' : 'Queue jobs'; ?></h2><p>Up to 25 jobs per page. Failed jobs need preparation recovery; failed deliveries are counted separately on job detail.</p>
<div class="peracrm-subscriber-table" role="region" tabindex="0" aria-label="Queue jobs"><table><thead><tr><th>Campaign / frozen revision</th><th>Job lifecycle</th><th>Preparation</th><th>Schedule intent (Europe/Istanbul)</th><th>Detail</th></tr></thead><tbody>
<?php foreach (array_slice($jobs,0,25) as $item) : ?><tr><td><?php echo esc_html(($item['internal_name'] ?: 'Untitled campaign').' · #'.$item['campaign_id'].' · Frozen revision '.$item['draft_revision']); ?></td><td><?php echo esc_html($item['state']); ?></td><td><?php echo (int)$item['prepared_count'].' / '.(int)$item['expected_count'].' · '.((int)$item['complete'] ? 'complete' : 'incomplete'); ?></td><td><?php echo esc_html(peracrm_campaign_queue_ui_time($item['scheduled_at'])); ?></td><td><a href="<?php echo esc_url(peracrm_campaign_queue_url((int)$item['campaign_id'],(int)$item['id'])); ?>">Job #<?php echo (int)$item['id']; ?></a></td></tr><?php endforeach; ?>
<?php if (!$jobs) : ?><tr><td colspan="5">No queue jobs on this page. Prepare a published frozen revision from its campaign.</td></tr><?php endif; ?></tbody></table></div>
<nav aria-label="Queue pages"><?php if ($before) : ?><a href="<?php echo esc_url(peracrm_campaign_queue_url($campaign)); ?>">Newest jobs</a><?php endif; ?><?php if (count($jobs)>25) : ?><a href="<?php echo esc_url(add_query_arg('before',(int)$jobs[24]['id'],peracrm_campaign_queue_url($campaign))); ?>">Older jobs</a><?php endif; ?></nav>
<?php endif; ?>
<?php echo peracrm_campaign_queue_ui_health(); ?>
<?php } catch (Throwable $error) { echo '<p role="alert">Queue state unavailable. Check campaign access and storage readiness, then reload. No changes were made by this read.</p>'; } ?>
</div><?php peracrm_frontend_render_partial('crm-side-nav',['active_view'=>'campaigns']); ?></div></section></main>
<?php peracrm_frontend_render_shell_footer(); ?>
