<?php
/** Authorized client workspace panel controls. */
if (!defined('ABSPATH') || !peracrm_client_panels_allowed($client_id)) { return; }
$eligible_panels = peracrm_client_panels_eligible($profile, $party);
?>
<section aria-labelledby="crm-manage-panels-title" class="crm-section crm-section--flush crm-manage-panels" id="crm-manage-panels" data-crm-manage-panels data-client-id="<?php echo esc_attr($client_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('peracrm_client_panels_' . $client_id)); ?>">
  <header class="crm-section__header">
    <div class="crm-section__heading-group">
      <h2 class="crm-section__title" id="crm-manage-panels-title" tabindex="-1"><?php esc_html_e('Manage panels', 'peracrm'); ?></h2>
      <p class="crm-section__description"><?php esc_html_e('Employees can customise which optional panels are visible for this client.', 'peracrm'); ?></p>
    </div>
  </header>
  <div class="crm-section__body">
    <noscript><p><?php esc_html_e('Enable JavaScript to change panel visibility.', 'peracrm'); ?></p></noscript>
    <p class="crm-section__meta" data-crm-panels-empty <?php if (array_intersect(array_keys($eligible_panels), $hidden_panels)) { echo 'hidden'; } ?>><?php esc_html_e('No panels are hidden.', 'peracrm'); ?></p>
    <ul class="crm-row-list">
      <?php foreach ($eligible_panels as $key => $panel) : ?>
      <li class="crm-row-list__item">
        <div class="crm-row-list__content">
          <label class="crm-row-list__title" for="crm-panel-toggle-<?php echo esc_attr($key); ?>"><?php echo esc_html($panel['label']); ?></label>
          <span class="crm-row-list__meta" id="crm-panel-state-<?php echo esc_attr($key); ?>" data-crm-panel-state="<?php echo esc_attr($key); ?>"><?php echo esc_html(in_array($key, $hidden_panels, true) ? __('Hidden', 'peracrm') : __('Visible', 'peracrm')); ?></span>
        </div>
        <input type="checkbox" id="crm-panel-toggle-<?php echo esc_attr($key); ?>" aria-describedby="crm-panel-state-<?php echo esc_attr($key); ?>" data-crm-panel-toggle="<?php echo esc_attr($key); ?>" <?php checked(!in_array($key, $hidden_panels, true)); ?> disabled />
      </li>
      <?php endforeach; ?>
    </ul>
    <div class="crm-section__actions">
      <button type="button" class="btn btn--ghost btn--blue" data-crm-panels-restore disabled><?php esc_html_e('Restore all panels', 'peracrm'); ?></button>
    </div>
  </div>
</section>
<p class="crm-panel-feedback" data-crm-panels-feedback role="status" aria-live="polite" tabindex="-1" data-error="<?php esc_attr_e('Unable to save panel preferences. Please refresh and try again.', 'peracrm'); ?>" data-saved="<?php esc_attr_e('Panel preferences saved.', 'peracrm'); ?>" data-hidden-label="<?php esc_attr_e('Hidden', 'peracrm'); ?>" data-visible-label="<?php esc_attr_e('Visible', 'peracrm'); ?>"></p>
