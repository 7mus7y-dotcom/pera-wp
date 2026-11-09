<?php
if (!defined('ABSPATH')) { exit; }
pera_crm_gate_or_redirect();
peracrm_frontend_render_shell_header();
?>
<main id="primary" class="site-main crm-page crm-page--appointments">
<?php peracrm_frontend_render_partial('crm-header',['title'=>__('Appointments','peracrm'),'description'=>__('Shared team calendar','peracrm'),'active_view'=>'appointments']); ?>
<section class="content-panel"><div class="content-panel-box border-dm crm-layout"><div class="crm-layout__main">
<section class="crm-panel-surface crm-section" data-appointment-calendar>
  <div class="appointment-toolbar"><button type="button" class="btn btn--solid btn--blue" data-appointment-new="0">New appointment</button><button type="button" class="btn btn--ghost btn--blue" data-calendar-prev aria-label="Previous period">←</button><button type="button" class="btn btn--ghost btn--blue" data-calendar-today>Today</button><button type="button" class="btn btn--ghost btn--blue" data-calendar-next aria-label="Next period">→</button><h2 class="crm-section__title" data-calendar-heading></h2></div>
  <form class="appointment-toolbar" data-calendar-filters>
    <label>View <select name="view"><option value="day">Day</option><option value="week" selected>Week</option><option value="month">Month</option><option value="list">List</option></select></label>
    <label>Date <input type="date" name="date" required></label>
    <label>Timezone <select name="timezone"><?php foreach(timezone_identifiers_list() as $tz): ?><option <?php selected($tz,'Europe/Istanbul'); ?>><?php echo esc_html($tz); ?></option><?php endforeach; ?></select></label>
    <label>Advisor <select name="advisor_id"><option value="">All advisors</option></select></label>
    <label>Type <select name="type"><option value="">All types</option><?php foreach(peracrm_appointment_types() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
    <label>Status <select name="status"><option value="">All statuses</option><?php foreach(peracrm_appointment_statuses() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
    <label>Client search <input type="search" name="client_search" autocomplete="off" data-calendar-client-search></label><div data-calendar-client-results></div><button type="button" class="btn btn--ghost btn--blue" data-calendar-clear-client hidden>Clear client</button>
  </form>
  <p data-calendar-message role="status" aria-live="polite" tabindex="-1"></p>
  <div data-calendar-content aria-label="Appointment calendar"></div>
</section>
</div><?php peracrm_frontend_render_partial('crm-side-nav',['active_view'=>'appointments']); ?></div></section></main>
<?php peracrm_frontend_render_shell_footer(); ?>
