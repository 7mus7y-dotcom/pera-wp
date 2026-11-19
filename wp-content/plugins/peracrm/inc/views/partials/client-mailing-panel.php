<?php
if (!defined('ABSPATH')) { exit; }
if (!peracrm_campaigns_staff_can('peracrm_manage_subscribers')) { return; }
try { $mailing = peracrm_client_mailing_status((int)$client_id); }
catch (Throwable $error) { $mailing = ['status'=>'unavailable', 'email'=>'', 'subscriber_id'=>0]; }
$labels = [
    'missing_email'=>__('No email saved', 'peracrm'), 'invalid_email'=>__('Email needs correcting', 'peracrm'),
    'missing'=>__('Not on the mailing list', 'peracrm'), 'unconfirmed'=>__('Marketing permission not confirmed', 'peracrm'),
    'subscribed'=>__('Subscribed', 'peracrm'), 'unsubscribed'=>__('Unsubscribed', 'peracrm'),
    'suppressed'=>__('Do not email', 'peracrm'), 'unavailable'=>__('Mailing status unavailable', 'peracrm'),
];
$may_write = peracrm_campaigns_staff_can('peracrm_manage_subscribers', true);
$intake = false;
try { $intake = $may_write && peracrm_subscribers_intake_allowed(false); }
catch (Throwable $error) { $mailing = ['status'=>'unavailable', 'email'=>'', 'subscriber_id'=>0]; }
?>
<article id="crm-client-mailing-list" class="crm-panel-surface crm-section crm-client-mailing-panel">
  <header class="crm-section__header"><h3 class="crm-section__title"><?php esc_html_e('Mailing list', 'peracrm'); ?></h3></header>
  <?php if (isset($_GET['mailing_notice'])) : ?><p role="status" class="crm-notice"><?php echo esc_html($_GET['mailing_notice'] === 'saved' ? __('Mailing permission saved.', 'peracrm') : __('Could not save mailing permission. Reload and check the email, permission reference and subscriber intake.', 'peracrm')); ?></p><?php endif; ?>
  <p data-client-mailing-status="<?php echo esc_attr($mailing['status']); ?>"><strong><?php echo esc_html($labels[$mailing['status']]); ?></strong></p>
  <?php if ($mailing['email'] !== '') : ?><p class="crm-client-mailing-email"><?php echo esc_html($mailing['email']); ?></p><?php endif; ?>
  <?php if (in_array($mailing['status'], ['suppressed','unsubscribed'], true)) : ?>
    <p class="crm-section__description"><?php esc_html_e('An existing withdrawal or suppression blocks marketing email. It cannot be cleared from this panel.', 'peracrm'); ?></p>
  <?php elseif ($mailing['status'] === 'subscribed') : ?>
    <p class="crm-section__description"><?php esc_html_e('This email is already on the mailing list. Campaign audience rules still apply.', 'peracrm'); ?></p>
  <?php elseif (in_array($mailing['status'], ['missing_email','invalid_email'], true)) : ?>
    <p class="crm-section__description"><?php esc_html_e('Save a valid email under Profile and key facts first.', 'peracrm'); ?></p>
  <?php endif; ?>
  <?php if ($mailing['subscriber_id']) : ?><p><a class="btn btn--ghost btn--blue" href="<?php echo esc_url(peracrm_subscribers_url($mailing['subscriber_id'].'/')); ?>"><?php esc_html_e('Manage subscriber', 'peracrm'); ?></a></p><?php endif; ?>
  <?php if ($may_write && in_array($mailing['status'], ['missing','unconfirmed'], true)) : ?>
    <?php if (!$intake) : ?><p class="crm-section__description"><?php esc_html_e('Enable real subscriber intake in Subscribers before adding mailing permission.', 'peracrm'); ?></p>
    <?php else : ?>
    <form class="crm-form-stack" method="post" action="<?php echo esc_url(peracrm_with_target_blog(static function () { return admin_url('admin-post.php'); })); ?>">
      <?php wp_nonce_field('peracrm_client_mailing_'.(int)$client_id); ?>
      <input type="hidden" name="action" value="peracrm_client_mailing">
      <input type="hidden" name="client_id" value="<?php echo (int)$client_id; ?>">
      <input type="hidden" name="expected_email" value="<?php echo esc_attr($mailing['email']); ?>">
      <label for="client-mailing-evidence-<?php echo (int)$client_id; ?>"><?php esc_html_e('Permission reference', 'peracrm'); ?></label>
      <input id="client-mailing-evidence-<?php echo (int)$client_id; ?>" name="evidence_ref" type="text" maxlength="191" required placeholder="<?php esc_attr_e('For example: the client’s WhatsApp reply agreeing to updates', 'peracrm'); ?>">
      <label><input type="checkbox" name="permission_confirmed" value="1" required> <?php esc_html_e('I have confirmed this client’s permission to receive Pera Property marketing emails.', 'peracrm'); ?></label>
      <p class="crm-section__description"><?php esc_html_e('Your confirmation and this reference will be recorded.', 'peracrm'); ?></p>
      <button class="btn btn--ghost btn--blue" type="submit"><?php echo esc_html($mailing['status'] === 'missing' ? __('Add to mailing list', 'peracrm') : __('Confirm mailing permission', 'peracrm')); ?></button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</article>
