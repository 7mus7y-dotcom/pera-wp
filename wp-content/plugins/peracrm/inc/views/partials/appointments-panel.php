<?php if (!defined('ABSPATH')) { exit; } ?>
<article id="crm-client-appointments" class="crm-panel-surface crm-section" data-crm-panel="appointments" <?php peracrm_client_panel_attributes('appointments',$hidden_panels); ?>>
  <header class="crm-section__header"><h3 class="crm-section__title"><?php esc_html_e('Appointments','peracrm'); ?></h3><?php peracrm_client_panel_dismiss('appointments'); ?></header>
  <button type="button" class="btn btn--ghost btn--blue" data-appointment-new="<?php echo (int)$client_id; ?>"><?php esc_html_e('New appointment','peracrm'); ?></button>
  <div data-appointment-history="<?php echo (int)$client_id; ?>" aria-live="polite"><?php esc_html_e('Loading appointments…','peracrm'); ?></div>
</article>
