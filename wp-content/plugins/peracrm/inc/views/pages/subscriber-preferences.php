<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Email preferences</title><link rel="stylesheet" href="<?php echo esc_url(PERACRM_URL . 'assets/frontend/crm.css'); ?>"></head>
<body class="crm-route"><main id="primary" class="crm-page peracrm-preferences"><h1>Email preferences</h1>
<?php if ($message !== '') : ?><p role="status"><?php echo esc_html($message); ?></p><?php endif; ?>
<?php if ($valid) : ?>
<p>Choose whether to receive marketing email. Opening this page makes no changes.</p>
<form method="post" action="<?php echo esc_url(peracrm_subscribers_preferences_url()); ?>" class="peracrm-subscriber-form">
<input type="hidden" name="token" value="<?php echo esc_attr($token); ?>"><input type="hidden" name="preference_action" value="withdraw">
<button class="btn" type="submit">Unsubscribe from all marketing email</button></form>
<form method="post" action="<?php echo esc_url(peracrm_subscribers_preferences_url()); ?>" class="peracrm-subscriber-form">
<input type="hidden" name="token" value="<?php echo esc_attr($token); ?>"><input type="hidden" name="preference_action" value="preferences">
<label for="public-topics">Replace topic preferences (topic keys separated by |)</label><input id="public-topics" name="topics" maxlength="2079" placeholder="news|properties" value="<?php echo esc_attr(implode('|', peracrm_subscribers_public_topics($token))); ?>">
<p>Leave blank to remove all topics. Topic choices never subscribe you or remove a global unsubscribe.</p><button class="btn btn--ghost" type="submit">Save topic choices</button></form>
<?php else : ?><p>This link is unavailable. Please use the original preferences link.</p><?php endif; ?>
</main></body></html>
